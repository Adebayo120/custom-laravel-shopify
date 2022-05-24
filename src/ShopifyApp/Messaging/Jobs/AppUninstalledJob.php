<?php

namespace Osiset\ShopifyApp\Messaging\Jobs;

use App\ShopifyShop;
use stdClass;
use App\Jobs\SendMailJob;
use Illuminate\Bus\Queueable;
use App\Classes\Queue\QueueClass;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Osiset\ShopifyApp\Actions\CancelCurrentPlan;
use Osiset\ShopifyApp\Contracts\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Contracts\Commands\Shop as IShopCommand;
use App\Jobs\Account\Downgrade\DowngradeAccountToFreePlanJob;

/**
 * Webhook job responsible for handling when the app is uninstalled.
 */
class AppUninstalledJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The shop domain.
     *
     * @var ShopDomain
     */
    protected $domain;

    /**
     * The webhook data.
     *
     * @var object
     */
    protected $data;

    /**
     * Create a new job instance.
     *
     * @param ShopDomain $domain  The shop domain.
     * @param stdClass   $data   The webhook data (JSON decoded).
     *
     * @return self
     */
    public function __construct(ShopDomain $domain, stdClass $data)
    {
        $this->domain = $domain;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @param IShopCommand      $shopCommand             The commands for shops.
     * @param IShopQuery        $shopQuery               The querier for shops.
     * @param CancelCurrentPlan $cancelCurrentPlanAction The action for cancelling the current plan.
     *
     * @return bool
     */
    public function handle(
        IShopCommand $shopCommand,
        IShopQuery $shopQuery,
        CancelCurrentPlan $cancelCurrentPlanAction
    ): bool {
        // Get the shop
        $shop = $shopQuery->getByDomain($this->domain);

        $shopId = $shop->getId();
        
        // Cancel the current plan
        $cancelCurrentPlanAction($shopId);
        
        // Purge shop of token, plan, etc.
        $shopCommand->clean( $shopId );

        $emailData = [];
        $emailData['store_name'] = $shop->shop_name;
        
        $shop->is_stripe_user = 1;
        $shop->shop_name = null;
        $shop->shop_email = null;
        if( $shop->from_shopify )
        {
            DowngradeAccountToFreePlanJob::dispatch( $shop->id )->onQueue( "account_upgrades_and_downgrades" );
            
            $shop->plan_type = 'free';
            $shop->from_shopify = 0;
        }

        $shop->save();

        // Soft delete the shop.
        $shopify_shop = ShopifyShop::where('user_id', $shop->id)->first();
        $shopify_shop->deleted_at = now();
        $shopify_shop->password = '';
        $shopify_shop->save();
        
        $emailData['name'] = $shop->name;
        $emailData['email'] = $shop->email;
        SendMailJob::dispatch($emailData, 'App Uninstallation Successful', 'emails.shopify.app_unistallation_success')->onQueue( QueueClass::MAILS );

        return true;
    }
}

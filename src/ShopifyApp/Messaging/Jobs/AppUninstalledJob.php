<?php

namespace Osiset\ShopifyApp\Messaging\Jobs;

use App\ShopifyShop;
use stdClass;
use Illuminate\Bus\Queueable;
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

        $shop->is_stripe_user = 1;
        $shop->shop_name = null;
        $shop->shop_email = null;
        if( $shop->from_shopify )
        {
            $shop->plan_type = 'free';
            $shop->from_shopify = 0;
        }

        $shop->save();

        DowngradeAccountToFreePlanJob::dispatch( $shop->id )->onQueue( "account_upgrades_and_downgrades" );

        // Soft delete the shop.
        ShopifyShop::where('user_id', $shop->id)->delete();

        return true;
    }
}

<?php

namespace Osiset\ShopifyApp\Storage\Commands;

use App\User;
use App\ShopifyShop;
use App\Traits\ShopsTrait;
use Illuminate\Support\Facades\DB;
use Osiset\ShopifyApp\Contracts\ShopModel;
use Osiset\ShopifyApp\Objects\Values\ShopId;
use Osiset\ShopifyApp\Traits\ConfigAccessible;
use Osiset\ShopifyApp\Contracts\Queries\Shop as ShopQuery;
use Osiset\ShopifyApp\Contracts\Commands\Shop as ShopCommand;
use Osiset\ShopifyApp\Contracts\Objects\Values\PlanId as PlanIdValue;
use Osiset\ShopifyApp\Contracts\Objects\Values\ShopDomain as ShopDomainValue;
use Osiset\ShopifyApp\Contracts\Objects\Values\AccessToken as AccessTokenValue;
use Osiset\ShopifyApp\Storage\Models\Plan;

/**
 * Reprecents the commands for shops.
 */
class Shop implements ShopCommand
{
    use ConfigAccessible, ShopsTrait;

    /**
     * The shop model (configurable).
     *
     * @var ShopModel
     */
    protected $model;

    /**
     * The querier.
     *
     * @var ShopQuery
     */
    protected $query;

    /**
     * Init for shop command.
     */
    public function __construct(ShopQuery $query)
    {
        $this->query = $query;
        $this->model = $this->getConfig('user_model');
    }

    /**
     * {@inheritdoc}
     */
    public function make(ShopDomainValue $domain, AccessTokenValue $token): ShopId
    {
        if( session()->has('shop') )
        {
            $shop = User::find(session('shop'));
            $shop->shop_name = $domain->toNative();
            $shop->shop_password = $token->isNull() ? '' : $token->toNative();
            $shop->shop_email = "shop@{$domain->toNative()}";
            $shop->save();
        }
        else
        {
            $model = $this->model;
            $shop = new $model();
            $shop->shop_name = $domain->toNative();
            $shop->shop_password = $token->isNull() ? '' : $token->toNative();
            $shop->shop_email = "shop@{$domain->toNative()}";
            $shop->save();
        }
        return $shop->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function setToPlan(ShopId $shopId, PlanIdValue $planId): bool
    {
        $shop = $this->getShop($shopId);
        $shop->plan_id = $planId->toNative();
        $shop->shopify_freemium = false;
        $shop->plan_type = Plan::find( $planId->toNative() )->name;

        return $shop->save();
    }

    /**
     * {@inheritdoc}
     */
    public function setAccessToken(ShopId $shopId, AccessTokenValue $token): bool
    {
        $shop = $this->getShop( $shopId );
        $shop->shop_password = $token->toNative();
        $persisted_shop_seperate_table =  ShopifyShop::withTrashed()->where( 'email', $shop->shop_email );
        if( $persisted_shop_seperate_table_first = $persisted_shop_seperate_table->first() )
        {
            $this->delete_shop_with_relations ( $persisted_shop_seperate_table_first, $persisted_shop_seperate_table, $shop);
        }
        else
        {
            $user_owned_shopify_shop = ShopifyShop::where( "user_id", $shop->id );
            if (  $user_owned_shopify_shop_first = $user_owned_shopify_shop ->first() )
            {
                $this->delete_shop_with_relations ( $user_owned_shopify_shop_first, $user_owned_shopify_shop, $shop );
            }

            $shop_seperate_table= new ShopifyShop();
            $shop_seperate_table->name = $shop->shop_name;
            $shop_seperate_table->password = $token->toNative();
            $shop_seperate_table->email = $shop->shop_email;
            $shop_seperate_table->user_id = $shop->id;
            $shop_seperate_table->persisted = 0;
            $shop_seperate_table->save();
        }

        return $shop->save(); 
    }

    /**
     * delete_shop_with_relations
     *
     * @param [type] $shopify_shop
     * @return void
     */
    public function delete_shop_with_relations ( $shopify_shop_first, $shopify_shop, $user )
    {
        $subaccounts_ids = $user->subAccounts->pluck("id")->toArray();

        $subaccount = $shopify_shop_first->subaccount;
        $subaccount->orders_count = 0;
        $subaccount->relations_revenue_array = $this->default_relations_revenue_array();
        $subaccount->save();

        // DELETE RELATED TABLES
        DB::table("customers")->where( "customerable_id", $shopify_shop_first->id )->where( "customerable_type", "App\ShopifyShop" )->delete();
        DB::table("products")->where( "productable_id", $shopify_shop_first->id )->where( "productable_type", "App\ShopifyShop" )->delete();
        DB::table( "orders" )->where( "orderable_id", $shopify_shop_first->id )->where( "orderable_type", "App\ShopifyShop" )->delete();

        $shopify_shop->forceDelete();

        DB::table("contacts")->whereIn("sub_account_id", $subaccounts_ids )
                            ->update( [ 
                                "shopify_revenue_value" => "0.00", 
                                "relations_revenue_array" => $this->default_relations_revenue_array() 
                            ] );

        DB::table( "automations" )->whereIn("sub_account_id", $subaccounts_ids )->update( [ "shopify_revenue_value" => "0.00" ] );
        DB::table("bots")->whereIn("sub_account_id", $subaccounts_ids )->update( [ "shopify_revenue_value" => "0.00" ] );
        DB::table("campaigns")->whereIn("sub_account_id", $subaccounts_ids )->update( [ "shopify_revenue_value" => "0.00" ] );
        DB::table( "sms_campaigns" )->whereIn( "sub_account_id", $subaccounts_ids )->update( [ "shopify_revenue_value" => "0.00" ] );
        DB::table( "push_messages" )->where( "user_id", $user->id )->update( [ "shopify_revenue_value" => "0.00" ] );
    }

    /**
     * {@inheritdoc}
     */
    public function clean(ShopId $shopId): bool
    {
        $shop = $this->getShop($shopId);
        $shop->shop_password = '';
        $shop->plan_id = null;

        return $shop->save();
    }

    /**
     * {@inheritdoc}
     */
    public function softDelete(ShopId $shopId): bool
    {
        $shop = $this->getShop($shopId);
        $shop->charges()->delete();

        return $shop->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function restore(ShopId $shopId): bool
    {
        $shop = $this->getShop($shopId, true);
        $shop->charges()->restore();

        return $shop->restore();
    }

    /**
     * {@inheritdoc}
     */
    public function setAsFreemium(ShopId $shopId): bool
    {
        $shop = $this->getShop($shopId);
        $this->setAsFreemiumByRef($shop);

        return $shop->save();
    }

    /**
     * {@inheritdoc}
     */
    public function setNamespace(ShopId $shopId, string $namespace): bool
    {
        $shop = $this->getShop($shopId);
        $this->setNamespaceByRef($shop, $namespace);

        return $shop->save();
    }

    /**
     * Sets a shop as freemium.
     *
     * @param ShopModel $shop The shop model (reference).
     *
     * @return void
     */
    public function setAsFreemiumByRef(ShopModel &$shop): void
    {
        $shop->shopify_freemium = true;
    }

    /**
     * Sets a shop namespace.
     *
     * @param ShopModel $shop      The shop model (reference).
     * @param string    $namespace The namespace.
     *
     * @return void
     */
    public function setNamespaceByRef(ShopModel &$shop, string $namespace): void
    {
        $shop->shopify_namespace = $namespace;
    }

    /**
     * Helper to get the shop.
     *
     * @param ShopId $shopId      The shop's ID.
     * @param bool   $withTrashed Include trashed shops?
     *
     * @return ShopModel|null
     */
    protected function getShop(ShopId $shopId, bool $withTrashed = false): ?ShopModel
    {
        return $this->query->getById($shopId, [], $withTrashed);
    }
}

<?php

namespace Osiset\ShopifyApp\Traits;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Illuminate\Http\Response as ResponseResponse;
use App\ShopifyShop;

/**
 * Responsible for handling incoming webhook requests.
 */
trait WebhookController
{
    use ConfigAccessible;

    /**
     * Handles an incoming webhook.
     *
     * @param string  $type    The type of webhook
     * @param Request $request The request object.
     *
     * @return ResponseResponse
     */
    public function handle(string $type, Request $request): ResponseResponse
    {
        $shopDomain = new ShopDomain( $request->header( 'x-shopify-shop-domain' ) );

        if ( !ShopifyShop::where( 'name', $shopDomain->toNative() )->where( 'persisted', 1 )->first() )
        {
            return Response::make( '', 201 );
        }

        if ( $type == "carts-update" || $type == "carts-create" )
        {
            // Get the job class and dispatch
            $jobClass = $this->getConfig('job_namespace').str_replace('-', '', ucwords($type, '-')).'Job';
            $jobData = json_decode($request->getContent());
            $jobClass::dispatch(
                $shopDomain,
                $jobData
            )->onQueue('shopify')
            ->delay( Carbon::now()->addSeconds(2) );
        }
        else
        {
            // Get the job class and dispatch
            $jobClass = $this->getConfig('job_namespace').str_replace('-', '', ucwords($type, '-')).'Job';
            $jobData = json_decode($request->getContent());
            $jobClass::dispatch(
                $shopDomain,
                $jobData
            )->onQueue('shopify');
        }

        return Response::make('', 201);
    }
}

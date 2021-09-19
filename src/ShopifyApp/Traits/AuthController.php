<?php

namespace Osiset\ShopifyApp\Traits;

use App\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Classes\Queue\QueueClass;
use Illuminate\Support\Facades\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Osiset\ShopifyApp\Actions\AuthorizeShop;
use App\Constants\Models\User\OnboardingTask;
use Illuminate\Contracts\View\View as ViewView;
use Osiset\ShopifyApp\Actions\AuthenticateShop;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use App\Jobs\Engage\AddUserActionToEngageJob;
use App\Constants\Engage\EngageUserTrackedEvent;


/**
 * Responsible for authenticating the shop.
 */
trait AuthController
{
    /**
     * Index route which displays the login page.
     *
     * @param Request $request The HTTP request.
     *
     * @return ViewView
     */
    public function index(Request $request): ViewView
    {
        return view("shopify.shop_login")->with( [ 'shopDomain' => $request->query('shop') ] );
    }

    /**
     * Authenticating a shop.
     *
     * @param AuthenticateShop $authenticateShop The action for authorizing and authenticating a shop.
     *
     * @return ViewView|RedirectResponse
     */
    public function authenticate(Request $request, AuthenticateShop $authenticateShop)
    {
        $store = Validator::make( $request->all(), [
            "shop" => "required"                
        ]);

        if ( $store->fails() )
        {
            return back()->with( "error", $store->getMessageBag()->first() );
        }

        // Get the shop domain
        $shopDomain = new ShopDomain( $request->get('shop') );
        if( $request->get('shop_user') )
        {
            //is the typed in shop correct
            if( !Str::endsWith( $request->get('shop'), '.myshopify.com' ) ) 
            {
                return back()->with('error', 'Invalid Shop Input');
            }
            //is it already installed for one of software users
            $existing_user = User::where( 'shop_name', $request->get('shop') )->where('shop_password', '!=', '' )->first();

            if( $existing_user )
            {
                return back()->with('error', 'Sendmunk is Already Been Installed On This Store');
            }
            else
            {
                //has someone tried installing before but didn't finish up
                $existing_user = User::where( 'shop_name', $request->get('shop') )->where('shop_password', '')->first();
                // is it same percon or another
                if( $existing_user && $existing_user->id != $request->shop_user )
                {
                    //did the person tried installing from software
                    if( $existing_user->email )
                    {
                        $existing_user->shop_name = null;
                        $existing_user->shop_email = null;
                        $existing_user->save();
                    }
                    // or the person tried installing from shopify plartform
                    else
                    {
                        $existing_user->forceDelete();
                    }
                }
            }
            session( [ 'shop' => $request->get( 'shop_user' ) ] );
        }
        // Run the action, returns [result object, result status]
        list( $result, $status ) = $authenticateShop($request);

        if ($status === null) {
            // Go to login, something is wrong
            return Redirect::route('shoplogin');
        } elseif ($status === false) {
            // No code, redirect to auth URL
            return $this->oauthFailure($result->url, $shopDomain);
        } else {
            // Everything's good... determine if we need to redirect back somewhere
            if(  session()->has('shop') )
            {
                $user = User::where( 'shop_name', $request->get('shop') )->first();
                $user->complete_onboarding_task( OnboardingTask::CONNECT_STORE );
                $integrated_shop_engage_track_event_properties = [
                    "shop_name"             => $user->shop_name,
                    "shop_email"            => $user->shop_email,
                ];
        
                AddUserActionToEngageJob::dispatch( $user->id, EngageUserTrackedEvent::INTEGRATED_SHOPIFY, $integrated_shop_engage_track_event_properties, now() )->onQueue( QueueClass::ENGAGE );

                session()->flash( 'status', $request->get('shop').' was Successfully Integrated Into Your Account' );
                session( [ 'return_to' => url('integration') ] );
                session()->forget('shop');
            }
            $return_to = Session::get('return_to');
            if ($return_to) {
                Session::forget('return_to');
                return Redirect::to($return_to);
            }

            // No return_to, go to home route
            return Redirect::route('shophome');
        }
    }

    /**
     * Simply redirects to Shopify's Oauth screen.
     *
     * @param Request       $request  The request object.
     * @param AuthorizeShop $authShop The action for authenticating a shop.
     *
     * @return ViewView
     */
    public function oauth(Request $request, AuthorizeShop $authShop): ViewView
    {
        // Setup
        // check software customner that have installing the shop
        session()->forget('shop');
        $user_that_tried_installing_shop = User::where( 'shop_name', $request->get('shop') )
                                ->where('shop_password', '')
                                ->whereNotNull('email')
                                ->first();
        if( $user_that_tried_installing_shop )
        {
            $user_that_tried_installing_shop->shop_name = null;
            $user_that_tried_installing_shop->shop_email = null;
            $user_that_tried_installing_shop->save();
        }

        $shopDomain = new ShopDomain( $request->get( 'shop' ) );
        $result = $authShop($shopDomain, null);

        // Redirect
        return $this->oauthFailure($result->url, $shopDomain);
    }

    /**
     * Handles when authentication is unsuccessful or new.
     *
     * @param string     $authUrl    The auth URl to redirect the user to get the code.
     * @param ShopDomain $shopDomain The shop's domain.
     *
     * @return ViewView
     */
    private function oauthFailure(string $authUrl, ShopDomain $shopDomain): ViewView
    {
        return View::make(
            'shopify-app::auth.fullpage_redirect',
            [
                'authUrl'    => $authUrl,
                'shopDomain' => $shopDomain->toNative(),
            ]
        );
    }
}

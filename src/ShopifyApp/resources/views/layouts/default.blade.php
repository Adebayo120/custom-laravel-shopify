<!DOCTYPE html>
<html lang="en">
    <head>
      @if ( app()->environment('production') )
            <!-- Hotjar Tracking Code for https://sendmunk.com -->
            <script>
            (function(h,o,t,j,a,r){
            h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
            h._hjSettings={hjid:2373155,hjsv:6};
            a=o.getElementsByTagName('head')[0];
            r=o.createElement('script');r.async=1;
            r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
            a.appendChild(r);
            })(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
            </script>
            <!-- Global site tag (gtag.js) - Google Analytics -->
            <script async src=“https://www.googletagmanager.com/gtag/js?id=UA-118161364-7”></script>
            <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'UA-118161364-7');
            </script>
      @endif
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>@yield('title', 'SendMunk')</title>
        @include('includes.partials.metatags')
        @include('includes.partials.themecolor')
        @include('includes.partials.favicons')
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <link rel="stylesheet" type="text/css" href='{{ asset("css/custom/smunk-app/smunk-app.min.css") }}' />
        <link rel="stylesheet" type="text/css" href='{{ asset("css/custom/smunk-app/auth/smunk-auth.css") }}' />
        <link rel="stylesheet" type="text/css" href="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>
        <link rel="stylesheet" type="text/css" href="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css"/>
        @include('includes.partials.tracking')
        @yield('styles')
    </head>

    <body>
        <body style="background: #0083BF;">
            <div class="smunk-auth-container-fluid">
                  <div class="smunk-auth-wrapper">
                        <div class="smunk-auth-content-left" style="background:#F8F9FB;">
                              <div class="smunk-branding">
                                    <a href="{{ url('/') }}">
                                          <img src="{{ asset('img/sendmunk-logo-3.svg') }}" alt="SendMunk logo" draggable="false"/>
                                    </a>
                              </div>
                              <div class="auth-header-content">
                                    @yield('auth-heading')
                                    <p class="auth-subheading">@yield('auth-subheading', 'Please, login to your account.')</p>
                              </div>
                              <div class="auth-notice smunk-auth-notice">
                                    @if ($errors->any())
                                          <div class="smunk-message warning message">
                                                <i class="smunk-close-icon material-icons">close</i>
                                                <ul>
                                                @foreach ($errors->all() as $error)
                                                      <li>{{ $error }}</li>
                                                @endforeach
                                                </ul>
                                          </div>
                                    @endif
                                    @if(Session::has('error'))
                                          <div class="smunk-message warning message">
                                                <i class="smunk-close-icon material-icons">close</i>
                                                <div class="header">{{ Session::get('error') }}</div>
                                          </div>
                                    @endif
                                    @if(Session::has('status'))
                                          <div class="smunk-message positive message">
                                                <i class="smunk-close-icon material-icons">close</i>
                                                <div class="header">{{ Session::get('status') }}</div>
                                          </div>
                                    @endif
                              </div>
                              @yield('auth-content-left')
                              {{-- BOTTOM AUTH LINK FOR MOBILE VIEW  --}}
                              @include('includes.frontend.authbottomlinks')
                        </div>
                        <div class="smunk-auth-content-right" style="background: #0083BF;">
                              @yield('auth-content-right')
                        </div>
                  </div>
            </div>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
            <script src="{{ asset('vendor/uaparser/ua-parser.min.js') }}"></script>
            <script type="text/javascript" src="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
            <script type="text/javascript" src="{{ asset('js/custom/smunk-app/script.js').smunkHelper::version_control() }}"></script>


        @if(config('shopify-app.appbridge_enabled'))
            <script src="https://unpkg.com/@shopify/app-bridge{{ config('shopify-app.appbridge_version') ? '@'.config('shopify-app.appbridge_version') : '' }}"></script>
            <script>
                var AppBridge = window['app-bridge'];
                var createApp = AppBridge.default;
                var app = createApp({
                    apiKey: '{{ config('shopify-app.api_key') }}',
                    shopOrigin: '{{ Auth::user()->shop_name }}',
                    forceRedirect: true,
                });
            </script>

            @include('shopify-app::partials.flash_messages')
        @endif
        @yield('footerscripts')
        @yield('scripts')

        <script>
            function initFreshChat(){window.fcWidget.init({token:"8044fbf5-9cde-4d20-b7af-f8383e57bd20",host:"https://wchat.freshchat.com"})}function initialize(t,i){var e;t.getElementById(i)?initFreshChat():((e=t.createElement("script")).id=i,e.async=!0,e.src="https://wchat.freshchat.com/js/widget.js",e.onload=initFreshChat,t.head.appendChild(e))}function initiateCall(){initialize(document,"freshchat-js-sdk")}window.addEventListener?window.addEventListener("load",initiateCall,!1):window.attachEvent("load",initiateCall,!1);
      </script>
    </body>
</html>
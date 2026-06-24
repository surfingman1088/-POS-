<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <title>{{ __($title ?? config('app.name', 'Laravel')) }}</title>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased" style="background:#fff8f3;">

        <div style="display:flex; min-height:100vh; width:100%;">

            {{-- 左側橘色品牌區 --}}
            <div class="login-left-panel" style="
                width:42%;
                background:linear-gradient(160deg,#f97316 0%,#ea580c 45%,#9a3412 100%);
                display:flex; flex-direction:column; align-items:center; justify-content:center;
                padding:48px 40px; position:relative; overflow:hidden;
            ">
                {{-- 裝飾圓圈 --}}
                <div style="
                    position:absolute; width:420px; height:420px; border-radius:50%;
                    border:60px solid rgba(255,255,255,0.07);
                    top:-130px; right:-130px; pointer-events:none;
                "></div>
                <div style="
                    position:absolute; width:300px; height:300px; border-radius:50%;
                    border:50px solid rgba(255,255,255,0.06);
                    bottom:-90px; left:-90px; pointer-events:none;
                "></div>

                {{-- Logo 圖示 --}}
                <div style="
                    width:80px; height:80px;
                    background:rgba(255,255,255,0.15);
                    border-radius:22px;
                    display:flex; align-items:center; justify-content:center;
                    margin-bottom:20px;
                    border:2px solid rgba(255,255,255,0.25);
                    position:relative; z-index:1;
                ">
                    <x-app-logo-icon class="h-10 fill-current text-white" />
                </div>

                {{-- 店名 --}}
                <div style="position:relative; z-index:1; text-align:center;">
                    @if(env('STORE_NAME_ALT'))
                        <div style="font-size:1.6rem; font-weight:900; color:white; letter-spacing:0.06em; line-height:1.1; text-shadow:0 2px 12px rgba(0,0,0,0.2);">
                            {{ env('STORE_NAME_ALT') }}
                        </div>
                        <div style="font-size:2.4rem; font-weight:900; color:white; letter-spacing:0.06em; line-height:1.1; text-shadow:0 2px 12px rgba(0,0,0,0.2);">
                            {{ env('STORE_NAME') }}
                        </div>
                    @else
                        <div style="font-size:2.6rem; font-weight:900; color:white; letter-spacing:0.06em; line-height:1.1; text-shadow:0 2px 12px rgba(0,0,0,0.2);">
                            {{ env('STORE_NAME', config('app.name')) }}
                        </div>
                    @endif

                    <div style="color:rgba(255,255,255,0.75); font-size:0.88rem; margin-top:8px; letter-spacing:0.08em;">
                        YO 團購管理系統
                    </div>

                    <div style="width:50px; height:3px; background:rgba(255,255,255,0.4); border-radius:2px; margin:18px auto;"></div>

                    <div style="color:rgba(255,255,255,0.6); font-size:0.78rem; line-height:1.7;">
                        高效管理<br>輕鬆掌握每一筆訂單
                    </div>
                </div>
            </div>

            {{-- 右側表單區 --}}
            <div style="
                flex:1; display:flex; align-items:center; justify-content:center;
                padding:48px 40px; background:#ffffff;
            ">
                <div style="width:100%; max-width:360px;">
                    {{ $slot }}
                </div>
            </div>

        </div>

        @fluxScripts
    </body>
</html>

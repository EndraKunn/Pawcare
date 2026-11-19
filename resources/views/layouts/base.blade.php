<!doctype html>
<html lang="id" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
<meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.options.host') }}">
<meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.options.port') }}">
<meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.options.scheme') }}">
<meta name="realtime-user-id" content="{{ auth()->id() }}">
<meta name="realtime-conversation-id" content="{{ request()->route('conversation') ?? '' }}">
<meta name="realtime-business-id" content="{{ auth()->check() && auth()->user()->isOwner() ? auth()->user()->primaryBusiness()?->id : '' }}">
<title>@yield('title', 'PawCare') · PawCare</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🐾</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/pawcare.css') }}">
@livewireStyles
@stack('head')
</head>
<body>
<div class="wrap">
  @yield('body')
</div>
<script src="{{ asset('vendor/pusher.min.js') }}" defer></script>
<script src="{{ asset('vendor/laravel-echo.js') }}" defer></script>
<script src="{{ asset('js/realtime.js') }}" defer></script>
<script src="{{ asset('js/app.js') }}" defer></script>
@livewireScripts
@stack('scripts')
</body>
</html>

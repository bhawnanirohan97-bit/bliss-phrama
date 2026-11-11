<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Console' }} · PharmaLead AI</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            PharmaLead AI
            <small>Bliss Pharmex</small>
        </a>

        <nav class="nav" aria-label="Main">
            <a href="{{ route('dashboard') }}" @class(['is-active' => request()->routeIs('dashboard')])>
                Dashboard
            </a>
            <a href="{{ route('catalogue.index') }}" @class(['is-active' => request()->routeIs('catalogue.*')])>
                Catalogue
                @if ($navUnreviewedCount > 0)
                    <span class="count">{{ $navUnreviewedCount }}</span>
                @endif
            </a>
            <a href="{{ route('settings.edit') }}" @class(['is-active' => request()->routeIs('settings.*')])>
                Settings
            </a>
        </nav>

        <div class="sidebar-footer">
            <div>
                Bot is
                <strong style="color: {{ $navBotEnabled ? 'var(--green)' : 'var(--amber)' }}">
                    {{ $navBotEnabled ? 'on' : 'off' }}
                </strong>
            </div>
            <div>Times shown in IST</div>
        </div>
    </aside>

    <main class="main">
        @if (session('status'))
            <div class="flash" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="flash is-error" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>
</div>

@stack('scripts')
</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Kopi Nusa</title>
    {{-- Self-contained styles: error pages must render even when assets, the session or the database are the problem. --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center; padding: 24px;
               background: #f5f5f4; color: #1c1917; font-family: Figtree, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: 100%; max-width: 420px; background: #fff; border: 1px solid #e7e5e4; border-radius: 16px; padding: 32px 28px; text-align: center;
               box-shadow: 0 1px 2px rgba(28, 25, 23, .04); }
        .logo { width: 44px; height: 44px; color: #d97706; }
        .code { margin: 16px 0 0; font-size: 13px; color: #78716c; font-variant-numeric: tabular-nums; }
        h1 { margin: 4px 0 0; font-size: 22px; line-height: 1.3; font-weight: 700; }
        p.message { margin: 12px 0 0; font-size: 15px; line-height: 1.6; color: #57534e; }
        .actions { margin-top: 24px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        .btn { display: inline-flex; align-items: center; justify-content: center; height: 40px; padding: 0 16px; border-radius: 8px; font: inherit;
               font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; transition: transform 160ms cubic-bezier(.23, 1, .32, 1); }
        .btn:active { transform: scale(.97); }
        .btn:focus-visible { outline: 2px solid #f59e0b; outline-offset: 2px; }
        .btn-primary { background: #1c1917; color: #fff; border: 0; }
        .btn-primary:hover { background: #44403c; }
        .btn-secondary { background: #fff; color: #292524; border: 1px solid #d6d3d1; }
        .btn-secondary:hover { background: #fafaf9; }
        @media (prefers-reduced-motion: reduce) { .btn:active { transform: none; } }
    </style>
</head>
<body>
    <main>
        <svg class="logo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 9h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V9Z" />
            <path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16" />
            <path d="M8 2.5c-.6.8-.6 1.7 0 2.5s.6 1.7 0 2.5M12 2.5c-.6.8-.6 1.7 0 2.5s.6 1.7 0 2.5" />
            <path d="M3 21.5h14" />
        </svg>
        <p class="code">Kode @yield('code')</p>
        <h1>@yield('title')</h1>
        <p class="message">@yield('message')</p>
        <div class="actions">
            <button type="button" class="btn btn-secondary" onclick="history.length > 1 ? history.back() : location.assign('/')">Kembali</button>
            @hasSection('primary')
                @yield('primary')
            @else
                <a class="btn btn-primary" href="{{ url('/') }}">Ke beranda</a>
            @endif
        </div>
    </main>
</body>
</html>

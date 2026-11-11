<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="/manifest.json">
    <title>AI Lead Bot Remote · Bliss Pharmex</title>
    <style>
        :root {
            --bg: #090d16;
            --card-bg: #131c2e;
            --card-border: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --green: #10b981;
            --green-glow: rgba(16, 185, 129, 0.25);
            --red: #ef4444;
            --red-glow: rgba(239, 68, 68, 0.2);
            --blue: #3b82f6;
            --amber: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            padding: 16px 14px 80px 14px;
            max-width: 520px;
            margin: 0 auto;
            min-height: 100vh;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 4px 16px 4px;
        }

        .brand-title {
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .brand-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .desktop-link {
            font-size: 0.8rem;
            color: var(--blue);
            text-decoration: none;
            padding: 6px 12px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            font-weight: 500;
        }

        /* Flash message */
        .toast {
            background: #1e293b;
            border-left: 4px solid var(--blue);
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.85rem;
            color: var(--text-main);
        }

        /* Status Hero Card */
        .hero-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            padding: 24px 20px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .hero-card.is-active {
            border-color: rgba(16, 185, 129, 0.4);
            box-shadow: 0 10px 30px -5px var(--green-glow);
        }

        .hero-card.is-paused {
            border-color: rgba(239, 68, 68, 0.3);
            box-shadow: 0 10px 30px -5px var(--red-glow);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .pill-active {
            background: rgba(16, 185, 129, 0.15);
            color: var(--green);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .pill-paused {
            background: rgba(239, 68, 68, 0.15);
            color: var(--red);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: currentColor;
            display: inline-block;
        }

        .pulse {
            animation: pulse-ring 2s infinite ease-in-out;
        }

        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.3); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }

        .hero-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .hero-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 22px;
            line-height: 1.4;
        }

        /* Power Switch Button */
        .power-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 16px;
            font-size: 1.05rem;
            font-weight: 700;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .power-btn:active {
            transform: scale(0.98);
        }

        .power-btn.btn-stop {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            color: #ffffff;
        }

        .power-btn.btn-start {
            background: linear-gradient(135deg, #10b981, #047857);
            color: #ffffff;
        }

        /* Quick Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 22px;
        }

        .stat-box {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 12px 8px;
            text-align: center;
        }

        .stat-num {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .stat-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 2px;
        }

        /* Section Container */
        .section-box {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 18px 16px;
            margin-bottom: 20px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .section-title {
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Domestic Toggle Card */
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            margin-bottom: 14px;
        }

        .toggle-info-title {
            font-size: 0.85rem;
            font-weight: 600;
        }

        .toggle-info-sub {
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        /* IOS Switch Style */
        .switch {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 28px;
            flex-shrink: 0;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #334155;
            transition: .3s;
            border-radius: 28px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--green);
        }

        input:checked + .slider:before {
            transform: translateX(20px);
        }

        /* Country Grid */
        .countries-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }

        .country-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            user-select: none;
            transition: all 0.15s ease;
        }

        .country-chip input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--blue);
        }

        .country-chip.is-checked {
            border-color: var(--blue);
            background: rgba(59, 130, 246, 0.12);
        }

        .save-btn {
            width: 100%;
            padding: 13px;
            font-size: 0.9rem;
            font-weight: 700;
            background: var(--blue);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }

        .save-btn:active {
            opacity: 0.9;
        }

        /* Lead Feed */
        .lead-card {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 10px;
        }

        .lead-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .lead-buyer {
            font-size: 0.9rem;
            font-weight: 700;
        }

        .lead-badge {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            background: #1e293b;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .lead-badge.status-accepted, .lead-badge.status-received {
            background: rgba(16, 185, 129, 0.15);
            color: var(--green);
        }

        .lead-badge.status-held {
            background: rgba(245, 158, 11, 0.15);
            color: var(--amber);
        }

        .lead-prod {
            font-size: 0.82rem;
            color: #e2e8f0;
            margin-bottom: 4px;
        }

        .lead-meta {
            font-size: 0.72rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed rgba(255, 255, 255, 0.08);
        }

        .wa-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #25d366;
            color: #000;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 6px;
            text-decoration: none;
        }

        .empty-feed {
            text-align: center;
            padding: 24px 10px;
            color: var(--text-muted);
            font-size: 0.82rem;
        }

        /* Sticky bottom nav bar for mobile */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid var(--card-border);
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            z-index: 100;
            max-width: 520px;
            margin: 0 auto;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            font-size: 0.7rem;
            color: var(--text-muted);
            text-decoration: none;
        }

        .nav-item.active {
            color: var(--blue);
            font-weight: 600;
        }

        .nav-icon {
            font-size: 1.15rem;
        }
    </style>
</head>
<body>

    <header>
        <div>
            <div class="brand-title">Bliss Pharmex AI</div>
            <div class="brand-sub">Mobile Lead Catcher</div>
        </div>
        <a href="/settings" class="desktop-link">Desktop View ↗</a>
    </header>

    @if (session('status'))
        <div class="toast" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <!-- Hero Status Card -->
    <section class="hero-card {{ $settings->enabled ? 'is-active' : 'is-paused' }}" id="heroCard">
        <div class="status-pill {{ $settings->enabled ? 'pill-active' : 'pill-paused' }}" id="statusPill">
            <span class="dot {{ $settings->enabled ? 'pulse' : '' }}" id="statusDot"></span>
            <span id="statusText">{{ $settings->enabled ? 'AI BOT RUNNING' : 'AI BOT PAUSED' }}</span>
        </div>

        <h1 class="hero-title" id="heroTitle">
            {{ $settings->enabled ? 'Auto-Buying Active' : 'Offline / Standby' }}
        </h1>
        <p class="hero-desc" id="heroDesc">
            {{ $settings->enabled 
                ? 'Monitoring IndiaMART for export inquiries from your selected target countries.' 
                : 'Bot is paused. Inquiries will be held safely until you toggle back on.' }}
        </p>

        <!-- Big Toggle Form -->
        <form method="POST" action="{{ route('mobile.toggle') }}" id="toggleForm">
            @csrf
            <input type="hidden" name="enabled" value="{{ $settings->enabled ? '0' : '1' }}" id="toggleInput">
            <button type="submit" class="power-btn {{ $settings->enabled ? 'btn-stop' : 'btn-start' }}" id="powerBtn">
                @if ($settings->enabled)
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Turn AI Bot OFF</span>
                @else
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Turn AI Bot ON</span>
                @endif
            </button>
        </form>
    </section>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-num">{{ $stats['today'] }}</div>
            <div class="stat-label">Today's Leads</div>
        </div>
        <div class="stat-box">
            <div class="stat-num" style="color: var(--green);">{{ count($selectedCountries) }}</div>
            <div class="stat-label">Export Markets</div>
        </div>
        <div class="stat-box">
            <div class="stat-num">{{ $stats['total'] }}</div>
            <div class="stat-label">Total Caught</div>
        </div>
    </div>

    <!-- Export & Filter Controls -->
    <form method="POST" action="{{ route('mobile.criteria') }}">
        @csrf
        <section class="section-box">
            <div class="section-header">
                <span class="section-title">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                    Export Filters
                </span>
            </div>

            <!-- Domestic Blocker Switch -->
            <div class="toggle-row">
                <div>
                    <div class="toggle-info-title">Reject Domestic (India)</div>
                    <div class="toggle-info-sub">Blocks PCD, GST, Indian phone & local inquiries</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="reject_domestic" value="1" {{ $rejectDomestic ? 'checked' : '' }}>
                    <span class="slider"></span>
                </label>
            </div>

            <!-- Target Export Countries -->
            <div style="font-size: 0.8rem; font-weight: 700; margin-bottom: 8px; color: var(--text-muted); text-transform: uppercase;">
                Allowed Export Markets
            </div>

            <div class="countries-grid">
                @foreach ($presetCountries as $iso => $data)
                    @php $checked = in_array($iso, $selectedCountries, true); @endphp
                    <label class="country-chip {{ $checked ? 'is-checked' : '' }}">
                        <input type="checkbox" name="country_filters[]" value="{{ $iso }}" {{ $checked ? 'checked' : '' }} onchange="this.closest('.country-chip').classList.toggle('is-checked', this.checked)">
                        <span>{{ $data['flag'] }} {{ $data['name'] }}</span>
                    </label>
                @endforeach
            </div>

            <button type="submit" class="save-btn">
                Save Filter Settings
            </button>
        </section>
    </form>

    <!-- Target Products Section -->
    <section class="section-box">
        <div class="section-header">
            <span class="section-title">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                Target Products & Medicines
            </span>
            <span style="font-size: 0.72rem; color: var(--text-muted);">{{ count($targetProducts ?? []) }} set</span>
        </div>

        <!-- Add Product Form -->
        <form method="POST" action="{{ route('settings.products.add') }}" style="display: flex; gap: 8px; margin-bottom: 12px;">
            @csrf
            <input
                type="text"
                name="product"
                placeholder="e.g. Paracetamol, Amoxicillin"
                required
                style="flex: 1; padding: 10px 12px; background: rgba(15, 23, 42, 0.7); border: 1px solid var(--card-border); border-radius: 10px; color: #fff; font-size: 0.85rem;"
            >
            <button type="submit" class="save-btn" style="width: auto; padding: 10px 16px; white-space: nowrap;">
                + Add
            </button>
        </form>

        <!-- Product Tags List -->
        @if (! empty($targetProducts))
            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                @foreach ($targetProducts as $prod)
                    <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); padding: 5px 10px; border-radius: 16px; font-size: 0.8rem;">
                        <span>💊 {{ $prod }}</span>
                        <form method="POST" action="{{ route('settings.products.remove') }}" style="display: inline; margin: 0;">
                            @csrf
                            <input type="hidden" name="product" value="{{ $prod }}">
                            <button type="submit" style="background: none; border: none; color: #ef4444; font-weight: bold; cursor: pointer; padding: 0 4px; font-size: 1rem; line-height: 1;">
                                ×
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @else
            <div style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.5; padding: 4px 0;">
                All 923 catalogue medicines allowed. Add specific medicines above if you want AI to only buy leads for certain products.
            </div>
        @endif
    </section>

    <!-- Recent Leads Feed -->
    <section class="section-box">
        <div class="section-header">
            <span class="section-title">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                Recent Caught Leads
            </span>
            <span style="font-size: 0.72rem; color: var(--text-muted);">Auto-refreshes</span>
        </div>

        @forelse ($recentLeads as $lead)
            <div class="lead-card">
                <div class="lead-header">
                    <div>
                        <div class="lead-buyer">{{ $lead->displayName() }}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                            {{ $lead->city() ? $lead->city() . ', ' : '' }}{{ $lead->countryName() ?? $lead->countryIso() ?? 'International' }}
                        </div>
                    </div>
                    <span class="lead-badge status-{{ strtolower($lead->status->value) }}">
                        {{ $lead->status->value }}
                    </span>
                </div>

                <div class="lead-prod">
                    💊 <strong>{{ $lead->productName() ?? 'Pharmaceutical inquiry' }}</strong>
                </div>

                <div class="lead-meta">
                    <span>🕒 {{ $lead->arrivedAtLocal()?->format('d M H:i') ?? 'Recent' }}</span>
                    @if ($lead->phone())
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $lead->phone()) }}" target="_blank" class="wa-btn">
                            WhatsApp Buyer ↗
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="empty-feed">
                No leads caught yet today.<br>
                Ensure AI Bot is <strong>ON</strong> to auto-catch new inquiries.
            </div>
        @endforelse
    </section>

    <!-- Sticky Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="/mobile" class="nav-item active">
            <span class="nav-icon">📱</span>
            <span>Remote</span>
        </a>
        <a href="/catalogue" class="nav-item">
            <span class="nav-icon">💊</span>
            <span>Catalogue</span>
        </a>
        <a href="/settings" class="nav-item">
            <span class="nav-icon">⚙️</span>
            <span>Settings</span>
        </a>
    </nav>

    <!-- Instant AJAX Toggle Script for Instant Mobile Response -->
    <script>
        document.getElementById('toggleForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('powerBtn');
            const input = document.getElementById('toggleInput');
            const nextVal = input.value === '1';

            btn.disabled = true;
            btn.style.opacity = '0.7';

            try {
                const res = await fetch("{{ route('mobile.toggle') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ enabled: nextVal })
                });

                const data = await res.json();
                if (data.success) {
                    const isNowOn = data.enabled;
                    input.value = isNowOn ? '0' : '1';

                    const card = document.getElementById('heroCard');
                    const pill = document.getElementById('statusPill');
                    const dot = document.getElementById('statusDot');
                    const text = document.getElementById('statusText');
                    const title = document.getElementById('heroTitle');
                    const desc = document.getElementById('heroDesc');

                    if (isNowOn) {
                        card.className = 'hero-card is-active';
                        pill.className = 'status-pill pill-active';
                        dot.className = 'dot pulse';
                        text.innerText = 'AI BOT RUNNING';
                        title.innerText = 'Auto-Buying Active';
                        desc.innerText = 'Monitoring IndiaMART for export inquiries from your selected target countries.';
                        btn.className = 'power-btn btn-stop';
                        btn.innerHTML = `<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><span>Turn AI Bot OFF</span>`;
                    } else {
                        card.className = 'hero-card is-paused';
                        pill.className = 'status-pill pill-paused';
                        dot.className = 'dot';
                        text.innerText = 'AI BOT PAUSED';
                        title.innerText = 'Offline / Standby';
                        desc.innerText = 'Bot is paused. Inquiries will be held safely until you toggle back on.';
                        btn.className = 'power-btn btn-start';
                        btn.innerHTML = `<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><span>Turn AI Bot ON</span>`;
                    }
                }
            } catch (err) {
                // If AJAX fails, fall back to standard form submit
                document.getElementById('toggleForm').submit();
            } finally {
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        });
    </script>
</body>
</html>

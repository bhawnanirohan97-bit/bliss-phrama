@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <div class="page-head">
        <div>
            <h1>Settings</h1>
            <p>Export targeting, automated lead filtering, and scoring policies.</p>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <h2>Bot Status</h2>
            <p class="muted" style="margin-top: 0;">
                Currently
                <strong style="color: {{ $settings->enabled ? 'var(--green)' : 'var(--amber)' }}">
                    {{ $settings->enabled ? 'on' : 'off' }}
                </strong>.
            </p>

            <form method="post" action="{{ route('settings.bot') }}">
                @csrf
                <input type="hidden" name="enabled" value="{{ $settings->enabled ? 0 : 1 }}">
                <button class="btn {{ $settings->enabled ? 'btn-danger' : 'btn-primary' }}" type="submit">
                    {{ $settings->enabled ? 'Switch bot off' : 'Switch bot on' }}
                </button>
            </form>

            <ul class="small muted" style="margin: 16px 0 0; padding-left: 18px; line-height: 1.8;">
                <li><strong>On:</strong> incoming leads are qualified, scored, and drafted automatically.</li>
                <li><strong>Off:</strong> incoming leads are stored and marked <em>held</em> until resumed.</li>
                <li>IndiaMART webhook is always acknowledged with HTTP 200 so pushes are never deactivated.</li>
            </ul>
        </div>

        <div class="card">
            <h2>IndiaMART push endpoint</h2>

            <p class="muted" style="margin-top: 0;">
                Paste this into the IndiaMART Lead Manager Push API settings.
            </p>

            <div class="field">
                <label for="webhook-path">Path</label>
                <input
                    id="webhook-path"
                    type="text"
                    readonly
                    value="/api/webhooks/indiamart/{{ config('services.indiamart.webhook_secret') }}"
                    onclick="this.select()"
                >
            </div>

            <p class="faint small" style="margin: 10px 0 0; line-height: 1.7;">
                Prefix this path with your public Cloudflare Tunnel URL when forwarding to your local machine.
            </p>

            @if (! config('services.indiamart.webhook_secret'))
                <div class="notice" style="margin-top: 14px;">
                    <strong>No webhook secret is configured.</strong>
                    Every push will be rejected with 404 until <code>INDIAMART_WEBHOOK_SECRET</code>
                    is set in <code>.env</code>.
                </div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-head">
            <h2>Export Targeting & Lead Buying Filters</h2>
            <span class="faint small">Enforces export-only criteria for AI evaluation & lead buying</span>
        </div>

        <div class="card">
            <form method="post" action="{{ route('settings.criteria') }}">
                @csrf

                <div style="margin-bottom: 24px;">
                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                        <input
                            type="checkbox"
                            name="reject_domestic"
                            value="1"
                            {{ $rejectDomestic ? 'checked' : '' }}
                            style="margin-top: 4px; accent-color: var(--accent);"
                        >
                        <div>
                            <strong>Reject Domestic Leads of India (Export Only)</strong>
                            <p class="faint small" style="margin: 2px 0 0;">
                                When enabled, enquiries originating in or destined for India (including Indian phone numbers, cities, and PCD franchise keywords) are rejected automatically so credits are never wasted on domestic leads.
                            </p>
                        </div>
                    </label>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                        Target Export Countries (Multi-Country Selection)
                    </label>
                    <p class="faint small" style="margin: 0 0 14px;">
                        Select which overseas destination markets you want to accept or auto-buy. If countries are selected, any enquiry outside these countries will be skipped. If no countries are selected, all non-Indian destinations are allowed.
                    </p>

                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; margin-bottom: 16px;">
                        @foreach ($presetCountries as $iso => $countryName)
                            <label style="display: flex; align-items: center; gap: 8px; padding: 6px 10px; background: var(--bg-elevated); border: 1px solid var(--border); border-radius: 6px; cursor: pointer;">
                                <input
                                    type="checkbox"
                                    name="country_filters[]"
                                    value="{{ $iso }}"
                                    {{ in_array($iso, $selectedCountries ?? [], true) ? 'checked' : '' }}
                                    style="accent-color: var(--accent);"
                                >
                                <span style="font-size: 13px;">{{ $countryName }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="field" style="margin-top: 12px;">
                        <label for="custom_countries">Additional Countries (comma-separated ISO codes or names)</label>
                        <input
                            id="custom_countries"
                            type="text"
                            name="custom_countries"
                            placeholder="e.g. CA, FR, IT, BR"
                            value="{{ implode(', ', array_diff($selectedCountries ?? [], array_keys($presetCountries))) }}"
                        >
                        <span class="faint small">Type additional country codes separated by commas if not listed above.</span>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                    <button class="btn btn-primary" type="submit">
                        Save Export Targeting Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="section">
        <div class="section-head">
            <h2>Target Products & Medicines Filter</h2>
            <span class="faint small">AI will only buy leads matching these products · Leave empty to allow all catalogue products</span>
        </div>

        <div class="card">
            <!-- Add Product Form -->
            <form method="post" action="{{ route('settings.products.add') }}" style="margin-bottom: 24px;">
                @csrf
                <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                    Add Target Medicine or Formulation
                </label>
                <div style="display: flex; gap: 10px; max-width: 650px;">
                    <input
                        type="text"
                        name="product"
                        placeholder="e.g. Paracetamol 500mg, Amoxicillin, Ciprofloxacin (comma-separated)"
                        required
                        style="flex: 1; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-card); color: inherit;"
                    >
                    <button type="submit" class="btn btn-primary" style="white-space: nowrap;">
                        + Add Product
                    </button>
                </div>
                <p class="faint small" style="margin: 6px 0 0;">
                    Tip: You can add multiple products at once by separating them with commas.
                </p>
            </form>

            <!-- Active Products List -->
            <div>
                <label style="font-weight: 600; display: block; margin-bottom: 10px;">
                    Active Target Products ({{ count($targetProducts) }})
                </label>

                @if (! empty($targetProducts))
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        @foreach ($targetProducts as $prod)
                            <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); padding: 6px 12px; border-radius: 20px; font-size: 0.88rem;">
                                <span>💊 <strong>{{ $prod }}</strong></span>
                                <form method="post" action="{{ route('settings.products.remove') }}" style="display: inline; margin: 0;">
                                    @csrf
                                    <input type="hidden" name="product" value="{{ $prod }}">
                                    <button type="submit" title="Remove {{ $prod }}" style="background: none; border: none; color: #ef4444; font-weight: bold; cursor: pointer; padding: 0 4px; font-size: 1.1rem; line-height: 1;">
                                        ×
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="notice" style="margin: 0;">
                        <strong>All Catalogue Medicines Allowed.</strong>
                        No individual product restrictions are configured, so the AI will evaluate and buy leads for any medicine in your catalogue. Add specific products above if you wish to restrict auto-buying to only certain formulations.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-head">
            <h2>Scoring policy</h2>
            <span class="faint small">Deterministic · no model can change a score</span>
        </div>

        <div class="card">
            <dl class="kv">
                <dt>Policy version</dt>
                <dd class="mono">{{ $policyVersion }}</dd>

                <dt>Matcher floor</dt>
                <dd>
                    <span class="mono">{{ number_format($matcherFloor, 2) }}</span>
                    <span class="faint small">— candidates below this score are treated as no match</span>
                </dd>

                <dt>Domestic Rejection</dt>
                <dd>
                    <span class="chip {{ $rejectDomestic ? 'chip-green' : 'chip-amber' }}">
                        {{ $rejectDomestic ? 'Active (Export-Only)' : 'Inactive' }}
                    </span>
                </dd>

                <dt>Target Country Filter</dt>
                <dd>
                    @if (! empty($selectedCountries))
                        <span class="mono">{{ count($selectedCountries) }} country/countries whitelisted</span>
                        <span class="faint small">({{ implode(', ', $selectedCountries) }})</span>
                    @else
                        <span class="faint small">All non-Indian export destinations allowed</span>
                    @endif
                </dd>

                <dt>Country rules</dt>
                <dd>
                    @if ($countryRules > 0)
                        <span class="mono">{{ number_format($countryRules) }}</span> countries classified
                    @else
                        <span class="chip chip-amber">None loaded</span>
                        <span class="faint small">
                            — every destination scores 0, so the maximum total is 80 and no lead can
                            reach the good or act-now band.
                        </span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>
@endsection

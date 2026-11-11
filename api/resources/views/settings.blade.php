@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <div class="page-head">
        <div>
            <h1>Settings</h1>
            <p>The two things that decide whether a lead is caught or missed.</p>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <h2>Bot</h2>
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
                <li><strong>On:</strong> a pushed lead is stored and marked <em>received</em>.</li>
                <li><strong>Off:</strong> a pushed lead is still stored, but marked <em>held</em>.</li>
                <li>IndiaMART is always answered with HTTP 200. Nothing is ever rejected, because
                    repeated failures cause the Push API to be deactivated.</li>
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
                A path alone is not enough — IndiaMART needs the full public URL, so this must be
                prefixed with the current tunnel address. Quick tunnels get a new hostname every
                time they restart; if leads stop arriving, compare the address here with the one
                saved in IndiaMART.
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

                <dt>Domestic (IN)</dt>
                <dd>
                    {{ $rejectDomestic ? 'Hard rejected (export-only)' : 'Scored normally' }}
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

            @if ($countryRules === 0)
                <div class="notice" style="margin-top: 14px;">
                    <strong>Destination scoring is switched off by missing data, not by a bug.</strong>
                    Import the country eligibility list with
                    <code>php artisan countries:import &lt;file.csv&gt;</code> once it is available.
                    Until then, scores understate every lead rather than guessing which countries are
                    safe to sell into.
                </div>
            @endif
        </div>
    </div>
@endsection

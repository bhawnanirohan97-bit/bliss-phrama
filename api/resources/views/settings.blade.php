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
                        <strong>All 923 Catalogue Medicines Allowed.</strong>
                        No individual product restrictions are configured, so the AI will evaluate and buy leads for any medicine in your catalogue. Add specific products above if you wish to restrict auto-buying to only certain formulations.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Full Catalogue Products List & Manager -->
    <div class="section">
        <div class="section-head" style="display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <h2>Complete Product Catalogue (All {{ $catalogueCounts['total'] }} Products)</h2>
                <span class="faint small">Search, approve, remove, block, or delete any formulation from the AI lead buyer</span>
            </div>
            <div style="display: flex; gap: 8px;">
                <span class="chip chip-green" id="approved-count-badge">✅ {{ $catalogueCounts['approved'] }} Approved</span>
                <span class="chip chip-amber" id="blocked-count-badge">⛔ {{ $catalogueCounts['blocked'] }} Blocked</span>
            </div>
        </div>

        <div class="card">
            <!-- Add New Product to Catalogue Form -->
            <details style="margin-bottom: 20px; background: rgba(15, 23, 42, 0.4); border: 1px dashed var(--border); border-radius: 8px; padding: 12px 16px;">
                <summary style="font-weight: 600; cursor: pointer; color: var(--accent);">
                    + Add a Brand New Product to Catalogue
                </summary>
                <form method="post" action="{{ route('catalogue.store') }}" style="margin-top: 14px; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: flex-end;">
                    @csrf
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Medicine Name *</label>
                        <input type="text" name="name" placeholder="e.g. Cefixime 200mg" required style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-card); color: inherit;">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Category</label>
                        <input type="text" name="category" placeholder="e.g. Antibiotics" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-card); color: inherit;">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Strength</label>
                        <input type="text" name="strength" placeholder="e.g. 200 mg" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-card); color: inherit;">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Dosage Form</label>
                        <select name="dosage_form" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-card); color: inherit;">
                            <option value="Tablet">Tablet</option>
                            <option value="Capsule">Capsule</option>
                            <option value="Injection">Injection</option>
                            <option value="Syrup / Liquid">Syrup / Liquid</option>
                            <option value="Ointment / Cream">Ointment / Cream</option>
                            <option value="Drops">Drops</option>
                            <option value="Inhaler">Inhaler</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="width: 100%; height: 38px;">Save to Catalogue</button>
                    </div>
                </form>
            </details>

            <!-- Live Search & Filter Bar -->
            <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <input
                        type="search"
                        id="catalogue-search"
                        placeholder="🔍 Type medicine name, category, or formulation to search instantly..."
                        oninput="filterCatalogueList()"
                        style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); color: inherit; font-size: 0.95rem;"
                    >
                </div>
                <div style="display: flex; gap: 6px;">
                    <button type="button" class="btn btn-sm btn-ghost is-active" id="btn-filter-all" onclick="setCatalogueFilter('all')" style="border-radius: 6px;">
                        All ({{ $catalogueCounts['total'] }})
                    </button>
                    <button type="button" class="btn btn-sm btn-ghost" id="btn-filter-approved" onclick="setCatalogueFilter('approved')" style="border-radius: 6px;">
                        Approved ({{ $catalogueCounts['approved'] }})
                    </button>
                    <button type="button" class="btn btn-sm btn-ghost" id="btn-filter-blocked" onclick="setCatalogueFilter('blocked')" style="border-radius: 6px;">
                        Blocked ({{ $catalogueCounts['blocked'] }})
                    </button>
                </div>
            </div>

            <!-- Scrollable Products Table Container -->
            <div style="max-height: 520px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                    <thead>
                        <tr style="position: sticky; top: 0; background: var(--bg-card); border-bottom: 2px solid var(--border); z-index: 10; text-align: left;">
                            <th style="padding: 10px 12px;">#</th>
                            <th style="padding: 10px 12px;">Medicine Name</th>
                            <th style="padding: 10px 12px;">Category</th>
                            <th style="padding: 10px 12px;">Dosage Form</th>
                            <th style="padding: 10px 12px;">Status</th>
                            <th style="padding: 10px 12px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="catalogue-tbody">
                        @foreach ($catalogueProducts as $idx => $prod)
                            @php $isApp = $prod->review_status->value === 'approved'; @endphp
                            <tr class="cat-row" data-name="{{ strtolower($prod->name) }}" data-cat="{{ strtolower($prod->category ?? '') }}" data-status="{{ $prod->review_status->value }}" style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 8px 12px; color: var(--text-muted); font-size: 0.78rem;">{{ $idx + 1 }}</td>
                                <td style="padding: 8px 12px;">
                                    <strong>{{ $prod->name }}</strong>
                                    @if ($prod->strength)
                                        <span class="mono faint small" style="margin-left: 4px;">({{ $prod->strength }})</span>
                                    @endif
                                </td>
                                <td style="padding: 8px 12px; color: var(--text-muted); font-size: 0.82rem;">
                                    {{ $prod->category ?? '—' }}
                                </td>
                                <td style="padding: 8px 12px; font-size: 0.82rem;">
                                    {{ $prod->dosage_form ?? '—' }}
                                </td>
                                <td style="padding: 8px 12px;">
                                    @if ($isApp)
                                        <span class="chip chip-green" style="font-size: 0.75rem;">Approved</span>
                                    @else
                                        <span class="chip chip-amber" style="font-size: 0.75rem;">Blocked</span>
                                    @endif
                                </td>
                                <td style="padding: 8px 12px; text-align: right;">
                                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                                        <!-- Add to strict target list -->
                                        <form method="post" action="{{ route('settings.products.add') }}" style="display: inline; margin: 0;">
                                            @csrf
                                            <input type="hidden" name="product" value="{{ $prod->name }}">
                                            <button type="submit" class="btn btn-sm btn-ghost" title="Add to strict target products list" style="padding: 3px 8px; font-size: 0.75rem;">
                                                + Target
                                            </button>
                                        </form>

                                        <!-- Toggle Status: Block or Approve -->
                                        <form method="post" action="{{ route('catalogue.toggle') }}" style="display: inline; margin: 0;">
                                            @csrf
                                            <input type="hidden" name="slug" value="{{ $prod->slug }}">
                                            @if ($isApp)
                                                <input type="hidden" name="action" value="block">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Block product so AI never buys leads for it" style="padding: 3px 8px; font-size: 0.75rem; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">
                                                    🚫 Remove / Block
                                                </button>
                                            @else
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-sm" title="Re-approve product for AI buying" style="padding: 3px 8px; font-size: 0.75rem; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                                                    ✅ Approve
                                                </button>
                                            @endif
                                        </form>

                                        <!-- Permanently Delete -->
                                        <form method="post" action="{{ route('catalogue.destroy') }}" onsubmit="return confirm('Are you sure you want to permanently delete {{ addslashes($prod->name) }} from the catalogue?');" style="display: inline; margin: 0;">
                                            @csrf
                                            <input type="hidden" name="slug" value="{{ $prod->slug }}">
                                            <button type="submit" class="btn btn-sm" title="Permanently delete from database" style="padding: 3px 6px; font-size: 0.75rem; background: none; border: none; color: var(--text-muted); cursor: pointer;">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 10px; display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--text-muted);">
                <span id="showing-count-label">Showing all {{ count($catalogueProducts) }} products</span>
                <span>Actions take effect immediately on incoming BuyLeads</span>
            </div>
        </div>
    </div>

    <!-- Live Fast Filtering Script -->
    <script>
        let currentFilterStatus = 'all';

        function setCatalogueFilter(status) {
            currentFilterStatus = status;
            document.querySelectorAll('#btn-filter-all, #btn-filter-approved, #btn-filter-blocked').forEach(b => {
                b.classList.remove('is-active');
            });
            const activeBtn = document.getElementById('btn-filter-' + status);
            if (activeBtn) activeBtn.classList.add('is-active');
            filterCatalogueList();
        }

        function filterCatalogueList() {
            const query = (document.getElementById('catalogue-search').value || '').toLowerCase().trim();
            const rows = document.querySelectorAll('.cat-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const cat = row.getAttribute('data-cat') || '';
                const status = row.getAttribute('data-status') || '';

                const matchesQuery = !query || name.includes(query) || cat.includes(query);
                const matchesStatus = currentFilterStatus === 'all' || status === currentFilterStatus;

                if (matchesQuery && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('showing-count-label').innerText = 'Showing ' + visibleCount + ' matching products';
        }
    </script>

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

@extends('layouts.app')

@section('title', 'Catalogue')

@section('content')
    <div class="page-head">
        <div>
            <h1>Catalogue</h1>
            <p>
                Matching only ever auto-selects SKUs you mark <strong>approved</strong>.
                Everything starts unreviewed, and unreviewed SKUs are invisible to the matcher.
            </p>
        </div>
    </div>

    <div class="grid grid-4">
        <div class="stat">
            <div class="value">{{ number_format($totals['matchable']) }}</div>
            <div class="label">Matchable SKUs</div>
        </div>
        <div class="stat is-good">
            <div class="value">{{ number_format($totals['approved']) }}</div>
            <div class="label">Approved</div>
        </div>
        <div class="stat">
            <div class="value">{{ number_format($totals['blocked']) }}</div>
            <div class="label">Blocked</div>
        </div>
        <div class="stat {{ $totals['unreviewed'] > 0 ? 'is-alert' : '' }}">
            <div class="value">{{ number_format($totals['unreviewed']) }}</div>
            <div class="label">Unreviewed</div>
        </div>
    </div>

    @if ($totals['approved'] === 0)
        <div class="notice" style="margin-top: 16px;">
            <strong>Nothing is approved, so no lead can match.</strong>
            Work category by category: approve the lines you genuinely export, and block the ones you
            will not touch (controlled medicines, unlicensed oncology, anything you cannot authorise).
            Blocking is not a delete — the SKU stays in the table.
        </div>
    @endif

    <details style="margin-top: 16px; background: rgba(15, 23, 42, 0.4); border: 1px dashed var(--border); border-radius: 8px; padding: 12px 16px;">
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

    <form class="filters" method="get" action="{{ route('catalogue.index') }}" style="margin-top: 16px;">
        <div class="field">
            <label for="q">Search</label>
            <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Name, category, strength">
        </div>

        <div class="field">
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                @foreach ($categorySummary as $row)
                    <option value="{{ $row->category }}" @selected($category === $row->category)>
                        {{ $row->category }} ({{ $row->total }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="review-status">Review status</label>
            <select id="review-status" name="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $case)
                    <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="btn-row">
            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn btn-ghost" href="{{ route('catalogue.index') }}">Reset</a>
        </div>
    </form>

    {{-- The bulk form deliberately does not wrap the table: each row already has
         its own small approve/block form, and forms cannot be nested. The row
         checkboxes opt in with the `form` attribute instead. --}}
    <form id="bulk-review" method="post" action="{{ route('catalogue.review') }}" class="btn-row" style="margin-bottom: 10px;">
        @csrf

        <label class="btn btn-ghost btn-sm" style="cursor: pointer;">
            <input type="checkbox" class="checkbox" data-select-all>
            Select page
        </label>

        <span class="faint small" data-selection-count>0 selected</span>

        <select name="status" aria-label="Set selected to" style="width: auto;">
            <option value="approved">Approve</option>
            <option value="blocked">Block</option>
            <option value="unreviewed">Reset to unreviewed</option>
        </select>

        <button class="btn btn-primary btn-sm" type="submit">Apply to selected</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width: 34px;"></th>
                    <th>Product</th>
                    <th class="nowrap">Category</th>
                    <th class="nowrap">Strength</th>
                    <th class="nowrap">Form</th>
                    <th class="nowrap">Review</th>
                    <th class="nowrap"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td>
                            <input
                                type="checkbox"
                                class="checkbox"
                                name="slugs[]"
                                value="{{ $product->slug }}"
                                form="bulk-review"
                                data-row-checkbox
                                aria-label="Select {{ $product->name }}"
                            >
                        </td>
                        <td>
                            {{ $product->name }}
                            @if ($product->packaging)
                                <span class="sub">{{ $product->packaging }}</span>
                            @endif
                        </td>
                        <td class="nowrap faint">{{ $product->category ?? '—' }}</td>
                        <td class="nowrap mono">{{ $product->strength ?? '—' }}</td>
                        <td class="nowrap">{{ $product->dosage_form ?? '—' }}</td>
                        <td class="nowrap">
                            <span class="chip chip-{{ $product->review_status->tone() }}">
                                {{ $product->review_status->label() }}
                            </span>
                        </td>
                        <td class="nowrap">
                            <div class="btn-row" style="align-items: center;">
                                <form method="post" action="{{ route('settings.products.add') }}" style="display: inline; margin: 0;">
                                    @csrf
                                    <input type="hidden" name="product" value="{{ $product->name }}">
                                    <button class="btn btn-sm btn-ghost" type="submit" title="Add to strict target list">+ Target</button>
                                </form>

                                @if (! $product->isApproved())
                                    <form method="post" action="{{ route('catalogue.review') }}">
                                        @csrf
                                        <input type="hidden" name="slugs[]" value="{{ $product->slug }}">
                                        <input type="hidden" name="status" value="approved">
                                        <button class="btn btn-sm" type="submit">Approve</button>
                                    </form>
                                @endif

                                @if (! $product->isBlocked())
                                    <form method="post" action="{{ route('catalogue.review') }}">
                                        @csrf
                                        <input type="hidden" name="slugs[]" value="{{ $product->slug }}">
                                        <input type="hidden" name="status" value="blocked">
                                        <button class="btn btn-sm btn-danger" type="submit" title="Remove / Block from AI Buying">Block</button>
                                    </form>
                                @endif

                                <form method="post" action="{{ route('catalogue.destroy') }}" onsubmit="return confirm('Permanently delete {{ addslashes($product->name) }}?');" style="display: inline; margin: 0;">
                                    @csrf
                                    <input type="hidden" name="slug" value="{{ $product->slug }}">
                                    <button class="btn btn-sm" type="submit" title="Permanently delete from database" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 2px 4px;">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty">
                                <strong>No SKUs match this view.</strong>
                                <a href="{{ route('catalogue.index') }}">Clear the filters</a> to see the whole catalogue.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('partials.pagination', ['paginator' => $products])

    <div class="section">
        <div class="section-head">
            <h2>Bulk by category</h2>
            <span class="faint small">Applies to every matchable SKU in that category, not just this page.</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="nowrap">Total</th>
                        <th class="nowrap">Approved</th>
                        <th class="nowrap">Blocked</th>
                        <th class="nowrap">Unreviewed</th>
                        <th class="nowrap"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categorySummary as $row)
                        @php
                            $unreviewedInCategory = $row->total - $row->approved - $row->blocked;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('catalogue.index', ['category' => $row->category]) }}">
                                    {{ $row->category }}
                                </a>
                            </td>
                            <td class="nowrap mono">{{ $row->total }}</td>
                            <td class="nowrap mono">{{ $row->approved }}</td>
                            <td class="nowrap mono">{{ $row->blocked }}</td>
                            <td class="nowrap mono">{{ $unreviewedInCategory }}</td>
                            <td class="nowrap">
                                <div class="btn-row">
                                    <form
                                        method="post"
                                        action="{{ route('catalogue.review') }}"
                                        onsubmit="return confirm('Approve all {{ $row->total }} products in {{ $row->category }}?');"
                                    >
                                        @csrf
                                        <input type="hidden" name="category" value="{{ $row->category }}">
                                        <input type="hidden" name="status" value="approved">
                                        <button class="btn btn-sm" type="submit">Approve all</button>
                                    </form>

                                    <form
                                        method="post"
                                        action="{{ route('catalogue.review') }}"
                                        onsubmit="return confirm('Block all {{ $row->total }} products in {{ $row->category }}?');"
                                    >
                                        @csrf
                                        <input type="hidden" name="category" value="{{ $row->category }}">
                                        <input type="hidden" name="status" value="blocked">
                                        <button class="btn btn-sm btn-danger" type="submit">Block all</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Keep the "N selected" label honest and make the select-all box reflect
        // the rows on this page only.
        (function () {
            const boxes = Array.from(document.querySelectorAll('[data-row-checkbox]'));
            const all = document.querySelector('[data-select-all]');
            const label = document.querySelector('[data-selection-count]');

            const refresh = function () {
                const selected = boxes.filter((box) => box.checked).length;
                if (label) label.textContent = selected + ' selected';
                if (all) {
                    all.checked = selected > 0 && selected === boxes.length;
                    all.indeterminate = selected > 0 && selected < boxes.length;
                }
            };

            if (all) {
                all.addEventListener('change', function () {
                    boxes.forEach((box) => { box.checked = all.checked; });
                    refresh();
                });
            }

            boxes.forEach((box) => box.addEventListener('change', refresh));
            refresh();
        })();
    </script>
@endpush

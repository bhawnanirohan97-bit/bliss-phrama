@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-head">
        <div>
            <h1>Dashboard</h1>
            <p>What arrived, what is waiting, and whether the bot is actually working.</p>
        </div>
        <div class="btn-row">
            <a class="btn" href="{{ route('leads.index') }}">Open inbox</a>
            <a class="btn btn-ghost" href="{{ route('catalogue.index') }}">Review catalogue</a>
        </div>
    </div>

    @if (! $botEnabled)
        <div class="notice" style="margin-bottom: 16px;">
            <strong>The bot is switched off.</strong>
            IndiaMART pushes are still accepted and every lead is stored, but each one is
            marked <span class="chip chip-amber">Held</span> instead of being processed.
            Nothing is lost while it is off.
        </div>
    @endif

    @if ($catalogueCounts['approved'] === 0)
        <div class="notice" style="margin-bottom: 16px;">
            <strong>No SKUs are approved yet, so the matcher will find nothing.</strong>
            Matching only runs against SKUs you have explicitly cleared for export.
            <a href="{{ route('catalogue.index', ['status' => 'unreviewed']) }}">Start reviewing the catalogue</a>.
        </div>
    @endif

    @if ($countryRules === 0)
        <div class="notice" style="margin-bottom: 16px;">
            <strong>No country rules are loaded.</strong>
            Every destination currently scores 0, so the highest possible total is 80 and no lead
            can reach the <em>good</em> or <em>act now</em> band. Import the country eligibility
            list to unlock destination scoring. Scores are honest, not guessed.
        </div>
    @endif

    <div class="grid grid-4">
        <div class="stat">
            <div class="value">{{ number_format($leadCounts['awaiting']) }}</div>
            <div class="label">Awaiting contact</div>
        </div>
        <div class="stat {{ $leadCounts['held'] > 0 ? 'is-alert' : '' }}">
            <div class="value">{{ number_format($leadCounts['held']) }}</div>
            <div class="label">Held while bot was off</div>
        </div>
        <div class="stat is-good">
            <div class="value">{{ number_format($leadCounts['contacted']) }}</div>
            <div class="label">Contacted</div>
        </div>
        <div class="stat">
            <div class="value">{{ number_format($leadCounts['today']) }}</div>
            <div class="label">Arrived today</div>
        </div>
    </div>

    <div class="section">
        <div class="section-head">
            <h2>Catalogue readiness</h2>
            <span class="faint small">{{ number_format($catalogueCounts['matchable']) }} matchable SKUs</span>
        </div>
        <div class="card">
            @php
                $matchable = max($catalogueCounts['matchable'], 1);
                $approvedPct = round($catalogueCounts['approved'] / $matchable * 100, 1);
                $blockedPct = round($catalogueCounts['blocked'] / $matchable * 100, 1);
            @endphp

            <div class="bar" role="img" aria-label="{{ $approvedPct }}% approved, {{ $blockedPct }}% blocked">
                <span class="approved" style="width: {{ $approvedPct }}%"></span>
                <span class="blocked" style="width: {{ $blockedPct }}%"></span>
                <span class="unreviewed" style="width: {{ 100 - $approvedPct - $blockedPct }}%"></span>
            </div>

            <div class="dl-inline" style="margin-top: 14px;">
                <span><strong>{{ number_format($catalogueCounts['approved']) }}</strong> approved</span>
                <span><strong>{{ number_format($catalogueCounts['blocked']) }}</strong> blocked</span>
                <span><strong>{{ number_format($catalogueCounts['unreviewed']) }}</strong> unreviewed</span>
                <span><strong>{{ number_format($catalogueCounts['services']) }}</strong> service listings excluded</span>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-head">
            <h2>Lead bands</h2>
            <span class="faint small">Latest assessment per lead</span>
        </div>

        <div class="card">
            <div class="btn-row">
                @foreach (\App\Enums\ScoreBand::cases() as $case)
                    <span class="chip chip-{{ $case->tone() }}">
                        {{ $case->label() }} · {{ number_format($bandCounts[$case->value]) }}
                    </span>
                @endforeach
            </div>

            <p class="faint small" style="margin: 14px 0 0;">
                @if ($leadCounts['unassessed'] > 0)
                    {{ number_format($leadCounts['unassessed']) }} actionable lead(s) have no assessment yet.
                    They are scored when the bot is on, or when you reassess from the lead screen.
                @else
                    Every actionable lead has an assessment. A band only changes when you reassess it
                    or the policy version changes.
                @endif
            </p>
        </div>
    </div>

    <div class="section">
        <div class="section-head">
            <h2>Latest leads</h2>
            <a class="small" href="{{ route('leads.index') }}">View all</a>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="nowrap">Arrived</th>
                        <th>Buyer</th>
                        <th class="nowrap">Country</th>
                        <th>Product</th>
                        <th class="nowrap">Type</th>
                        <th class="nowrap">Band</th>
                        <th class="nowrap">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentLeads as $lead)
                        <tr>
                            <td class="nowrap mono">
                                {{ $lead->arrivedAtLocal()?->format('d M H:i') ?? '—' }}
                                <span class="sub">{{ $lead->arrived_at?->diffForHumans() }}</span>
                            </td>
                            <td>
                                <a href="{{ route('leads.show', $lead) }}">{{ $lead->displayName() }}</a>
                                @if ($lead->buyerName() && $lead->companyName())
                                    <span class="sub">{{ $lead->buyerName() }}</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                {{ $lead->countryIso() ?? '—' }}
                                @if ($lead->countryName())
                                    <span class="sub">{{ $lead->countryName() }}</span>
                                @endif
                            </td>
                            <td>
                                {{ \Illuminate\Support\Str::limit($lead->productName() ?? '—', 60) }}
                                @if ($lead->message())
                                    <span class="sub">{{ \Illuminate\Support\Str::limit($lead->message(), 70) }}</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                <span class="chip chip-grey plain">{{ $lead->typeLabel() }}</span>
                            </td>
                            <td class="nowrap">
                                @if ($lead->latestAssessment)
                                    <span class="chip chip-{{ $lead->latestAssessment->band->tone() }}">
                                        {{ $lead->latestAssessment->band->label() }}
                                    </span>
                                    <span class="sub mono">{{ $lead->latestAssessment->overall_score }}/100</span>
                                @else
                                    <span class="faint small">Not scored</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                <span class="chip chip-{{ $lead->status->tone() }}">{{ $lead->status->label() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty">
                                    <strong>No leads have arrived yet.</strong>
                                    IndiaMART pushes will appear here as soon as the Push API is active
                                    and pointing at this app.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-head">
            <h2>Sources</h2>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Source</th>
                        <th class="nowrap">Status</th>
                        <th class="nowrap">Leads</th>
                        <th class="nowrap">Last success</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sources as $source)
                        <tr>
                            <td>{{ $source->name }}</td>
                            <td class="nowrap"><span class="chip chip-grey plain">{{ $source->integration_status }}</span></td>
                            <td class="nowrap mono">{{ number_format($source->leads_count) }}</td>
                            <td class="nowrap">
                                {{ $source->last_success_at?->timezone(config('pharmalead.display_timezone'))->format('d M Y H:i') ?? 'never' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="empty">
                                    <strong>No source has connected yet.</strong>
                                    The IndiaMART source is created automatically on the first push.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

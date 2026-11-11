@extends('layouts.app')

@section('title', 'Purchased Leads')

@section('content')
    <div class="page-head">
        <div>
            <h1>Purchased Leads</h1>
            <p>
                Every lead pushed by IndiaMART, newest first. Leads arrive whether the bot is on or off;
                this list is the record of what was caught.
            </p>
        </div>
    </div>

    <form class="filters" method="get" action="{{ route('leads.index') }}">
        <div class="field">
            <label for="q">Search</label>
            <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Product, buyer, company, country">
        </div>

        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $case)
                    <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="type">Type</label>
            <select id="type" name="type">
                <option value="">All types</option>
                @foreach ($types as $case)
                    <option value="{{ $case->value }}" @selected($type === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="sort">Sort</label>
            <select id="sort" name="sort">
                <option value="ranked" @selected($sort === 'ranked')>Ranked</option>
                <option value="newest" @selected($sort === 'newest')>Newest first</option>
            </select>
        </div>

        <div class="btn-row">
            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn btn-ghost" href="{{ route('leads.index') }}">Reset</a>
        </div>
    </form>

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
                    <th class="nowrap"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leads as $lead)
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
                            {{ \Illuminate\Support\Str::limit($lead->productName() ?? '—', 56) }}
                            @if ($lead->message())
                                <span class="sub">{{ \Illuminate\Support\Str::limit($lead->message(), 80) }}</span>
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
                        <td class="nowrap">
                            <a class="btn btn-sm btn-ghost" href="{{ route('leads.show', $lead) }}">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty">
                                <strong>No leads match this view.</strong>
                                @if ($search !== '' || $status !== '' || $type !== '')
                                    <a href="{{ route('leads.index') }}">Clear the filters</a> to see everything.
                                @else
                                    Nothing has been pushed by IndiaMART yet. Once the Push API is active,
                                    leads appear here within seconds of arrival.
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('partials.pagination', ['paginator' => $leads])
@endsection

@extends('layouts.app')

@section('title', 'Lead ' . $lead->external_lead_id)

@section('content')
    @php
        $whatsappDraft = collect($drafts)->first(fn ($draft) => $draft->channel->value === 'whatsapp' && $draft->passed());
        $emailDraft = collect($drafts)->first(fn ($draft) => $draft->channel->value === 'email' && $draft->passed());
    @endphp

    <div class="page-head">
        <div>
            <div class="small" style="margin-bottom: 6px;">
                <a href="{{ route('dashboard') }}">← Dashboard</a>
            </div>
            <h1>{{ $lead->displayName() }}</h1>
            <p>
                <span class="chip chip-grey plain">{{ $lead->typeLabel() }}</span>
                <span class="chip chip-{{ $lead->status->tone() }}">{{ $lead->status->label() }}</span>
                <span class="faint mono small" style="margin-left: 6px;">{{ $lead->external_lead_id }}</span>
            </p>
        </div>

        <div class="btn-row">
            @if ($lead->whatsappUrl($whatsappDraft?->body))
                <a class="btn btn-primary" href="{{ $lead->whatsappUrl($whatsappDraft?->body) }}" target="_blank" rel="noopener">WhatsApp</a>
            @endif
            @if ($lead->emailUrl($emailDraft?->subject, $emailDraft?->body))
                <a class="btn" href="{{ $lead->emailUrl($emailDraft?->subject, $emailDraft?->body) }}">Email</a>
            @endif
            @if ($lead->phone())
                <a class="btn btn-ghost" href="tel:{{ $lead->phone() }}">Call</a>
            @endif
        </div>
    </div>

    @if ($lead->status->value === 'held')
        <div class="notice" style="margin-bottom: 16px;">
            <strong>This lead arrived while the bot was off.</strong>
            It was stored, not dropped. Release it once you want it worked.
        </div>
    @endif

    <div class="detail-grid">
        <div class="stack">
            <div class="card">
                <h2>Assessment</h2>

                @if ($assessment)
                    @php
                        $components = [
                            ['Product match', $assessment->product_match, 35],
                            ['Commercial value', $assessment->commercial_value, 20],
                            ['Destination', $assessment->destination, 20],
                            ['Buyer and intent', $assessment->buyer_type_intent, 15],
                            ['Completeness', $assessment->completeness, 10],
                        ];
                    @endphp

                    <div class="btn-row" style="justify-content: space-between; align-items: baseline;">
                        <div>
                            <span class="chip chip-{{ $assessment->band->tone() }}">{{ $assessment->band->label() }}</span>
                            <span class="mono" style="font-size: 22px; font-weight: 600; margin-left: 8px;">
                                {{ $assessment->overall_score }}<span class="faint small">/100</span>
                            </span>
                        </div>
                        <span class="faint small">
                            policy {{ $assessment->policy_version }} ·
                            {{ $assessment->created_at?->timezone(config('pharmalead.display_timezone'))->format('d M Y, H:i') }} IST
                        </span>
                    </div>

                    <p class="muted" style="margin: 8px 0 4px;">{{ $assessment->band->action() }}</p>

                    <div class="components">
                        @foreach ($components as [$label, $awarded, $max])
                            <div class="component">
                                <span class="muted">{{ $label }}</span>
                                <div class="track"><span style="width: {{ $max > 0 ? round($awarded / $max * 100) : 0 }}%"></span></div>
                                <span class="mono small">{{ $awarded }}/{{ $max }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="btn-row" style="margin-top: 14px;">
                        <span class="chip chip-grey plain">{{ $assessment->match_state->label() }} match</span>
                        <span class="chip chip-grey plain">{{ $assessment->buyer_type->label() }}</span>
                        <span class="chip chip-grey plain">{{ $assessment->purchase_intent->label() }}</span>
                        <span class="chip chip-{{ $assessment->destination_class->tone() }}">
                            {{ $assessment->destination_country ?? 'No destination' }}
                        </span>
                        @if ($assessment->requested_quantity)
                            <span class="chip chip-grey plain">
                                {{ number_format($assessment->requested_quantity) }}
                                {{ $assessment->requested_quantity_unit === 'unknown' ? '' : $assessment->requested_quantity_unit }}
                                requested
                            </span>
                        @endif
                    </div>

                    @if ($assessment->product)
                        <p class="small" style="margin: 14px 0 0;">
                            Matched SKU:
                            <a href="{{ route('catalogue.index', ['q' => $assessment->product->name]) }}">
                                {{ $assessment->product->name }}
                            </a>
                        </p>
                    @endif

                    @if ($assessment->gates !== [])
                        <div class="notice" style="margin-top: 14px;">
                            <strong>Hard gates fired — never contact automatically.</strong>
                            <div class="btn-row" style="margin-top: 8px;">
                                @foreach ($assessment->gates as $gate)
                                    <span class="chip chip-red">{{ str_replace('_', ' ', $gate) }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($assessment->risk_flags !== [])
                        <div class="btn-row" style="margin-top: 14px;">
                            @foreach ($assessment->risk_flags as $flag)
                                <span class="chip chip-amber plain">{{ str_replace('_', ' ', $flag) }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($assessment->missing_information !== [])
                        <p class="faint small" style="margin: 14px 0 0;">
                            Missing:
                            {{ implode(', ', array_map(fn ($field) => str_replace('_', ' ', $field), $assessment->missing_information)) }}
                        </p>
                    @endif

                    <details style="margin-top: 14px;">
                        <summary class="small muted" style="cursor: pointer;">Why this score</summary>
                        <ul class="small muted" style="margin: 10px 0 0; padding-left: 18px; line-height: 1.9;">
                            @foreach ($assessment->reasons as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    </details>
                @else
                    <p class="muted" style="margin-top: 0;">
                        This lead has not been scored. Leads are scored at arrival when the bot is on,
                        when a held lead is released, or when you ask for a reassessment.
                    </p>
                @endif

                <form method="post" action="{{ route('leads.assess', $lead) }}" style="margin-top: 14px;">
                    @csrf
                    <button class="btn btn-sm" type="submit">Reassess now</button>
                </form>
            </div>

            <div class="card">
                <h2>Enquiry</h2>

                @if ($lead->productName())
                    <div style="font-size: 16px; font-weight: 600; margin-bottom: 10px;">
                        {{ $lead->productName() }}
                    </div>
                @endif

                @if ($lead->message())
                    <div class="quote">{{ $lead->message() }}</div>
                @else
                    <p class="muted" style="margin: 0;">No message was included with this lead.</p>
                @endif

                @if (is_string(data_get($lead->payload, 'QUERY_MCAT_NAME')) && data_get($lead->payload, 'QUERY_MCAT_NAME') !== '')
                    <p class="faint small" style="margin: 12px 0 0;">
                        IndiaMART category: {{ data_get($lead->payload, 'QUERY_MCAT_NAME') }}
                    </p>
                @endif
            </div>

            <div class="card">
                <h2>Reply drafts</h2>

                <p class="faint small" style="margin-top: 0;">
                    Drafts only — nothing is sent from this app. Copy one into WhatsApp, your mail client,
                    or use it as your call script. Every draft passed an automated fact check before it appeared here.
                </p>

                @if ($assessment === null)
                    <p class="muted" style="margin-bottom: 0;">
                        This lead has not been assessed, so no drafts exist. Reassess it to generate them.
                    </p>
                @elseif ($assessment->gates !== [])
                    <div class="notice">
                        <strong>No drafts are generated for a hard reject.</strong>
                        This lead fired
                        {{ implode(', ', array_map(fn ($gate) => str_replace('_', ' ', $gate), $assessment->gates)) }}.
                    </div>
                @elseif ($drafts === [])
                    <p class="muted">No drafts yet. Generate them from the current assessment.</p>
                @else
                    @foreach ($drafts as $draft)
                        <div class="draft">
                            <div class="draft-head">
                                <span class="chip chip-{{ $draft->channel->tone() }}">{{ $draft->channel->label() }}</span>
                                <span class="chip chip-{{ $draft->fact_check_status->tone() }}">{{ $draft->fact_check_status->label() }}</span>
                                <span class="faint small">{{ $draft->template_id }} v{{ $draft->template_version }}</span>
                            </div>

                            @if ($draft->passed())
                                @if ($draft->subject)
                                    <div class="draft-subject">
                                        <span class="muted small">Subject</span>
                                        <span id="draft-subject-{{ $draft->id }}">{{ $draft->subject }}</span>
                                        <button class="btn btn-sm" type="button" data-copy-target="draft-subject-{{ $draft->id }}">Copy subject</button>
                                    </div>
                                @endif

                                <pre class="draft-body" id="draft-body-{{ $draft->id }}">{{ $draft->body }}</pre>

                                <div class="btn-row">
                                    <button class="btn btn-sm" type="button" data-copy-target="draft-body-{{ $draft->id }}">
                                        Copy {{ $draft->channel->label() }}
                                    </button>
                                    <form method="post" action="{{ route('leads.drafts.regenerate', $lead) }}">
                                        @csrf
                                        <input type="hidden" name="channel" value="{{ $draft->channel->value }}">
                                        <button class="btn btn-sm btn-ghost" type="submit">Regenerate</button>
                                    </form>
                                </div>
                            @else
                                <div class="notice">
                                    <strong>Withheld: the fact check failed.</strong>
                                    The text below was not shown because it makes a claim that cannot be traced
                                    to the enquiry, the SKU record or the operator's own details.
                                    <ul>
                                        @foreach ($draft->violations() as $violation)
                                            <li class="mono small">{{ $violation }}</li>
                                        @endforeach
                                    </ul>
                                </div>

                                <form method="post" action="{{ route('leads.drafts.regenerate', $lead) }}">
                                    @csrf
                                    <input type="hidden" name="channel" value="{{ $draft->channel->value }}">
                                    <button class="btn btn-sm" type="submit">Regenerate this draft</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                @endif

                @if ($assessment !== null && $assessment->gates === [])
                    <form method="post" action="{{ route('leads.drafts.regenerate', $lead) }}" style="margin-top: 14px;">
                        @csrf
                        <button class="btn btn-sm" type="submit">Regenerate all three</button>
                    </form>

                    <p class="faint small" style="margin: 12px 0 0;">
                        Anchored to {{ $assessment?->product?->name ?? "the buyer's stated product requirement" }}.
                        The wording presents Bliss Pharmex as a third-party exporter: it never claims manufacture,
                        stock or certification.
                    </p>
                @endif
            </div>

            <div class="card">
                <h2>Approved catalogue matches</h2>

                @forelse ($matches as $match)
                    <div class="match">
                        <div>
                            <div class="score">{{ number_format($match->score * 100, 0) }}</div>
                            <div class="score-bar"><span style="width: {{ round($match->score * 100) }}%"></span></div>
                        </div>
                        <div style="min-width: 0;">
                            <div>
                                <a href="{{ route('catalogue.index', ['q' => $match->product->name]) }}">
                                    {{ $match->product->name }}
                                </a>
                            </div>
                            <div class="faint small">
                                {{ $match->product->category ?? 'Uncategorised' }}
                                @if ($match->product->strength)
                                    · {{ $match->product->strength }}
                                @endif
                                @if ($match->product->packaging)
                                    · {{ $match->product->packaging }}
                                @endif
                            </div>
                            <div style="margin-top: 6px;">
                                @if ($match->strengthMatched)
                                    <span class="chip chip-green">Strength matches</span>
                                @elseif ($match->strengthConflicts)
                                    <span class="chip chip-red">Strength differs</span>
                                @endif
                                @if ($match->formMatched)
                                    <span class="chip chip-teal">Form matches</span>
                                @endif
                                @foreach ($match->matchedTokens as $token)
                                    <span class="chip chip-grey plain">{{ $token }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="muted" style="margin: 0;">
                        No approved SKU matches this enquiry. That can mean the product is not in the
                        catalogue, the wording is too vague, or the right SKU has not been approved yet.
                    </p>
                @endforelse

                @if ($matches === [] && $unapprovedCandidates !== [])
                    <div style="margin-top: 16px; border-top: 1px solid var(--border); padding-top: 14px;">
                        <div class="small" style="margin-bottom: 10px; color: var(--amber);">
                            Closest SKUs that are not approved yet — approving one would let this lead match:
                        </div>

                        @foreach ($unapprovedCandidates as $candidate)
                            <div class="match">
                                <div>
                                    <div class="score">{{ number_format($candidate->score * 100, 0) }}</div>
                                    <div class="score-bar"><span style="width: {{ round($candidate->score * 100) }}%"></span></div>
                                </div>
                                <div style="min-width: 0;">
                                    <div>
                                        <a href="{{ route('catalogue.index', ['q' => $candidate->product->name]) }}">
                                            {{ $candidate->product->name }}
                                        </a>
                                    </div>
                                    <div class="faint small">
                                        {{ $candidate->product->category ?? 'Uncategorised' }}
                                        · <span class="chip chip-{{ $candidate->product->review_status->tone() }}">{{ $candidate->product->review_status->label() }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card">
                <details>
                    <summary class="small muted" style="cursor: pointer;">Original payload from IndiaMART</summary>
                    <pre class="mono small" style="margin: 12px 0 0; overflow-x: auto; color: var(--text-muted);">{{ json_encode($lead->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <h2>Buyer</h2>
                <dl class="kv">
                    <dt>Name</dt>
                    <dd>{{ $lead->buyerName() ?? '—' }}</dd>

                    <dt>Company</dt>
                    <dd>{{ $lead->companyName() ?? '—' }}</dd>

                    <dt>Email</dt>
                    <dd>
                        @if ($lead->email())
                            <a href="mailto:{{ $lead->email() }}">{{ $lead->email() }}</a>
                        @else
                            —
                        @endif
                    </dd>

                    <dt>Phone</dt>
                    <dd>{{ $lead->phone() ?? '—' }}</dd>

                    <dt>City</dt>
                    <dd>{{ $lead->city() ?? '—' }}</dd>

                    <dt>Country</dt>
                    <dd>
                        {{ $lead->countryIso() ?? '—' }}
                        @if ($lead->countryName())
                            <span class="faint">({{ $lead->countryName() }})</span>
                        @endif
                    </dd>

                    <dt>Arrived</dt>
                    <dd>
                        {{ $lead->arrivedAtLocal()?->format('d M Y, H:i') ?? '—' }}
                        <span class="faint small">IST</span>
                    </dd>
                </dl>
            </div>

            <div class="card">
                <h2>Update status</h2>

                <div class="btn-row">
                    @if ($lead->status->value !== 'contacted')
                        <form method="post" action="{{ route('leads.status', $lead) }}">
                            @csrf
                            <input type="hidden" name="status" value="contacted">
                            <button class="btn btn-primary" type="submit">Mark contacted</button>
                        </form>
                    @endif

                    @if ($lead->status->value === 'held')
                        <form method="post" action="{{ route('leads.status', $lead) }}">
                            @csrf
                            <input type="hidden" name="status" value="received">
                            <button class="btn" type="submit">Release Lead</button>
                        </form>
                    @endif

                    @if ($lead->status->value !== 'held')
                        <form method="post" action="{{ route('leads.status', $lead) }}">
                            @csrf
                            <input type="hidden" name="status" value="held">
                            <button class="btn btn-ghost" type="submit">Hold</button>
                        </form>
                    @endif
                </div>

                <p class="faint small" style="margin: 12px 0 0;">
                    Marking contacted only records that you made contact outside this app.
                    Nothing is sent to the buyer from here.
                </p>
            </div>

            <div class="card">
                <h2>Record</h2>
                <ul class="timeline">
                    <li>
                        <span class="dot"></span>
                        <div>
                            Pushed by {{ $lead->leadSource?->name ?? 'IndiaMART' }}
                            <div class="faint small">{{ $lead->arrivedAtLocal()?->format('d M Y, H:i') }} IST</div>
                        </div>
                    </li>
                    <li>
                        <span class="dot"></span>
                        <div>
                            Stored as {{ $lead->status->label() }}
                            <div class="faint small">{{ $lead->created_at?->timezone(config('pharmalead.display_timezone'))->format('d M Y, H:i') }} IST</div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-copy-target]');

            if (!button) {
                return;
            }

            const target = document.getElementById(button.getAttribute('data-copy-target'));

            if (!target) {
                return;
            }

            const original = button.textContent;

            navigator.clipboard.writeText(target.textContent.trim()).then(function () {
                button.textContent = 'Copied';
                setTimeout(function () {
                    button.textContent = original;
                }, 1500);
            });
        });
    </script>
@endpush

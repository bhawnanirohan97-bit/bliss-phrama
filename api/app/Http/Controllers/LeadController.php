<?php

namespace App\Http\Controllers;

use App\Enums\LeadQueryType;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadAssessment;
use App\Services\Drafting\DraftService;
use App\Services\Matching\ProductMatch;
use App\Services\Matching\ProductMatcher;
use App\Services\Policy\AssessmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(
        private readonly ProductMatcher $matcher,
        private readonly AssessmentService $assessments,
        private readonly DraftService $drafts,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();
        $search = $request->string('q')->trim()->toString();
        $sort = $request->string('sort')->toString() === 'newest' ? 'newest' : 'ranked';

        // One row per lead: the newest assessment. Assessments are append-only,
        // so "current" means the highest id for that lead.
        $latest = LeadAssessment::query()
            ->select('lead_id', 'band_rank', 'overall_score')
            ->whereIn('id', LeadAssessment::query()->selectRaw('max(id)')->groupBy('lead_id'));

        $leads = Lead::query()
            ->leftJoinSub($latest, 'assessment', fn ($join) => $join->on('assessment.lead_id', '=', 'leads.id'))
            ->select('leads.*')
            ->when($status !== '', fn ($query) => $query->where('leads.status', $status))
            // A hard reject is never work: it stays out of the inbox unless the
            // operator asks for it explicitly by status. Otherwise a domestic
            // or blocked-destination lead would clutter the queue forever.
            ->when($status === '', fn ($query) => $query->where('leads.status', '!=', LeadStatus::HardRejected->value))
            ->when($type !== '', fn ($query) => $query->where('query_type', $type))
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $like = '%'.$search.'%';

                $inner->where('payload->QUERY_PRODUCT_NAME', 'like', $like)
                    ->orWhere('payload->QUERY_MESSAGE', 'like', $like)
                    ->orWhere('payload->SENDER_NAME', 'like', $like)
                    ->orWhere('payload->SENDER_COMPANY', 'like', $like)
                    ->orWhere('payload->SENDER_COUNTRY_NAME', 'like', $like);
            }))
            ->when(
                $sort === 'ranked',
                // Band first, then total score, then oldest unworked lead first.
                // Unassessed leads sit below scored leads: they have no band yet.
                fn ($query) => $query
                    ->orderByRaw('coalesce(assessment.band_rank, -1) desc')
                    ->orderByDesc('assessment.overall_score')
                    ->orderBy('leads.arrived_at')
                    ->orderBy('leads.id'),
                fn ($query) => $query->latest('leads.arrived_at')->latest('leads.id'),
            )
            ->with('latestAssessment')
            ->paginate(25)
            ->withQueryString();

        return view('leads.index', [
            'leads' => $leads,
            'status' => $status,
            'type' => $type,
            'search' => $search,
            'sort' => $sort,
            'statuses' => LeadStatus::cases(),
            'types' => LeadQueryType::cases(),
        ]);
    }

    public function show(Lead $lead): View
    {
        $lead->loadMissing(['leadSource', 'latestAssessment.product', 'drafts']);

        $query = $lead->enquiryText();

        return view('leads.show', [
            'lead' => $lead,
            'assessment' => $lead->latestAssessment,
            'matches' => $query === '' ? [] : $this->matcher->match($query, limit: 5),
            'unapprovedCandidates' => $query === '' ? [] : $this->unapprovedCandidates($query),
            'drafts' => $this->drafts->latestForLead($lead),
        ]);
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:received,held,contacted,allocated,hard_rejected'],
        ]);

        $lead->update(['status' => LeadStatus::from($validated['status'])]);

        // Releasing a held lead back into the inbox is the moment it becomes
        // work: score it if it was never scored while the bot was off.
        if ($lead->status === LeadStatus::Received && $lead->latestAssessment === null && $lead->queryType()?->isActionable()) {
            $this->assessments->assess($lead);
        }

        return back()->with('status', "Lead {$lead->external_lead_id} marked as {$validated['status']}.");
    }

    /**
     * Explicit reassessment. The band is a stored decision, so it only changes
     * when the operator asks: usually right after approving the SKUs that make
     * an existing lead matchable.
     */
    public function assess(Lead $lead): RedirectResponse
    {
        $assessment = $this->assessments->assess($lead);

        return back()->with(
            'status',
            "Lead {$lead->external_lead_id} reassessed at {$assessment->overall_score}/100 ({$assessment->band->label()}).",
        );
    }

    /**
     * Show what would have matched if the SKU had been approved. This is the
     * fastest route from "no match" to "the operator knows which SKU to review".
     *
     * @return list<ProductMatch>
     */
    private function unapprovedCandidates(string $query): array
    {
        return array_values(array_filter(
            $this->matcher->match($query, limit: 5, minimumScore: 0.30, approvedOnly: false),
            fn (ProductMatch $match) => ! $match->product->isApproved(),
        ));
    }
}

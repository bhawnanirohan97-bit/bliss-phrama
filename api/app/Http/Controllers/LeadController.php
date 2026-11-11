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

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('dashboard');
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

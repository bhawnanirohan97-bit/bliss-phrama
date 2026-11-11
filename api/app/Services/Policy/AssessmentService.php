<?php

namespace App\Services\Policy;

use App\Enums\LeadStatus;
use App\Events\LeadAssessed;
use App\Models\Lead;
use App\Models\LeadAssessment;
use App\Services\Matching\ProductMatcher;
use App\Services\Qualification\InquiryExtractor;

/**
 * Runs the deterministic pipeline for one lead and stores the result.
 *
 * Assessments are append-only (architecture principle P5): every call inserts a
 * new row, so "what did the system think when it alerted me" is always
 * answerable even after the catalogue or the country rules change.
 */
final class AssessmentService
{
    public function __construct(
        private readonly InquiryExtractor $extractor,
        private readonly ProductMatcher $matcher,
        private readonly Assessor $assessor,
    ) {}

    public function assess(Lead $lead): LeadAssessment
    {
        $qualification = $this->extractor->extract($lead);
        $matches = $this->matcher->match($lead->enquiryText(), limit: 5);
        $assessment = $this->assessor->assess($lead, $qualification, $matches);

        $stored = LeadAssessment::create($assessment->toAttributes($lead->id));

        // The gate decision is also a state change: a hard-rejected lead leaves
        // the working list. A later reassessment that clears the gates puts it
        // back, which is why this is a transition and not a one-way tombstone.
        if ($assessment->hardRejected() && in_array($lead->status, [LeadStatus::Received, LeadStatus::Allocated], true)) {
            $lead->update(['status' => LeadStatus::HardRejected]);
        } elseif (! $assessment->hardRejected() && $lead->status === LeadStatus::HardRejected) {
            $lead->update(['status' => LeadStatus::Received]);
        }

        // Drafting is a listener, not an inline call: a failure there must not
        // cost the assessment, and the webhook must stay fast.
        LeadAssessed::dispatch($lead, $stored);

        return $stored;
    }

    /**
     * Reuse an existing assessment if one was already stored for this lead
     * without asking the policy to run again. Used by read-only screens.
     */
    public function assessIfMissing(Lead $lead): ?LeadAssessment
    {
        return $lead->latestAssessment ?? $this->assess($lead);
    }
}

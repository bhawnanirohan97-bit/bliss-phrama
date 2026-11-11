<?php

namespace App\Listeners;

use App\Events\LeadAssessed;
use App\Services\Drafting\DraftService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes the three outreach drafts for a freshly assessed lead.
 *
 * A drafting failure must not remove the assessment: the score and band are
 * still valid, and the operator can regenerate the drafts by hand.
 */
class GenerateLeadDrafts
{
    public function __construct(private readonly DraftService $drafts) {}

    public function handle(LeadAssessed $event): void
    {
        try {
            $this->drafts->generateForAssessment($event->lead, $event->assessment);
        } catch (Throwable $exception) {
            Log::error('Draft generation failed; the assessment is unaffected.', [
                'lead_id' => $event->lead->id,
                'assessment_id' => $event->assessment->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}

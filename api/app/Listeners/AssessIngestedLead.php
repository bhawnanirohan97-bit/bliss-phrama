<?php

namespace App\Listeners;

use App\Events\LeadIngested;
use App\Services\Policy\AssessmentService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Scores a newly stored lead.
 *
 * Scoring must never take the webhook down. A failure here leaves the lead
 * stored and unassessed, which is recoverable; a 500 response to IndiaMART is
 * not, because repeated failures deactivate the Push API.
 */
class AssessIngestedLead
{
    public function __construct(private readonly AssessmentService $assessments) {}

    public function handle(LeadIngested $event): void
    {
        try {
            $this->assessments->assess($event->lead);
        } catch (Throwable $exception) {
            Log::error('Lead assessment failed; lead remains stored and unassessed.', [
                'lead_id' => $event->lead->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\LeadAssessment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A lead has a new assessment in force.
 *
 * Drafting listens to this rather than being called by the webhook, so the
 * ingestion path stays fast and a drafting failure can never take a push down.
 */
class LeadAssessed
{
    use Dispatchable;

    public function __construct(
        public Lead $lead,
        public LeadAssessment $assessment,
    ) {}
}

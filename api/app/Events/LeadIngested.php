<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A lead has been persisted and is ready to be judged.
 *
 * The webhook fires this only for a genuinely new lead: a replayed push is
 * idempotent and must not produce a second assessment.
 */
class LeadIngested
{
    use Dispatchable;

    public function __construct(public Lead $lead) {}
}

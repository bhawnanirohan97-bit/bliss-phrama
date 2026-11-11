<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Services\Policy\AssessmentService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class AssessLeadsCommand extends Command
{
    protected $signature = 'leads:assess
                            {--all : Reassess every actionable lead, not only the unassessed}
                            {--limit=0 : Stop after this many leads (0 means no limit)}';

    protected $description = 'Run the deterministic scoring policy over stored leads';

    public function handle(AssessmentService $assessments): int
    {
        $limit = (int) $this->option('limit');

        $query = Lead::query()
            ->actionable()
            ->whereIn('status', [LeadStatus::Received->value, LeadStatus::Allocated->value, LeadStatus::Contacted->value])
            ->when(! $this->option('all'), fn (Builder $builder) => $builder->whereDoesntHave('assessments'))
            ->orderBy('arrived_at')
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $leads = $query->get();

        if ($leads->isEmpty()) {
            $this->info('Nothing to assess.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($leads as $lead) {
            $assessment = $assessments->assess($lead);

            $rows[] = [
                $lead->external_lead_id,
                $lead->displayName(),
                $assessment->band->label(),
                $assessment->overall_score,
                $assessment->product?->name ?? '—',
            ];
        }

        $this->table(['Query ID', 'Buyer', 'Band', 'Score', 'Matched SKU'], $rows);
        $this->info($leads->count().' lead(s) assessed.');

        return self::SUCCESS;
    }
}

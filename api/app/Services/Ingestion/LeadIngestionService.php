<?php

namespace App\Services\Ingestion;

use App\Enums\LeadStatus;
use App\Events\LeadIngested;
use App\Models\AiBotSetting;
use App\Models\Lead;
use App\Models\LeadSource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

class LeadIngestionService
{
    /**
     * Persist a lead pushed by a source, idempotently.
     *
     * A lead is always stored, even when the bot is switched off. Deferring the
     * work is safe; discarding the lead is not, because it may already have been
     * paid for and the source will not resend it as new.
     *
     * @param  array<string, mixed>  $payload
     */
    public function ingest(LeadSource $source, array $payload): Lead
    {
        $externalId = (string) ($payload['UNIQUE_QUERY_ID'] ?? '');
        $enabled = AiBotSetting::current()->enabled;

        $lead = Lead::updateOrCreate(
            [
                'lead_source_id' => $source->id,
                'external_lead_id' => $externalId,
            ],
            [
                'query_type' => $payload['QUERY_TYPE'] ?? null,
                'status' => $enabled ? LeadStatus::Received : LeadStatus::Held,
                'arrived_at' => $this->parseQueryTime($payload['QUERY_TIME'] ?? null),
                'payload' => $payload,
            ],
        );

        // Only a genuinely new, actionable lead is judged. A replayed push is
        // idempotent, and a catalogue view or phone call has nothing to answer.
        if ($lead->wasRecentlyCreated && $enabled && in_array($lead->query_type, Lead::ACTIONABLE_TYPES, true)) {
            LeadIngested::dispatch($lead);
        }

        return $lead;
    }

    /**
     * IndiaMART sends QUERY_TIME as a wall clock string in Asia/Kolkata.
     * Convert to UTC so arrival-to-contact timing is comparable.
     */
    public function parseQueryTime(?string $value): ?CarbonInterface
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'Asia/Kolkata')->utc();
        } catch (Throwable) {
            return null;
        }
    }
}

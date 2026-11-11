<?php

namespace App\Services\Drafting;

use App\Enums\DraftChannel;
use App\Enums\FactCheckStatus;
use App\Models\Draft;
use App\Models\Lead;
use App\Models\LeadAssessment;

/**
 * Produces the three outreach drafts for one lead.
 *
 * Generation is deterministic and append-only: the same lead and assessment
 * always produce the same text, and regenerating writes new rows instead of
 * editing the old ones. A lead that fired any hard gate gets no drafts at all,
 * because there is no version of the message that should be sent.
 */
final class DraftService
{
    public function __construct(
        private readonly DraftTemplates $templates,
        private readonly DraftFactChecker $checker,
    ) {}

    /**
     * Used by the LeadAssessed listener. Skips a set that already exists for
     * this exact assessment, so re-running the listener is harmless.
     *
     * @return list<Draft>
     */
    public function generateForAssessment(Lead $lead, LeadAssessment $assessment): array
    {
        if ($assessment->gates !== []) {
            return [];
        }

        $alreadyDrafted = Draft::query()
            ->where('lead_assessment_id', $assessment->id)
            ->exists();

        return $alreadyDrafted ? [] : $this->create($lead, $assessment, null);
    }

    /**
     * Rebuild from the current templates and SKU record.
     *
     * @return list<Draft>
     */
    public function regenerate(Lead $lead, ?DraftChannel $channel = null): array
    {
        $assessment = $lead->latestAssessment;

        if ($assessment === null || $assessment->gates !== []) {
            return [];
        }

        return $this->create($lead, $assessment, $channel);
    }

    /**
     * The newest draft per channel, in channel order, for the lead screen.
     *
     * @return list<Draft>
     */
    public function latestForLead(Lead $lead): array
    {
        $latest = [];

        foreach ($lead->drafts()->orderBy('id')->get() as $draft) {
            $latest[$draft->channel->value] = $draft;
        }

        $ordered = [];

        foreach (DraftChannel::cases() as $channel) {
            if (isset($latest[$channel->value])) {
                $ordered[] = $latest[$channel->value];
            }
        }

        return $ordered;
    }

    /**
     * @return list<Draft>
     */
    private function create(Lead $lead, LeadAssessment $assessment, ?DraftChannel $only): array
    {
        $context = DraftContext::from($lead, $assessment);
        $channels = $only === null ? DraftChannel::cases() : [$only];

        $created = [];

        foreach ($channels as $channel) {
            $rendered = $this->templates->render($channel, $context);
            $result = $this->checker->check($channel, $rendered->subject, $rendered->body, $context);

            $created[] = Draft::create([
                'lead_id' => $lead->id,
                'lead_assessment_id' => $assessment->id,
                'channel' => $channel->value,
                'language' => 'en',
                'template_id' => $rendered->templateId,
                'template_version' => $rendered->templateVersion,
                'subject' => $rendered->subject,
                'body' => $rendered->body,
                'fact_check_status' => $result->passed
                    ? FactCheckStatus::Passed->value
                    : FactCheckStatus::Failed->value,
                'fact_check_detail' => $result->toDetail(),
                'provider' => 'template',
                'model_version' => null,
            ]);
        }

        return $created;
    }
}

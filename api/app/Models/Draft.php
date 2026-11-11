<?php

namespace App\Models;

use App\Enums\DraftChannel;
use App\Enums\FactCheckStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One generated outreach message.
 *
 * Rows are immutable in substance: regenerating appends a new row, and the
 * newest row per channel is the one the lead screen shows.
 */
class Draft extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'lead_assessment_id',
        'channel',
        'language',
        'template_id',
        'template_version',
        'subject',
        'body',
        'fact_check_status',
        'fact_check_detail',
        'provider',
        'model_version',
        'approved_by',
        'approved_at',
        'sent_by',
        'sent_at',
        'channel_ref',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => DraftChannel::class,
            'fact_check_status' => FactCheckStatus::class,
            'fact_check_detail' => 'array',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<LeadAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(LeadAssessment::class, 'lead_assessment_id');
    }

    public function passed(): bool
    {
        return $this->fact_check_status === FactCheckStatus::Passed;
    }

    /**
     * The failed rules, in the order the checker found them.
     *
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = $this->fact_check_detail['violations'] ?? [];

        return is_array($violations) ? array_values(array_filter($violations, 'is_string')) : [];
    }
}

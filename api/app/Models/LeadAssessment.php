<?php

namespace App\Models;

use App\Enums\BuyerType;
use App\Enums\DestinationClass;
use App\Enums\MatchState;
use App\Enums\PurchaseIntent;
use App\Enums\ScoreBand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable record of a scoring decision.
 *
 * A reassessment inserts a new row; nothing here is ever updated. The latest
 * row by id is the current assessment for the lead.
 */
class LeadAssessment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'policy_version',
        'sku_id',
        'match_state',
        'match_score',
        'product_match',
        'commercial_value',
        'destination',
        'buyer_type_intent',
        'completeness',
        'overall_score',
        'band',
        'band_rank',
        'destination_country',
        'destination_class',
        'requested_quantity',
        'requested_quantity_unit',
        'buyer_type',
        'purchase_intent',
        'gates',
        'reasons',
        'missing_information',
        'risk_flags',
        'candidates',
        'explanation',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_state' => MatchState::class,
            'match_score' => 'float',
            'band' => ScoreBand::class,
            'destination_class' => DestinationClass::class,
            'buyer_type' => BuyerType::class,
            'purchase_intent' => PurchaseIntent::class,
            'product_match' => 'integer',
            'commercial_value' => 'integer',
            'destination' => 'integer',
            'buyer_type_intent' => 'integer',
            'completeness' => 'integer',
            'overall_score' => 'integer',
            'band_rank' => 'integer',
            'requested_quantity' => 'integer',
            'gates' => 'array',
            'reasons' => 'array',
            'missing_information' => 'array',
            'risk_flags' => 'array',
            'candidates' => 'array',
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'sku_id');
    }

    public function firedGate(string $gate): bool
    {
        return in_array($gate, $this->gates ?? [], true);
    }
}

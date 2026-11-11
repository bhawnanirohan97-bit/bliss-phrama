<?php

namespace App\Models;

use App\Enums\LeadQueryType;
use App\Enums\LeadStatus;
use Carbon\CarbonInterface;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /**
     * Lead types that represent a buyer actually looking to buy something.
     *
     * @var list<string>
     */
    public const ACTIONABLE_TYPES = ['B', 'W', 'WA'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_source_id',
        'external_lead_id',
        'query_type',
        'status',
        'arrived_at',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'arrived_at' => 'datetime',
            'status' => LeadStatus::class,
        ];
    }

    /**
     * @return BelongsTo<LeadSource, $this>
     */
    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    /**
     * Every assessment ever run for this lead, newest last.
     *
     * @return HasMany<LeadAssessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(LeadAssessment::class);
    }

    /**
     * The assessment currently in force. Reassessment appends, so this is the
     * newest row rather than a mutable pointer.
     *
     * @return HasOne<LeadAssessment, $this>
     */
    public function latestAssessment(): HasOne
    {
        return $this->hasOne(LeadAssessment::class)->latestOfMany();
    }

    /**
     * Every draft ever generated for this lead, newest last.
     *
     * @return HasMany<Draft, $this>
     */
    public function drafts(): HasMany
    {
        return $this->hasMany(Draft::class);
    }

    /**
     * Enquiries with a product to match and a buyer to answer.
     *
     * @param  Builder<Lead>  $query
     */
    public function scopeActionable(Builder $query): void
    {
        $query->whereIn('query_type', self::ACTIONABLE_TYPES);
    }

    /**
     * @param  Builder<Lead>  $query
     */
    public function scopeAwaitingWork(Builder $query): void
    {
        $query->whereIn('status', [LeadStatus::Received->value, LeadStatus::Held->value]);
    }

    public function queryType(): ?LeadQueryType
    {
        return LeadQueryType::tryFrom((string) $this->query_type);
    }

    public function typeLabel(): string
    {
        return $this->queryType()?->label() ?? 'Unknown';
    }

    /**
     * Arrival time in the operator's zone, for display only.
     */
    public function arrivedAtLocal(): ?CarbonInterface
    {
        return $this->arrived_at?->copy()->timezone(config('pharmalead.display_timezone'));
    }

    /**
     * Everything the buyer typed that might name a product.
     */
    public function enquiryText(): string
    {
        return trim(implode(' ', array_filter([
            $this->payloadValue('QUERY_PRODUCT_NAME'),
            $this->payloadValue('QUERY_MESSAGE'),
            $this->payloadValue('QUERY_MCAT_NAME'),
        ], fn (?string $part) => $part !== null && $part !== '')));
    }

    public function productName(): ?string
    {
        return $this->payloadValue('QUERY_PRODUCT_NAME');
    }

    public function message(): ?string
    {
        return $this->payloadValue('QUERY_MESSAGE');
    }

    public function buyerName(): ?string
    {
        return $this->payloadValue('SENDER_NAME');
    }

    public function companyName(): ?string
    {
        return $this->payloadValue('SENDER_COMPANY');
    }

    /**
     * Falls back to the buyer's name when no company was supplied, so a lead
     * is never listed as an anonymous row.
     */
    public function displayName(): string
    {
        return $this->companyName() ?? $this->buyerName() ?? 'Unknown buyer';
    }

    public function countryIso(): ?string
    {
        $iso = $this->payloadValue('SENDER_COUNTRY_ISO');

        return $iso === null ? null : Str::upper($iso);
    }

    public function countryName(): ?string
    {
        return $this->payloadValue('SENDER_COUNTRY_NAME');
    }

    public function city(): ?string
    {
        return $this->payloadValue('SENDER_CITY');
    }

    public function email(): ?string
    {
        return $this->payloadValue('SENDER_EMAIL');
    }

    public function phone(): ?string
    {
        return $this->payloadValue('SENDER_MOBILE')
            ?? $this->payloadValue('SENDER_ALTERNATE_MOBILE');
    }

    /**
     * A wa.me link, optionally pre-filled with the drafted WhatsApp message.
     *
     * The operator still presses send in WhatsApp; this only saves retyping.
     */
    public function whatsappUrl(?string $text = null): ?string
    {
        $phone = $this->phone();

        if ($phone === null) {
            return null;
        }

        // wa.me wants digits only, including the country code.
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        $url = 'https://wa.me/'.$digits;

        return $text === null || trim($text) === ''
            ? $url
            : $url.'?text='.rawurlencode($text);
    }

    /**
     * A mailto link, optionally pre-filled with the drafted subject and body.
     */
    public function emailUrl(?string $subject = null, ?string $body = null): ?string
    {
        $email = $this->email();

        if ($email === null) {
            return null;
        }

        $query = array_filter([
            'subject' => $subject,
            'body' => $body,
        ], fn (?string $value) => $value !== null && trim($value) !== '');

        return 'mailto:'.$email.($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    private function payloadValue(string $key): ?string
    {
        $value = $this->payload[$key] ?? null;

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

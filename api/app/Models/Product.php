<?php

namespace App\Models;

use App\Enums\ProductReviewStatus;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'category',
        'brand',
        'generic_name',
        'strength',
        'dosage_form',
        'packaging',
        'moq',
        'product_type',
        'is_service',
        'review_status',
        'reviewed_at',
        'principal',
        'authorisation_status',
        'search_text',
        'source_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'is_service' => 'boolean',
            'review_status' => ProductReviewStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Only the SKUs a buyer enquiry can legitimately be matched against.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeMatchable(Builder $query): void
    {
        $query->where('is_service', false)
            ->where('product_type', '!=', ProductType::Service->value);
    }

    /**
     * SKUs the operator has explicitly cleared for export.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('review_status', ProductReviewStatus::Approved->value);
    }

    public function isApproved(): bool
    {
        return $this->review_status === ProductReviewStatus::Approved;
    }

    public function isBlocked(): bool
    {
        return $this->review_status === ProductReviewStatus::Blocked;
    }

    public function approve(): void
    {
        $this->forceFill([
            'review_status' => ProductReviewStatus::Approved,
            'reviewed_at' => now(),
        ])->save();
    }

    public function block(): void
    {
        $this->forceFill([
            'review_status' => ProductReviewStatus::Blocked,
            'reviewed_at' => now(),
        ])->save();
    }
}

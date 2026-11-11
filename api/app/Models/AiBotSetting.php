<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiBotSetting extends Model
{
    use HasFactory;

    /**
     * The single settings row that governs whether the bot processes leads.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([], ['enabled' => false]);
    }

    protected $fillable = [
        'enabled',
        'criteria',
        'product_filters',
        'country_filters',
        'buyer_type_filters',
        'min_quantity',
        'max_quantity',
        'min_order_value',
        'max_order_value',
        'keywords_include',
        'keywords_exclude',
        'allowed_dosage_forms',
        'only_new_buyers',
        'skip_personal',
        'skip_job_vendor',
        'auto_draft',
        'auto_prioritize',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'criteria' => 'array',
        'product_filters' => 'array',
        'country_filters' => 'array',
        'buyer_type_filters' => 'array',
        'keywords_include' => 'array',
        'keywords_exclude' => 'array',
        'allowed_dosage_forms' => 'array',
        'only_new_buyers' => 'boolean',
        'skip_personal' => 'boolean',
        'skip_job_vendor' => 'boolean',
        'auto_draft' => 'boolean',
        'auto_prioritize' => 'boolean',
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'min_order_value' => 'decimal:2',
        'max_order_value' => 'decimal:2',
    ];
}

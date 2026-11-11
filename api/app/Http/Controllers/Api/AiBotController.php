<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiBotSetting;
use Illuminate\Http\Request;

class AiBotController extends Controller
{
    public function index()
    {
        $settings = AiBotSetting::first();
        if (! $settings) {
            $settings = AiBotSetting::create(['enabled' => false]);
        }

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'boolean',
            'criteria' => 'nullable|array',
            'product_filters' => 'nullable|array',
            'country_filters' => 'nullable|array',
            'buyer_type_filters' => 'nullable|array',
            'min_quantity' => 'nullable|integer|min:0',
            'max_quantity' => 'nullable|integer|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_order_value' => 'nullable|numeric|min:0',
            'keywords_include' => 'nullable|array',
            'keywords_exclude' => 'nullable|array',
            'allowed_dosage_forms' => 'nullable|array',
            'only_new_buyers' => 'boolean',
            'skip_personal' => 'boolean',
            'skip_job_vendor' => 'boolean',
            'auto_draft' => 'boolean',
            'auto_prioritize' => 'boolean',
        ]);

        $settings = AiBotSetting::first();
        if (! $settings) {
            $settings = new AiBotSetting;
        }
        $settings->fill($validated);
        $settings->save();

        return response()->json(['message' => 'AI bot settings updated', 'settings' => $settings]);
    }

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);
        $settings = AiBotSetting::first();
        if (! $settings) {
            $settings = AiBotSetting::create(['enabled' => $validated['enabled']]);
        } else {
            $settings->enabled = $validated['enabled'];
            $settings->save();
        }

        return response()->json(['enabled' => $settings->enabled]);
    }

    public function evaluate(Request $request)
    {
        $settings = AiBotSetting::first();
        if (! $settings || ! $settings->enabled) {
            return response()->json(['enabled' => false, 'matches' => [], 'message' => 'AI bot is disabled']);
        }

        $lead = $request->all();
        $result = $this->checkLead($settings, $lead);

        return response()->json(['enabled' => true] + $result);
    }

    private function checkLead($settings, $lead)
    {
        $inquiry = strtolower((string) ($lead['inquiry_text'] ?? $lead['requirement_text'] ?? ''));
        $country = $lead['country'] ?? $lead['destination_country'] ?? '';
        $buyerType = strtolower((string) ($lead['buyer_type'] ?? ''));
        $product = strtolower((string) ($lead['product'] ?? $lead['product_name'] ?? ''));
        $quantity = $lead['quantity'] ?? $lead['parsed_quantity'] ?? null;
        $matches = [];

        if ($settings->skip_personal && $buyerType === 'personal') {
            return ['eligible' => false, 'reason' => 'Personal buyer skipped'];
        }
        if ($settings->skip_job_vendor && $buyerType === 'job_vendor') {
            return ['eligible' => false, 'reason' => 'Job/vendor skipped'];
        }

        if (! empty($settings->country_filters) && ! empty($country) && ! in_array($country, $settings->country_filters)) {
            return ['eligible' => false, 'reason' => 'Country not in allowed list'];
        }

        if (! empty($settings->keywords_include)) {
            $found = false;
            foreach ($settings->keywords_include as $kw) {
                if ($kw && str_contains($inquiry, strtolower((string) $kw))) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $matches[] = ['type' => 'warning', 'message' => 'No required keywords found'];
            } else {
                $matches[] = ['type' => 'ok', 'message' => 'Keywords match'];
            }
        }

        if (! empty($settings->keywords_exclude)) {
            foreach ($settings->keywords_exclude as $kw) {
                if ($kw && str_contains($inquiry, strtolower((string) $kw))) {
                    return ['eligible' => false, 'reason' => 'Excluded keyword found: '.$kw];
                }
            }
        }

        if ($settings->min_quantity !== null && $quantity !== null && (int) $quantity < (int) $settings->min_quantity) {
            return ['eligible' => false, 'reason' => 'Quantity below minimum'];
        }
        if ($settings->max_quantity !== null && $quantity !== null && (int) $quantity > (int) $settings->max_quantity) {
            return ['eligible' => false, 'reason' => 'Quantity above maximum'];
        }

        $matches[] = ['type' => 'ok', 'message' => 'Lead meets AI bot criteria'];

        return ['eligible' => true, 'matches' => $matches];
    }
}

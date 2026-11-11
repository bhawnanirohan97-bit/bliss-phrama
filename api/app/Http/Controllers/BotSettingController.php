<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Models\AiBotSetting;
use App\Models\Country;
use App\Models\Lead;
use App\Models\Product;
use App\Services\Policy\AssessmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class BotSettingController extends Controller
{
    public const PRESET_COUNTRIES = [
        'US' => 'USA (United States)',
        'GB' => 'UK (United Kingdom)',
        'AU' => 'Australia',
        'PH' => 'Philippines',
        'KR' => 'South Korea',
        'JP' => 'Japan',
        'CN' => 'China',
        'NG' => 'Nigeria',
        'AE' => 'UAE (United Arab Emirates)',
        'KE' => 'Kenya',
        'GH' => 'Ghana',
        'VN' => 'Vietnam',
        'DE' => 'Germany',
        'ZA' => 'South Africa',
    ];

    public function __construct(private readonly AssessmentService $assessments) {}

    public function edit(): View
    {
        $settings = AiBotSetting::current();

        $catalogueProducts = Product::query()
            ->matchable()
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'category', 'strength', 'dosage_form', 'review_status']);

        return view('settings', [
            'settings' => $settings,
            'countryRules' => Country::query()->count(),
            'policyVersion' => (string) config('pharmalead.policy_version'),
            'matcherFloor' => (float) config('pharmalead.matcher_floor'),
            'rejectDomestic' => (bool) ($settings->reject_domestic ?? config('pharmalead.reject_domestic', true)),
            'presetCountries' => self::PRESET_COUNTRIES,
            'selectedCountries' => $settings->country_filters ?? [],
            'targetProducts' => $settings->product_filters ?? [],
            'catalogueProducts' => $catalogueProducts,
            'catalogueCounts' => [
                'total' => Product::query()->matchable()->count(),
                'approved' => Product::query()->approved()->count(),
                'blocked' => Product::query()->where('review_status', 'blocked')->count(),
            ],
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $settings = AiBotSetting::current();
        $settings->update(['enabled' => $validated['enabled']]);

        $released = $settings->enabled ? $this->releaseHeldLeads() : 0;

        $message = $settings->enabled
            ? 'Bot switched on. New leads will be processed as they arrive.'
            : 'Bot switched off. New leads are still stored, and will be marked held.';

        if ($released > 0) {
            $message .= " {$released} held lead(s) were released and scored.";
        }

        return back()->with('status', $message);
    }

    public function updateCriteria(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reject_domestic' => ['nullable', 'boolean'],
            'country_filters' => ['nullable', 'array'],
            'country_filters.*' => ['string'],
            'custom_countries' => ['nullable', 'string'],
        ]);

        $settings = AiBotSetting::current();

        $countries = $validated['country_filters'] ?? [];

        // Parse any custom comma-separated countries
        if (! empty($validated['custom_countries'])) {
            $customParts = array_map('trim', explode(',', $validated['custom_countries']));
            foreach ($customParts as $part) {
                if ($part !== '') {
                    $countries[] = strtoupper($part);
                }
            }
        }

        $countries = array_values(array_unique(array_filter($countries)));

        $settings->update([
            'reject_domestic' => $request->boolean('reject_domestic'),
            'country_filters' => $countries === [] ? null : $countries,
        ]);

        return back()->with('status', 'Target export countries and domestic rejection settings updated successfully.');
    }

    public function addProduct(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product' => ['required', 'string'],
        ]);

        $settings = AiBotSetting::current();
        $current = $settings->product_filters ?? [];

        // Support comma-separated items
        $parts = array_map('trim', explode(',', $validated['product']));
        foreach ($parts as $p) {
            if ($p !== '') {
                // Avoid case-insensitive duplicates
                $exists = false;
                foreach ($current as $existing) {
                    if (strcasecmp($existing, $p) === 0) {
                        $exists = true;
                        break;
                    }
                }
                if (! $exists) {
                    $current[] = $p;
                }
            }
        }

        $settings->update(['product_filters' => array_values($current)]);

        return back()->with('status', 'Target product(s) added successfully.');
    }

    public function removeProduct(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product' => ['required', 'string'],
        ]);

        $settings = AiBotSetting::current();
        $current = $settings->product_filters ?? [];

        $target = trim($validated['product']);
        $current = array_values(array_filter($current, fn ($p) => strcasecmp(trim((string) $p), $target) !== 0));

        $settings->update(['product_filters' => empty($current) ? null : $current]);

        return back()->with('status', "Product '{$target}' removed from target list.");
    }

    /**
     * Switching the bot on is the moment the offline backlog becomes work.
     */
    private function releaseHeldLeads(): int
    {
        $held = Lead::query()
            ->actionable()
            ->where('status', LeadStatus::Held->value)
            ->whereDoesntHave('assessments')
            ->orderBy('arrived_at')
            ->orderBy('id')
            ->get();

        foreach ($held as $lead) {
            $lead->update(['status' => LeadStatus::Received]);

            try {
                $this->assessments->assess($lead);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $held->count();
    }
}

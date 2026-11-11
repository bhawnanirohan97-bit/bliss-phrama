<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Models\AiBotSetting;
use App\Models\Lead;
use App\Services\Policy\AssessmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class MobileController extends Controller
{
    public const PRESET_COUNTRIES = [
        'US' => ['name' => 'USA', 'flag' => '🇺🇸'],
        'GB' => ['name' => 'UK', 'flag' => '🇬🇧'],
        'AU' => ['name' => 'Australia', 'flag' => '🇦🇺'],
        'PH' => ['name' => 'Philippines', 'flag' => '🇵🇭'],
        'KR' => ['name' => 'South Korea', 'flag' => '🇰🇷'],
        'JP' => ['name' => 'Japan', 'flag' => '🇯🇵'],
        'CN' => ['name' => 'China', 'flag' => '🇨🇳'],
        'AE' => ['name' => 'UAE (Dubai)', 'flag' => '🇦🇪'],
        'NG' => ['name' => 'Nigeria', 'flag' => '🇳🇬'],
        'KE' => ['name' => 'Kenya', 'flag' => '🇰🇪'],
        'GH' => ['name' => 'Ghana', 'flag' => '🇬🇭'],
        'VN' => ['name' => 'Vietnam', 'flag' => '🇻🇳'],
        'DE' => ['name' => 'Germany', 'flag' => '🇩🇪'],
        'ZA' => ['name' => 'South Africa', 'flag' => '🇿🇦'],
    ];

    public function __construct(private readonly AssessmentService $assessments) {}

    /**
     * Mobile-optimized remote control dashboard for smartphone screens.
     */
    public function index(): View
    {
        $settings = AiBotSetting::current();

        $recentLeads = Lead::query()
            ->latest('id')
            ->take(15)
            ->get();

        $stats = [
            'total' => Lead::query()->count(),
            'today' => Lead::query()->whereDate('created_at', today())->count(),
            'actionable' => Lead::query()->actionable()->count(),
        ];

        return view('mobile', [
            'settings' => $settings,
            'presetCountries' => self::PRESET_COUNTRIES,
            'selectedCountries' => $settings->country_filters ?? ['US', 'GB', 'AU', 'PH', 'KR', 'JP', 'CN'],
            'targetProducts' => $settings->product_filters ?? [],
            'rejectDomestic' => (bool) ($settings->reject_domestic ?? true),
            'recentLeads' => $recentLeads,
            'stats' => $stats,
        ]);
    }

    /**
     * Instant toggle (works for both standard form submit and AJAX/Fetch from mobile).
     */
    public function toggle(Request $request): JsonResponse|RedirectResponse
    {
        $settings = AiBotSetting::current();
        $newState = $request->has('enabled')
            ? $request->boolean('enabled')
            : ! $settings->enabled;

        $settings->update(['enabled' => $newState]);

        $released = 0;
        if ($newState) {
            $released = $this->releaseHeldLeads();
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'enabled' => $settings->enabled,
                'status_text' => $settings->enabled ? 'ACTIVE' : 'OFF',
                'released_count' => $released,
                'message' => $settings->enabled ? 'AI Bot is now ON' : 'AI Bot is now OFF',
            ]);
        }

        $msg = $settings->enabled ? 'AI Bot turned ON.' : 'AI Bot turned OFF.';
        if ($released > 0) {
            $msg .= " Released {$released} held lead(s).";
        }

        return redirect()->route('mobile.index')->with('status', $msg);
    }

    /**
     * Update target export countries and domestic filter from mobile.
     */
    public function updateCriteria(Request $request): RedirectResponse
    {
        $settings = AiBotSetting::current();

        $countries = $request->input('country_filters', []);
        if (! is_array($countries)) {
            $countries = [];
        }

        $custom = (string) $request->input('custom_countries', '');
        if ($custom !== '') {
            $parts = array_map('trim', explode(',', $custom));
            foreach ($parts as $p) {
                if ($p !== '') {
                    $countries[] = strtoupper($p);
                }
            }
        }

        $countries = array_values(array_unique(array_filter($countries)));

        $settings->update([
            'reject_domestic' => $request->boolean('reject_domestic'),
            'country_filters' => empty($countries) ? null : $countries,
        ]);

        return redirect()->route('mobile.index')->with('status', 'Target export markets and filters updated.');
    }

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
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $held->count();
    }
}

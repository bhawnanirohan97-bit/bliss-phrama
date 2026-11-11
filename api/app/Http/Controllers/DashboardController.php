<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Enums\ProductReviewStatus;
use App\Enums\ScoreBand;
use App\Models\AiBotSetting;
use App\Models\Country;
use App\Models\Lead;
use App\Models\LeadAssessment;
use App\Models\LeadSource;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(): View
    {
        $latest = LeadAssessment::query()->selectRaw('max(id)')->groupBy('lead_id');

        $bandTotals = LeadAssessment::query()
            ->whereIn('id', $latest)
            ->selectRaw('band, count(*) as total')
            ->groupBy('band')
            ->pluck('total', 'band');

        return view('dashboard', [
            'botEnabled' => AiBotSetting::current()->enabled,
            'leadCounts' => [
                'total' => Lead::query()->count(),
                'awaiting' => Lead::query()->awaitingWork()->actionable()->count(),
                'held' => Lead::query()->where('status', LeadStatus::Held->value)->count(),
                'contacted' => Lead::query()->where('status', LeadStatus::Contacted->value)->count(),
                'today' => Lead::query()->where('arrived_at', '>=', Carbon::today()->utc())->count(),
                'unassessed' => Lead::query()->actionable()->whereDoesntHave('assessments')->count(),
            ],
            'catalogueCounts' => [
                'matchable' => Product::query()->matchable()->count(),
                'approved' => Product::query()->approved()->count(),
                'blocked' => Product::query()->where('review_status', ProductReviewStatus::Blocked->value)->count(),
                'unreviewed' => Product::query()->where('review_status', ProductReviewStatus::Unreviewed->value)->count(),
                'services' => Product::query()->where('is_service', true)->count(),
            ],
            'bandCounts' => collect(ScoreBand::cases())
                ->mapWithKeys(fn (ScoreBand $band) => [$band->value => (int) ($bandTotals[$band->value] ?? 0)]),
            'countryRules' => Country::query()->count(),
            'recentLeads' => Lead::query()
                ->with('latestAssessment')
                ->latest('arrived_at')
                ->latest('id')
                ->limit(10)
                ->get(),
            'sources' => LeadSource::query()->withCount('leads')->get(),
        ]);
    }
}

<?php

namespace App\Providers;

use App\Enums\ProductReviewStatus;
use App\Models\AiBotSetting;
use App\Models\Lead;
use App\Models\Product;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The sidebar badge and bot indicator appear on every screen, so they
        // are resolved once per layout render rather than passed by each
        // controller.
        View::composer('layouts.app', function ($view): void {
            $view->with([
                'navAwaitingCount' => Lead::query()->awaitingWork()->actionable()->count(),
                'navUnreviewedCount' => Product::query()->matchable()->where('review_status', ProductReviewStatus::Unreviewed->value)->count(),
                'navBotEnabled' => AiBotSetting::current()->enabled,
            ]);
        });
    }
}

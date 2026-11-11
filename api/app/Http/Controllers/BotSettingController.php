<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Models\AiBotSetting;
use App\Models\Country;
use App\Models\Lead;
use App\Services\Policy\AssessmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class BotSettingController extends Controller
{
    public function __construct(private readonly AssessmentService $assessments) {}

    public function edit(): View
    {
        return view('settings', [
            'settings' => AiBotSetting::current(),
            'countryRules' => Country::query()->count(),
            'policyVersion' => (string) config('pharmalead.policy_version'),
            'matcherFloor' => (float) config('pharmalead.matcher_floor'),
            'rejectDomestic' => (bool) config('pharmalead.reject_domestic'),
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

    /**
     * Switching the bot on is the moment the offline backlog becomes work.
     *
     * Leads stored while it was off were deliberately left unprocessed, so they
     * are released here and scored once rather than waiting for the operator to
     * open each one by hand.
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
                // A scoring failure must not strand the whole backlog: the lead
                // is released and can be reassessed, and the rest still move.
                report($exception);
            }
        }

        return $held->count();
    }
}

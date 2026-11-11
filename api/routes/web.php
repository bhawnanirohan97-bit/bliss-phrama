<?php

use App\Http\Controllers\Api\AiBotController;
use App\Http\Controllers\BotSettingController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\LeadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/inbox', [LeadController::class, 'index'])->name('leads.index');
Route::get('/inbox/{lead}', [LeadController::class, 'show'])->name('leads.show');
Route::post('/inbox/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status');
Route::post('/inbox/{lead}/assess', [LeadController::class, 'assess'])->name('leads.assess');
Route::post('/inbox/{lead}/drafts', [DraftController::class, 'regenerate'])->name('leads.drafts.regenerate');

Route::get('/catalogue', [CatalogueController::class, 'index'])->name('catalogue.index');
Route::post('/catalogue/review', [CatalogueController::class, 'review'])->name('catalogue.review');

Route::get('/settings', [BotSettingController::class, 'edit'])->name('settings.edit');
Route::post('/settings/bot', [BotSettingController::class, 'toggle'])->name('settings.bot');

Route::get('/api/ai-bot/settings', [AiBotController::class, 'index']);
Route::put('/api/ai-bot/settings', [AiBotController::class, 'update']);
Route::post('/api/ai-bot/toggle', [AiBotController::class, 'toggle']);
Route::post('/api/ai-bot/evaluate', [AiBotController::class, 'evaluate']);

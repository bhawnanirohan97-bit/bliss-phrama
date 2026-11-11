<?php

use App\Http\Controllers\Api\AiBotController;
use App\Http\Controllers\BotSettingController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MobileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/mobile', [MobileController::class, 'index'])->name('mobile.index');
Route::post('/mobile/toggle', [MobileController::class, 'toggle'])->name('mobile.toggle');
Route::post('/mobile/criteria', [MobileController::class, 'updateCriteria'])->name('mobile.criteria');

Route::redirect('/inbox', '/')->name('leads.index');
Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
Route::post('/leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status');
Route::post('/leads/{lead}/assess', [LeadController::class, 'assess'])->name('leads.assess');
Route::post('/leads/{lead}/drafts', [DraftController::class, 'regenerate'])->name('leads.drafts.regenerate');

Route::get('/catalogue', [CatalogueController::class, 'index'])->name('catalogue.index');
Route::post('/catalogue/review', [CatalogueController::class, 'review'])->name('catalogue.review');
Route::post('/catalogue/add', [CatalogueController::class, 'store'])->name('catalogue.store');
Route::post('/catalogue/delete', [CatalogueController::class, 'destroy'])->name('catalogue.destroy');
Route::post('/catalogue/toggle', [CatalogueController::class, 'toggleStatus'])->name('catalogue.toggle');

Route::get('/settings', [BotSettingController::class, 'edit'])->name('settings.edit');
Route::post('/settings/bot', [BotSettingController::class, 'toggle'])->name('settings.bot');
Route::post('/settings/criteria', [BotSettingController::class, 'updateCriteria'])->name('settings.criteria');
Route::post('/settings/products/add', [BotSettingController::class, 'addProduct'])->name('settings.products.add');
Route::post('/settings/products/remove', [BotSettingController::class, 'removeProduct'])->name('settings.products.remove');


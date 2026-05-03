<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ExportController;

// ── Auth (guest only) ────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Protected routes (all require auth) ──────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Map
    Route::prefix('map')->name('map.')->group(function () {
        Route::get('/',            [MapController::class, 'index'])->name('index');
        Route::get('/markers',     [MapController::class, 'markers'])->name('markers');
        Route::get('/choropleth',  [MapController::class, 'choropleth'])->name('choropleth');
        Route::get('/region/{id}', [MapController::class, 'regionAccounts'])->name('region');
    });

    // Accounts / Categories / Admins
    Route::resource('accounts',   AccountController::class);
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('admins',     AdminController::class)->except(['show']);

    // Regions
    Route::get('/regions',          [RegionController::class, 'index'])->name('regions.index');
    Route::get('/regions/{region}', [RegionController::class, 'show'])->name('regions.show');

    // User management (superadmin only)
    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::post('/users/{user}/toggle-active',  [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::get('/users-activity-log',           [UserController::class, 'activityLog'])->name('users.activity-log');
    });

    // Export routes
    Route::prefix('export')->name('export.')->group(function () {
        Route::get('/accounts',     [ExportController::class, 'accountsExcel'])->name('accounts');
        Route::get('/accounts/pdf', [ExportController::class, 'accountsPdf'])->name('accounts.pdf');
        Route::get('/regions',      [ExportController::class, 'regionsExcel'])->name('regions');
        Route::get('/regions/pdf',  [ExportController::class, 'regionsPdf'])->name('regions.pdf');
        Route::get('/map',          [ExportController::class, 'mapExcel'])->name('map');
    });
});

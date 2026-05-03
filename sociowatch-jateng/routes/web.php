<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\RegionController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::prefix('map')->name('map.')->group(function () {
    Route::get('/',              [MapController::class, 'index'])->name('index');
    // JSON API endpoints untuk Leaflet layers
    Route::get('/markers',       [MapController::class, 'markers'])->name('markers');
    Route::get('/choropleth',    [MapController::class, 'choropleth'])->name('choropleth');
    Route::get('/region/{id}',   [MapController::class, 'regionAccounts'])->name('region');
});

Route::resource('accounts', AccountController::class);
Route::resource('categories', CategoryController::class)->except(['show']);
Route::resource('admins', AdminController::class)->except(['show']);

Route::get('/regions', [RegionController::class, 'index'])->name('regions.index');
Route::get('/regions/{region}', [RegionController::class, 'show'])->name('regions.show');

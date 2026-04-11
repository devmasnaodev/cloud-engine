<?php

use App\Http\Controllers\ServerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? to_route('servers.index')
        : to_route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Server management routes
    Route::resource('servers', ServerController::class);
    Route::post('servers/{server}/sites', [ServerController::class, 'createSite'])
        ->name('servers.sites.create');
    Route::post('servers/{server}/sites/{domain}/import', [ServerController::class, 'importSite'])
        ->name('servers.sites.import');
    Route::get('servers/{server}/sites/{domain}', [ServerController::class, 'siteInfo'])
        ->name('servers.sites.info');
    Route::delete('servers/{server}/sites/{domain}', [ServerController::class, 'destroySite'])
        ->name('servers.sites.destroy');

    // Global sites listing and management
    Route::get('sites', [\App\Http\Controllers\SiteController::class, 'index'])
        ->name('sites.index');
    Route::get('sites/create', [\App\Http\Controllers\SiteController::class, 'create'])
        ->name('sites.create');
    Route::post('sites', [\App\Http\Controllers\SiteController::class, 'store'])
        ->name('sites.store');
    Route::get('sites/{site}', [\App\Http\Controllers\SiteController::class, 'show'])
        ->name('sites.show');
    Route::get('sites/{site}/info', [\App\Http\Controllers\SiteController::class, 'siteInfo'])
        ->name('sites.info');
    Route::get('sites/{site}/edit', [\App\Http\Controllers\SiteController::class, 'edit'])
        ->name('sites.edit');
    Route::patch('sites/{site}', [\App\Http\Controllers\SiteController::class, 'update'])
        ->name('sites.update');
    Route::post('sites/{site}/enable', [\App\Http\Controllers\SiteController::class, 'enable'])
        ->name('sites.enable');
    Route::post('sites/{site}/disable', [\App\Http\Controllers\SiteController::class, 'disable'])
        ->name('sites.disable');
    Route::post('sites/{site}/clean', [\App\Http\Controllers\SiteController::class, 'clean'])
        ->name('sites.clean');
    Route::delete('sites/{site}', [\App\Http\Controllers\SiteController::class, 'destroy'])
        ->name('sites.destroy');
    Route::get('site-command-runs/{run}', [\App\Http\Controllers\SiteController::class, 'commandRunStatus'])
        ->name('site-command-runs.status');
    Route::post('servers/{server}/test-connection', [ServerController::class, 'testConnection'])
        ->name('servers.test-connection');

    // Provisioning
    Route::get('servers/{server}/provisioning', [\App\Http\Controllers\ProvisioningController::class, 'index'])
        ->name('servers.provisioning.index');
    Route::post('servers/{server}/provisioning', [\App\Http\Controllers\ProvisioningController::class, 'run'])
        ->name('servers.provisioning.run');
    Route::get('servers/{server}/provisioning/{run}', [\App\Http\Controllers\ProvisioningController::class, 'status'])
        ->name('servers.provisioning.status');
});

require __DIR__.'/settings.php';

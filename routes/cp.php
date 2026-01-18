<?php

use Illuminate\Support\Facades\Route;
use Thirtybittech\SafeCheck\Http\Controllers\AuditController;

Route::middleware(['statamic.cp.authenticated', 'can:safe-check view'])
    ->prefix('safe-check')
    ->group(function () {

        Route::get('/', [AuditController::class, 'index'])
            ->name('safe-check.index');

        Route::post('/scan', [AuditController::class, 'scan'])
            ->name('safe-check.scan');

        Route::get('/latest', [AuditController::class, 'latest'])
            ->name('safe-check.latest');

        Route::get('/export/json', [AuditController::class, 'exportJson'])
            ->name('safe-check.export.json');


        Route::post(
            '/vuln/{id}',
            [AuditController::class, 'vulnerability']
        )->where('id', '[A-Za-z0-9\-]+')->name('safe-check.vuln');
    });

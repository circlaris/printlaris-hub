<?php

use App\Http\Controllers\JobTypeController;
use App\Http\Controllers\PrintJobController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/job-types');

Route::resource('admin/job-types', JobTypeController::class)
    ->except(['show'])
    ->names([
        'index' => 'admin.job-types.index',
        'create' => 'admin.job-types.create',
        'store' => 'admin.job-types.store',
        'edit' => 'admin.job-types.edit',
        'update' => 'admin.job-types.update',
        'destroy' => 'admin.job-types.destroy',
    ]);

Route::post('/print-jobs', [PrintJobController::class, 'store'])->name('print-jobs.store');

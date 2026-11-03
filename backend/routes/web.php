<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\SiteEditorController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicPortfolioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicPortfolioController::class, 'index'])->name('portfolio.home');
Route::post('/contact', [PublicContactController::class, 'store'])
    ->middleware('throttle:3,10')
    ->name('contact.store');
Route::get('/preview', [PublicPortfolioController::class, 'preview'])
    ->middleware(['auth', 'admin'])
    ->name('portfolio.preview');
Route::get('/{asset}', [PublicPortfolioController::class, 'asset'])
    ->where('asset', 'styles\\.css|app\\.mjs|portfolio-data\\.mjs|favicon\\.svg');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'create'])->name('login');
    Route::post('/admin/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('admin.login');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/site-editor', [SiteEditorController::class, 'edit'])->name('site-editor.edit');
    Route::put('/site-editor', [SiteEditorController::class, 'update'])->name('site-editor.update');
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');
    Route::delete('/media/{mediaAsset}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::get('/inbox', [ContactMessageController::class, 'index'])->name('inbox.index');
    Route::patch('/inbox/{contactMessage}', [ContactMessageController::class, 'update'])->name('inbox.update');
    Route::delete('/inbox/{contactMessage}', [ContactMessageController::class, 'destroy'])->name('inbox.destroy');
});

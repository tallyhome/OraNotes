<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\NoteModerationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\WorkspaceModerationController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\ShareLinkController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
})->name('welcome');

Route::get('/s/{token}', PublicShareController::class)
    ->middleware('throttle:30,1')
    ->name('shares.public');

Route::get('/a/{attachment}', [AttachmentController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('attachments.show');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/favorites', FavoriteController::class)->name('favorites');
    Route::get('/shared', [ShareController::class, 'index'])->name('shared');
    Route::get('/trash', [TrashController::class, 'index'])->name('trash');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::patch('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::post('/workspaces/{workspace}/duplicate', [WorkspaceController::class, 'duplicate'])->name('workspaces.duplicate');
    Route::post('/workspaces/{workspace}/restore', [WorkspaceController::class, 'restore'])->name('workspaces.restore');
    Route::delete('/workspaces/{workspace}/force', [WorkspaceController::class, 'forceDestroy'])->name('workspaces.force');
    Route::post('/workspaces/{workspace}/lock', [WorkspaceController::class, 'lock'])->name('workspaces.lock');
    Route::post('/workspaces/{workspace}/unlock', [WorkspaceController::class, 'unlock'])->name('workspaces.unlock');

    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/api/search', SearchController::class)->name('api.search');
        Route::get('/api/notes/{note}', [NoteController::class, 'show'])->name('api.notes.show');
        Route::post('/api/workspaces/{workspace}/notes', [NoteController::class, 'store'])->name('api.notes.store');
        Route::patch('/api/notes/{note}', [NoteController::class, 'update'])->name('api.notes.update');
        Route::delete('/api/notes/{note}', [NoteController::class, 'destroy'])->name('api.notes.destroy');
        Route::post('/api/notes/{note}/duplicate', [NoteController::class, 'duplicate'])->name('api.notes.duplicate');
        Route::patch('/api/workspaces/{workspace}/positions', [NoteController::class, 'positions'])->name('api.notes.positions');

        Route::post('/api/notes/{note}/restore', [TrashController::class, 'restore'])->name('api.notes.restore');
        Route::delete('/api/notes/{note}/force', [TrashController::class, 'forceDestroy'])->name('api.notes.force');

        Route::get('/api/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
        Route::post('/api/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.notifications.readAll');
        Route::post('/api/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('api.notifications.read');

        Route::get('/api/notes/{note}/export.json', [ExportController::class, 'json'])->name('api.notes.export.json');
        Route::get('/api/notes/{note}/export.html', [ExportController::class, 'html'])->name('api.notes.export.html');
    });

    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/api/notes/{note}/shares', [ShareController::class, 'storeNoteShare'])->name('api.notes.shares.store');
        Route::delete('/api/notes/{note}/shares/{user}', [ShareController::class, 'destroyNoteShare'])->name('api.notes.shares.destroy');
        Route::post('/api/workspaces/{workspace}/members', [ShareController::class, 'storeWorkspaceMember'])->name('api.workspaces.members.store');
        Route::delete('/api/workspaces/{workspace}/members/{user}', [ShareController::class, 'destroyWorkspaceMember'])->name('api.workspaces.members.destroy');
        Route::post('/api/notes/{note}/links', [ShareLinkController::class, 'storeForNote'])->name('api.notes.links.store');
        Route::post('/api/workspaces/{workspace}/links', [ShareLinkController::class, 'storeForWorkspace'])->name('api.workspaces.links.store');
        Route::delete('/api/links/{shareLink}', [ShareLinkController::class, 'destroy'])->name('api.links.destroy');
    });

    Route::post('/api/uploads', [AttachmentController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('api.uploads.store');
});

Route::middleware(['auth', 'verified', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::get('/notes', [NoteModerationController::class, 'index'])->name('notes.index');
    Route::delete('/notes/{note}', [NoteModerationController::class, 'destroy'])->name('notes.destroy');
    Route::get('/workspaces', [WorkspaceModerationController::class, 'index'])->name('workspaces.index');
    Route::delete('/workspaces/{workspace}', [WorkspaceModerationController::class, 'destroy'])->name('workspaces.destroy');
    Route::get('/activity', ActivityLogController::class)->name('activity');
});

require __DIR__.'/auth.php';

<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InvitationAcceptanceController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\Api\V1\WorkspaceInvitationController;
use App\Http\Controllers\Api\V1\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'version' => 'v1',
]))->name('health');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

    /*
     * Everything below is workspace-scoped. The "workspace" middleware
     * resolves the tenant and verifies an active membership before any
     * controller or policy runs, and scopeBindings() resolves nested
     * parameters through the workspace, so a record belonging to another
     * tenant never reaches the application (PROJECT_SPEC.md §7).
     */
    /*
     * Acceptance is deliberately outside the workspace-scoped group: the
     * caller is not a member yet, so there is no tenant to resolve. It is the
     * invitation token plus the authenticated user's email that authorize it
     * (PROJECT_SPEC.md §10).
     */
    Route::post('/invitations/{token}/accept', InvitationAcceptanceController::class)
        ->name('invitations.accept');

    Route::middleware('workspace')->scopeBindings()->group(function () {
        Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
        Route::patch('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
        Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

        Route::get('/workspaces/{workspace}/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('/workspaces/{workspace}/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/workspaces/{workspace}/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::patch('/workspaces/{workspace}/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/workspaces/{workspace}/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::get('/workspaces/{workspace}/tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::post('/workspaces/{workspace}/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::get('/workspaces/{workspace}/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
        Route::patch('/workspaces/{workspace}/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('/workspaces/{workspace}/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

        Route::get('/workspaces/{workspace}/dashboard', DashboardController::class)->name('dashboard');

        // Read-only: activities are written by business actions, never by a
        // request (PROJECT_SPEC.md §9).
        Route::get('/workspaces/{workspace}/activities', [ActivityController::class, 'index'])->name('activities.index');

        Route::get('/workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'index'])->name('invitations.index');
        Route::post('/workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store'])->name('invitations.store');
        Route::delete('/workspaces/{workspace}/invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy'])->name('invitations.destroy');

        Route::get('/workspaces/{workspace}/members', [WorkspaceMemberController::class, 'index'])->name('members.index');
        Route::patch('/workspaces/{workspace}/members/{member}', [WorkspaceMemberController::class, 'update'])->name('members.update');
        Route::delete('/workspaces/{workspace}/members/{member}', [WorkspaceMemberController::class, 'destroy'])->name('members.destroy');
    });
});

<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Tenancy\WorkspaceContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped, not singleton: one tenant context per request, reset between
        // requests even on a long-lived worker (PROJECT_SPEC.md §7).
        $this->app->scoped(WorkspaceContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerMorphMap();
        $this->registerPasswordRules();
        $this->registerRateLimiters();
    }

    /**
     * Activities store a stable alias in subject_type rather than a fully
     * qualified class name, so the permanently retained audit log does not
     * break if a class is ever moved or renamed (PROJECT_SPEC.md §9).
     * enforceMorphMap (rather than morphMap) makes an unmapped model an
     * immediate error instead of a silently written class name.
     */
    protected function registerMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'workspace' => Workspace::class,
            'workspace_member' => WorkspaceMember::class,
            'workspace_invitation' => WorkspaceInvitation::class,
            'project' => Project::class,
            'task' => Task::class,
        ]);
    }

    /**
     * One place to define what counts as an acceptable password, used by
     * every rule set through Password::defaults().
     */
    protected function registerPasswordRules(): void
    {
        Password::defaults(fn () => Password::min(8));
    }

    /**
     * Throttling for the unauthenticated auth endpoints (register, login).
     * Exceeding a limit produces a 429 with Retry-After.
     */
    protected function registerRateLimiters(): void
    {
        // Credential stuffing is per-account, so the tighter limit is keyed by
        // email as well as IP; the looser per-IP limit catches an attacker
        // spraying many different addresses from one host.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by((string) $request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
    }
}

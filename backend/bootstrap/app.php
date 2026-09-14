<?php

use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA authentication: requests from the configured stateful
        // domains are authenticated with the session cookie + CSRF token.
        $middleware->statefulApi();

        // This is an API-only backend with no login page to redirect to. The
        // framework default resolves route('login') eagerly, which would turn
        // every unauthenticated request into a 500; returning null lets the
        // AuthenticationException surface as a JSON 401 instead.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'workspace' => ResolveWorkspace::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Every API 404 answers with the same body. Without this, a missing
        // record reports "No query results for model [App\Models\Workspace]
        // <uuid>" while a workspace the caller is simply not a member of
        // reports something else — a difference that tells an attacker which
        // workspace ids exist (PROJECT_SPEC.md §14, §17).
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }

            return null;
        });
    })->create();

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum SPA (cookie/session) authentication (PROJECT_SPEC.md §5).
 *
 * There is no Action class here: none of these operations appear in the list
 * of business actions in §15, and each is a single framework call.
 */
class AuthController extends Controller
{
    /**
     * Create an account. No session is established and no workspace is
     * created; the SPA logs in as a separate step (PROJECT_SPEC.md §6).
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // The 'hashed' cast on User::$password applies the configured hasher.
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        return UserResource::make($user)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Establish the session. The guard rejects soft-deleted users on its own,
     * because the user provider honours the SoftDeletes global scope.
     */
    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::guard('web')->attempt($request->credentials())) {
            // 422 rather than 401: §14 reserves 401 for "unauthenticated"
            // access to a protected endpoint, and this gives the SPA a
            // field-level error to render.
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Guards against session fixation: the pre-login session id is
        // discarded once the user is authenticated.
        $request->session()->regenerate();

        return UserResource::make($request->user());
    }

    /**
     * Terminate the session and rotate the CSRF token.
     */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Drop every guard instance resolved during this request. The sanctum
        // guard caches the user it resolved before the controller ran, so
        // without this it would still report an authenticated user for the
        // rest of the process — which matters under a long-lived worker.
        Auth::forgetGuards();

        return response()->noContent();
    }

    /**
     * The currently authenticated user. The auth:sanctum middleware answers
     * 401 when there is no session.
     */
    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}

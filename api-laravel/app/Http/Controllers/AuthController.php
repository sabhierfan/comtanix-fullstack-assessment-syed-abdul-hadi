<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Login, logout and "who am I" for staff, using Laravel Sanctum tokens.
 *
 * A token is like a temporary key card: the browser gets it at login and
 * sends it with every request in the "Authorization: Bearer <token>" header.
 */
class AuthController extends Controller
{
    /**
     * A bcrypt hash of a random string nobody knows. When the email does not exist
     * we still check the password against this, so the answer takes the same time.
     * Otherwise an attacker could measure the speed to learn which emails are real.
     */
    private const DUMMY_HASH = '$2y$12$cWGNChwSOZOzFa25Nngq0.C0AneEQx8nm2SjZ4gFXgjt3hMF6WDXW';

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        // Always run one password check, even when the user was not found.
        $passwordOk = Hash::check($request->validated('password'), $user?->password ?? self::DUMMY_HASH);

        if (! $user || ! $passwordOk) {
            // Same message for "no such email" and "wrong password" on purpose.
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // The token expires after SANCTUM_EXPIRATION minutes (8 hours by default).
        $minutes = config('sanctum.expiration');
        $expiresAt = $minutes ? now()->addMinutes($minutes) : null;
        $token = $user->createToken('dashboard', ['*'], $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toIso8601String(),
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Delete the token that made this request, so it can never be used again.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Return the logged-in user. The dashboard calls this on page load
     * to check the saved token still works and to refresh the user's role.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}

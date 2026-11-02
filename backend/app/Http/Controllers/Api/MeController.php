<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/me : who the verified token belongs to. Behind auth:supabase.
 *
 * Returns exactly: the Laravel user id, the Supabase user id, name, email and the verified role.
 * Nothing else: no token, no password hash, no remember token, no other claims.
 */
final class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user('supabase');
        $role = $user instanceof User ? $user->verifiedRole() : null;

        if ($role === null) {
            throw new AuthenticationException('Unauthenticated.', ['supabase']); // cannot happen behind the guard
        }

        return ApiResponse::success([
            'id' => $user->getKey(),
            'supabase_user_id' => $user->supabase_user_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role->value,
        ], 'Current user.');
    }
}

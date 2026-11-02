<?php

namespace App\Auth\Supabase;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The callback behind the "supabase" guard (registered in AppServiceProvider with Auth::viaRequest):
 * request -> strict Bearer parsing -> SupabaseTokenVerifier -> SupabaseUserProvisioner -> User with its verified role.
 *
 * It reuses the approved pieces as they are and adds no rules of its own:
 *  - no Bearer credentials                   -> null (the "auth" middleware answers 401 with a plain Bearer challenge);
 *  - malformed / invalid / expired token     -> InvalidSupabaseToken (401, error="invalid_token");
 *  - keys unavailable / not configured       -> AuthServiceUnavailable (503);
 *  - email already used by another user      -> EmailConflict (409);
 *  - genuine token without a usable email    -> AccountNotProvisionable (403);
 *  - explicit but unsupported app_metadata.role (e.g. "suspended") -> UnsupportedRole (403), before any account is
 *    looked up or created. A MISSING role still means "user".
 * The exceptions are rendered by ApiExceptionHandler.
 *
 * The role comes from the VERIFIED token only (app_metadata.role, default "user") and is attached to the User
 * instance for this request. It is not stored anywhere.
 *
 * Note: a valid token for a Supabase user who has no Laravel account yet creates that account (first login).
 */
final class SupabaseRequestAuthenticator
{
    public function __construct(
        private readonly SupabaseTokenVerifier $verifier,
        private readonly SupabaseUserProvisioner $provisioner,
    ) {}

    public function __invoke(Request $request): ?User
    {
        $token = BearerToken::from($request);

        if ($token === null) {
            return null;
        }

        $verified = $this->verifier->verify($token);

        if ($verified->hasUnsupportedRole) {
            throw new UnsupportedRole;
        }

        return $this->provisioner->provision($verified)->withVerifiedRole($verified->role);
    }
}

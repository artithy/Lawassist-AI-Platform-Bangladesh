<?php

namespace App\Auth\Supabase;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Finds, or creates on first login, the Laravel user for a VERIFIED Supabase token.
 *
 * Identity rules (all approved decisions):
 *  - The ONLY identity key is the Supabase user id (the verified "sub" claim, stored in users.supabase_user_id).
 *  - Accounts are NEVER linked by email. An email that already belongs to a different Laravel user is a conflict
 *    (EmailConflict, 409), even if that user has no Supabase id yet. Email is compared case-insensitively.
 *  - A token with no usable email cannot create an account (AccountNotProvisionable, 403). A user who is already
 *    mapped is still returned even if this particular token carries no email.
 *  - An already-mapped user is returned untouched: name and email are not synchronised from the token.
 *  - New users get an unguessable random password (hashed), which nobody ever sees: Supabase handles all sign-in.
 *    email_verified_at stays null (demo policy: unverified emails are allowed).
 *  - supabase_user_id is assigned explicitly, never mass-assigned (it is not fillable on purpose).
 *  - The role is NOT stored. It stays on the VerifiedToken and is read from there per request.
 *  - No lawyer profile is linked here; only an admin does that.
 *
 * Concurrent first logins: two requests can both see "no user" and both try to insert. The unique indexes on
 * supabase_user_id and email make the database reject the second insert. We then READ AFTER THE CONFLICT:
 *  - a row with our Supabase id exists -> the other request created it: return that user;
 *  - else the email is now taken by someone else -> EmailConflict;
 *  - else the violation is not one we understand -> rethrow it. Nothing is swallowed.
 *
 * This deliberately does not open its own transaction. On PostgreSQL a failed insert aborts the surrounding
 * transaction, which would make the read-after-conflict step fail; call it outside an open transaction.
 */
final class SupabaseUserProvisioner
{
    /**
     * @throws AccountNotProvisionable when a new account is needed but the token has no usable email (403)
     * @throws EmailConflict when the email already belongs to a different Laravel user (409)
     */
    public function provision(VerifiedToken $token): User
    {
        $existing = $this->findBySupabaseId($token->subject);

        if ($existing !== null) {
            return $existing;
        }

        $email = $token->email;

        if ($email === null) {
            throw new AccountNotProvisionable('no_usable_email');
        }

        if ($this->emailInUse($email)) {
            throw new EmailConflict;
        }

        try {
            return $this->create($token->subject, $email, $this->nameFor($token, $email));
        } catch (UniqueConstraintViolationException $e) {
            $winner = $this->findBySupabaseId($token->subject);

            if ($winner !== null) {
                return $winner; // a concurrent first login created it
            }

            if ($this->emailInUse($email)) {
                throw new EmailConflict($e);
            }

            throw $e;
        }
    }

    private function findBySupabaseId(string $supabaseUserId): ?User
    {
        return User::forSupabaseUser($supabaseUserId)->first();
    }

    private function emailInUse(string $email): bool
    {
        return User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->exists();
    }

    private function create(string $supabaseUserId, string $email, string $name): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
        ]);

        // Set explicitly, on purpose: supabase_user_id is not mass-assignable.
        $user->supabase_user_id = $supabaseUserId;
        $user->save();

        return $user;
    }

    /** Display name from the token (already cleaned and length-limited), else the part of the email before "@". */
    private function nameFor(VerifiedToken $token, string $email): string
    {
        $name = $token->displayName;

        if ($name === null || trim($name) === '') {
            $name = (string) strstr($email, '@', true);
        }

        return mb_substr($name, 0, 255);
    }
}

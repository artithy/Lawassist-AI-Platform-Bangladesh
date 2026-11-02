<?php

namespace App\Auth\Supabase;

use Illuminate\Auth\RequestGuard;
use Illuminate\Http\Request;

/**
 * The "supabase" guard: a RequestGuard that cannot hand one request's user to another request.
 *
 * Laravel's RequestGuard remembers the first user it resolves for as long as the guard object lives. With php-fpm that is
 * one request. In a worker that serves many requests from one booted application (Octane, Swoole, RoadRunner,
 * FrankenPHP worker mode) a remembered admin would be returned for the next caller, even an anonymous one.
 *
 * This guard remembers which Request OBJECT it resolved the user for (the framework swaps the request in on every new
 * request) and forgets the user as soon as a different request is current. A user attached on purpose with setUser()
 * (for example by actingAs() in tests) was not resolved here and is left alone.
 */
final class SupabaseGuard extends RequestGuard
{
    private ?Request $resolvedFor = null;

    public function user()
    {
        if ($this->resolvedFor !== null && $this->resolvedFor !== $this->request) {
            $this->user = null;
            $this->resolvedFor = null;
        }

        if ($this->user !== null) {
            return $this->user; // resolved for this very request (or attached with setUser())
        }

        $user = parent::user();

        if ($user !== null) {
            $this->resolvedFor = $this->request;
        }

        return $user;
    }

    public function forgetUser()
    {
        $this->resolvedFor = null;

        return parent::forgetUser();
    }
}

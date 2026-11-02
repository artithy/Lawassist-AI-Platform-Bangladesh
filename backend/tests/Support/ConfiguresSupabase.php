<?php

namespace Tests\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Points the app at a fake Supabase project and serves its (fake) JWKS from memory.
 * Call setUpSupabase() from setUp(). Tests may change $jwksBody / $jwksStatus / $jwksUnreachable between calls.
 */
trait ConfiguresSupabase
{
    protected SupabaseTestKeys $keys;

    /** @var array<string, mixed> */
    protected array $jwksBody = [];

    protected int $jwksStatus = 200;

    protected bool $jwksUnreachable = false;

    protected function setUpSupabase(): void
    {
        config([
            'supabase.url' => SupabaseTestKeys::URL,
            'supabase.audience' => 'authenticated',
            'supabase.jwks_url' => null,
            'supabase.jwks.cache_store' => 'array', // isolated, in-memory: tests never touch files or the database
            'supabase.legacy_hs256.secret' => null,
            'supabase.legacy_hs256.accept_until' => null,
        ]);

        $this->keys = SupabaseTestKeys::generate();
        $this->jwksBody = $this->keys->jwks();

        Http::fake(function () {
            if ($this->jwksUnreachable) {
                throw new ConnectionException('Supabase is unreachable (test).');
            }

            return Http::response($this->jwksBody, $this->jwksStatus);
        });
    }
}

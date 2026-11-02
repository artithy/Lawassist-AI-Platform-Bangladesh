<?php

namespace Tests\Feature\Api;

use App\Auth\Supabase\AccountNotProvisionable;
use App\Auth\Supabase\AuthServiceUnavailable;
use App\Auth\Supabase\EmailConflict;
use App\Auth\Supabase\InvalidSupabaseToken;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Supabase authentication failures must produce fixed, generic API errors (with the right status and headers),
 * must look the same with APP_DEBUG on or off, must never leak the internal reason code or the previous exception's
 * message, and must be logged exactly once with the reason code only.
 *
 * Throw-away routes (test only) raise each exception; the "previous" exceptions carry sentinel strings that must
 * never appear in a response.
 */
class AuthErrorResponsesTest extends TestCase
{
    private const SENTINELS = [
        'bad_signature', 'cache_unavailable', 'email_conflict', 'no_usable_email',
        'SENTINEL_PREVIOUS_MESSAGE', 'SENTINEL_TABLE_NAME', 'SENTINEL_DUPLICATE_KEY', 'RuntimeException',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api/_test')->group(function () {
            Route::get('invalid-token', fn () => throw new InvalidSupabaseToken(
                'bad_signature',
                new RuntimeException('SENTINEL_PREVIOUS_MESSAGE'),
            ));
            Route::get('unavailable', fn () => throw new AuthServiceUnavailable(
                'cache_unavailable',
                new RuntimeException('SENTINEL_TABLE_NAME'),
            ));
            Route::get('email-conflict', fn () => throw new EmailConflict(
                new RuntimeException('SENTINEL_DUPLICATE_KEY'),
            ));
            Route::get('no-email', fn () => throw new AccountNotProvisionable);
            Route::get('unauthenticated', fn () => throw new AuthenticationException);
            Route::get('boom', fn () => throw new RuntimeException('unrelated failure'));
        });
    }

    /** @return array<string, array{0: string, 1: int, 2: string}> path, status, message */
    private function cases(): array
    {
        return [
            'invalid token' => ['invalid-token', 401, 'Invalid or expired access token.'],
            'unavailable' => ['unavailable', 503, 'Authentication service unavailable.'],
            'email conflict' => ['email-conflict', 409, 'An account with this email already exists.'],
            'no usable email' => ['no-email', 403, 'Your account cannot be set up because it has no usable email address.'],
        ];
    }

    public function test_each_failure_has_its_status_and_the_standard_envelope(): void
    {
        foreach ($this->cases() as $name => [$path, $status, $message]) {
            $this->getJson("/api/_test/{$path}")
                ->assertStatus($status)
                ->assertExactJson(['success' => false, 'message' => $message]);
        }
    }

    public function test_responses_are_json_even_without_an_accept_header(): void
    {
        foreach ($this->cases() as $name => [$path, $status, $message]) {
            $this->get("/api/_test/{$path}")
                ->assertStatus($status)
                ->assertExactJson(['success' => false, 'message' => $message]);
        }
    }

    public function test_responses_are_identical_and_leak_nothing_with_debug_on_and_off(): void
    {
        foreach ([true, false] as $debug) {
            config(['app.debug' => $debug]);

            foreach ($this->cases() as $name => [$path, $status, $message]) {
                $response = $this->getJson("/api/_test/{$path}");

                $response->assertStatus($status)
                    ->assertExactJson(['success' => false, 'message' => $message]);

                $everything = $response->getContent().json_encode($response->headers->all());

                foreach (self::SENTINELS as $sentinel) {
                    $this->assertStringNotContainsString(
                        $sentinel,
                        $everything,
                        "{$name} (debug=".($debug ? 'on' : 'off')."): response leaked {$sentinel}",
                    );
                }
            }
        }
    }

    public function test_an_invalid_token_gets_a_bearer_challenge_with_invalid_token(): void
    {
        $this->getJson('/api/_test/invalid-token')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Bearer realm="LawAssist API", error="invalid_token"');
    }

    public function test_a_missing_token_gets_a_plain_bearer_challenge(): void
    {
        $this->getJson('/api/_test/unauthenticated')
            ->assertStatus(401)
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.'])
            ->assertHeader('WWW-Authenticate', 'Bearer realm="LawAssist API"');
    }

    public function test_service_unavailable_asks_clients_to_retry_after_five_seconds(): void
    {
        $this->getJson('/api/_test/unavailable')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '5');
    }

    public function test_only_the_invalid_token_response_carries_the_invalid_token_challenge(): void
    {
        foreach (['unavailable', 'email-conflict', 'no-email'] as $path) {
            $this->getJson("/api/_test/{$path}")->assertHeaderMissing('WWW-Authenticate');
        }
    }

    // ---- logging: one structured line, reason code only ------------------------------------------------------

    // Mockery note: a count modifier (once/twice) verifies IMMEDIATELY, using only the argument filter applied so far.
    // So the filter must come first: ->withArgs(...)->once() means "exactly one call matching these arguments".
    // ->once()->withArgs(...) would count every call to that method, whatever its arguments.

    public function test_an_invalid_token_is_logged_once_as_a_warning_with_only_the_reason(): void
    {
        Log::spy();

        $this->getJson('/api/_test/invalid-token')->assertStatus(401);

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => $context === ['reason' => 'bad_signature'],
        )->once();
        Log::shouldNotHaveReceived('error'); // no second, default entry
    }

    public function test_service_unavailable_is_logged_once_as_an_error_with_only_the_reason(): void
    {
        Log::spy();

        $this->getJson('/api/_test/unavailable')->assertStatus(503);

        Log::shouldHaveReceived('error')->withArgs(
            fn (string $message, array $context) => $context === ['reason' => 'cache_unavailable'],
        )->once();
        Log::shouldNotHaveReceived('warning');
    }

    public function test_provisioning_refusals_are_logged_once_as_warnings_with_only_the_reason(): void
    {
        Log::spy();

        $this->getJson('/api/_test/email-conflict')->assertStatus(409);
        $this->getJson('/api/_test/no-email')->assertStatus(403);

        Log::shouldHaveReceived('warning')->twice();
        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => $context === ['reason' => 'email_conflict'],
        )->once();
        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => $context === ['reason' => 'no_usable_email'],
        )->once();
        Log::shouldNotHaveReceived('error');
    }

    public function test_unrelated_exceptions_are_still_reported_normally(): void
    {
        Log::spy();
        config(['app.debug' => false]);

        $this->getJson('/api/_test/boom')
            ->assertStatus(500)
            ->assertExactJson(['success' => false, 'message' => 'Server error.']);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_a_broken_log_channel_does_not_turn_the_401_into_a_500(): void
    {
        Log::shouldReceive('warning')->andThrow(new RuntimeException('log channel down'));
        Log::shouldReceive('error')->andReturnNull();

        $this->getJson('/api/_test/invalid-token')
            ->assertStatus(401)
            ->assertExactJson(['success' => false, 'message' => 'Invalid or expired access token.']);
    }
}

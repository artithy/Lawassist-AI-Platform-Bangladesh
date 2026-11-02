<?php

namespace Tests\Feature\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

/**
 * Registers throw-away routes (test only, never part of the app) that raise each kind of exception,
 * then checks the response is always the standard JSON envelope.
 */
class ApiExceptionHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api/_test')->group(function () {
            Route::get('unauthenticated', fn () => throw new AuthenticationException);
            Route::get('forbidden', fn () => throw new AuthorizationException);
            Route::get('missing-model', fn () => throw (new ModelNotFoundException)->setModel('App\\Models\\Secret'));
            Route::get('too-many', fn () => throw new TooManyRequestsHttpException(30));
            Route::get('boom', fn () => throw new RuntimeException('secret database password'));
            Route::post('validate', fn () => Validator::make(request()->all(), ['email' => 'required|email'])->validate());
        });
    }

    public function test_unauthenticated_is_401(): void
    {
        $this->getJson('/api/_test/unauthenticated')
            ->assertStatus(401)
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.']);
    }

    public function test_forbidden_is_403(): void
    {
        $this->getJson('/api/_test/forbidden')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_missing_model_is_404_and_does_not_leak_the_model_name(): void
    {
        $this->getJson('/api/_test/missing-model')
            ->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Resource not found.']);
    }

    public function test_unknown_route_is_404_json_even_without_accept_header(): void
    {
        $this->get('/api/does-not-exist')
            ->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Resource not found.']);
    }

    public function test_wrong_method_is_405_with_allow_header(): void
    {
        $this->postJson('/api/health')
            ->assertStatus(405)
            ->assertJsonPath('success', false)
            ->assertHeader('Allow');
    }

    public function test_validation_failure_is_422_with_field_errors(): void
    {
        $this->postJson('/api/_test/validate', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email'], 'errors');
    }

    public function test_throttling_is_429_with_retry_after(): void
    {
        $this->getJson('/api/_test/too-many')
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertHeader('Retry-After');
    }

    public function test_unexpected_error_is_500_without_leaking_details(): void
    {
        config(['app.debug' => false]);

        $this->getJson('/api/_test/boom')
            ->assertStatus(500)
            ->assertExactJson(['success' => false, 'message' => 'Server error.']);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Support\ApiResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_envelope(): void
    {
        $response = ApiResponse::success(['id' => 1], 'Fetched');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['success' => true, 'message' => 'Fetched', 'data' => ['id' => 1]],
            $response->getData(true),
        );
    }

    public function test_created_uses_201(): void
    {
        $response = ApiResponse::created(['id' => 2]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);
    }

    public function test_error_envelope_omits_empty_errors(): void
    {
        $response = ApiResponse::error('Nope', 400);

        $this->assertSame(['success' => false, 'message' => 'Nope'], $response->getData(true));
    }

    public function test_error_envelope_includes_errors_when_given(): void
    {
        $response = ApiResponse::error('Invalid', 422, ['email' => ['Required.']]);

        $this->assertSame(['email' => ['Required.']], $response->getData(true)['errors']);
    }

    public function test_existing_health_endpoint_still_works(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('success', true);
    }
}

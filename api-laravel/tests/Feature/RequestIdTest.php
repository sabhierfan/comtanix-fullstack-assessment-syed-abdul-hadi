<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class RequestIdTest extends TestCase
{
    public function test_every_api_response_gets_a_request_id(): void
    {
        $response = $this->getJson('/api/me')->assertUnauthorized();

        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-Id')));
    }

    public function test_a_safe_incoming_request_id_is_reused(): void
    {
        $this->getJson('/api/me', ['X-Request-Id' => 'lb-trace-12345678'])
            ->assertHeader('X-Request-Id', 'lb-trace-12345678');
    }

    public function test_an_unsafe_incoming_request_id_is_replaced(): void
    {
        $response = $this->getJson('/api/me', ['X-Request-Id' => "bad id\nwith newline"]);

        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-Id')));
    }
}

<?php

namespace App\Services\Audit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP client for the Node audit service.
 */
class AuditServiceClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $secret,
        private readonly ?string $readKey,
        private readonly int $timeout = 5,
    ) {}

    /**
     * Read one quote request's audit trail, newest first.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     *
     * @throws RequestException|ConnectionException when the audit service is unreachable or errors
     */
    public function listEvents(int $quoteRequestId, int $page = 1, int $perPage = 20): array
    {
        return Http::acceptJson()
            ->withHeaders(['X-Api-Key' => (string) $this->readKey])
            ->connectTimeout(2)
            ->timeout($this->timeout)
            ->get($this->baseUrl.'/events', [
                'quoteRequestId' => $quoteRequestId,
                'page' => $page,
                'perPage' => $perPage,
            ])
            ->throw()
            ->json();
    }

    /**
     * Send one event. The X-Signature header is an HMAC-SHA256 of the exact raw body.
     *
     * @param  array<string, mixed>  $event
     *
     * @throws RequestException when the audit service answers with an error status
     */
    public function send(array $event): void
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        Http::withBody($body, 'application/json')
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Signature' => $this->sign($body),
            ])
            ->connectTimeout(3)
            ->timeout($this->timeout)
            ->post($this->baseUrl.'/events')
            ->throw();
    }

    public function sign(string $body): string
    {
        if (! $this->secret) {
            throw new RuntimeException('AUDIT_SIGNING_SECRET is not configured.');
        }

        return hash_hmac('sha256', $body, $this->secret);
    }
}

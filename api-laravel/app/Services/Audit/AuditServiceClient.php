<?php

namespace App\Services\Audit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the Node audit service over HTTP.
 *
 * - send()       : POST /events  (used by the queue job, signed with HMAC)
 * - listEvents() : GET  /events  (used by the dashboard's timeline, with the read key)
 */
class AuditServiceClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $secret,   // AUDIT_SIGNING_SECRET, shared with Node
        private readonly ?string $readKey,  // AUDIT_READ_API_KEY, shared with Node
        private readonly int $timeout = 5,  // seconds; never wait forever
    ) {}

    /**
     * Read one quote request's history, newest first.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     *
     * @throws RequestException|ConnectionException when the audit service is down or errors
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
            ->throw()   // turn 4xx/5xx answers into an exception
            ->json();
    }

    /**
     * Send one event to the audit service.
     *
     * Steps:
     *   1. Turn the event into JSON text.
     *   2. Make a signature of that exact text with the shared secret (HMAC-SHA256).
     *   3. Send the text, with the signature in the X-Signature header.
     * The audit service makes the same signature and compares. If someone changed
     * even one character, or doesn't know the secret, the signatures won't match.
     *
     * @param  array<string, mixed>  $event
     *
     * @throws RequestException when the audit service answers with an error status
     */
    public function send(array $event): void
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // withBody() sends our exact $body string, so the signed text = the sent text.
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

    /** HMAC-SHA256 of the body, as a hex string. */
    public function sign(string $body): string
    {
        if (! $this->secret) {
            throw new RuntimeException('AUDIT_SIGNING_SECRET is not configured.');
        }

        return hash_hmac('sha256', $body, $this->secret);
    }
}

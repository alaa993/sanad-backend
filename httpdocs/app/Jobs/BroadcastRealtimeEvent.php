<?php

namespace App\Jobs;

use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version4X;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Queued job that pushes an event to realtime-server (/internal/emit with X-Realtime-Token).
 * Falls back to ElephantIO Socket.IO client when HTTP emit fails. Runs on the `realtime` queue.
 */
class BroadcastRealtimeEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $event;
    public array $payload;

    public function __construct(string $event, array $payload)
    {
        $this->event = $event;
        $this->payload = $payload;
        $this->queue = 'realtime';
    }

    public int $tries = 3;

    public function handle(): void
    {
        if ($this->broadcastViaHttp()) {
            return;
        }
        $this->broadcastViaSocketClient();
    }

    /** Synchronous emit for hot paths that cannot wait for the queue worker. */
    public static function emitNow(string $event, array $payload): void
    {
        (new self($event, $payload))->handle();
    }

    /** Preferred path: POST to realtime-server internal HTTP emit endpoint. */
    private function broadcastViaHttp(): bool
    {
        $token = config('realtime.token');
        $internalUrl = config('realtime.internal_url');

        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-Realtime-Token' => $token])
                ->post($internalUrl.'/internal/emit', [
                    'event' => $this->event,
                    'payload' => $this->payload,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('Realtime HTTP broadcast rejected', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Realtime HTTP broadcast failed: '.$e->getMessage());
        }

        return false;
    }

    private function broadcastViaSocketClient(): void
    {
        $endpoint = config('realtime.url');
        $path = config('realtime.path', '/socket/');
        if (empty($endpoint)) {
            return;
        }

        try {
            $engine = new Version4X($endpoint, [
                'context' => ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]],
                'query' => [
                    'userId' => 'system',
                    'role' => 'system',
                    'token' => config('realtime.token'),
                ],
                'path' => $path,
            ]);
            $client = new Client($engine);
            $client->initialize();
            $client->emit($this->event, $this->payload);
            $client->close();
        } catch (\Throwable $e) {
            Log::warning('Realtime socket broadcast failed: '.$e->getMessage());
        }
    }
}

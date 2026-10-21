<?php

namespace App\Assistant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Talks to the Python NLU service. Returns null when it is down, never throws. */
class NluClient
{
    public function parse(string $text, string $today): ?array
    {
        return $this->post('/parse', ['text' => $text, 'today' => $today]);
    }

    /** @param  list<array{id:int,texts:list<string>}>  $faqs */
    public function faq(string $text, array $faqs): ?array
    {
        return $this->post('/faq', ['text' => $text, 'faqs' => $faqs])['match'] ?? null;
    }

    public function health(): ?array
    {
        try {
            return Http::timeout(config('services.nlu.timeout'))->get(config('services.nlu.url').'/health')->throw()->json();
        } catch (ConnectionException|RequestException) {
            return null;
        }
    }

    private function post(string $path, array $payload): ?array
    {
        try {
            return Http::timeout(config('services.nlu.timeout'))
                ->post(config('services.nlu.url').$path, $payload)
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $e) {
            Log::warning('NLU unavailable', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }
}

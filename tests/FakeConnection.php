<?php

namespace Tenma\Discord\Tests;

use Tenma\Discord\Connection;

/** Stands in for the plugin: records every call and lets a test send gateway events. */
final class FakeConnection implements Connection
{
    /** @var list<array{function: string, input: array<mixed>}> */
    public array $calls = [];

    /** @var array<string, string> "METHOD /path" => response body */
    public array $responses = [];

    /** @var array<string, \Closure(string): void> */
    private array $subscriptions = [];

    public function __construct(private readonly string $channel = 'discord.w0') {}

    public function call(string $function, string $input): string
    {
        $this->record($function, $input);

        return $function === 'register' ? $this->channel : '';
    }

    public function await(string $function, string $input): string
    {
        $request = $this->record($function, $input);
        if ($function !== 'rest') {
            return '';
        }

        $method = is_string($request['method'] ?? null) ? $request['method'] : '';
        $path = is_string($request['path'] ?? null) ? $request['path'] : '';

        return $this->responses["$method $path"] ?? '';
    }

    public function subscribe(string $channel, \Closure $handler): void
    {
        $this->subscriptions[$channel] = $handler;
    }

    /** @param array<mixed> $data */
    public function emit(string $type, array $data): void
    {
        $payload = json_encode(['op' => 0, 't' => $type, 'd' => $data], JSON_THROW_ON_ERROR);
        ($this->subscriptions[$this->channel])($payload);
    }

    /** @return list<array<mixed>> the bodies of the REST calls with this method and path */
    public function rest(string $method, string $path): array
    {
        $bodies = [];
        foreach ($this->calls as $call) {
            if ($call['function'] === 'rest' && $call['input']['method'] === $method && $call['input']['path'] === $path) {
                $bodies[] = is_array($call['input']['body']) ? $call['input']['body'] : [];
            }
        }

        return $bodies;
    }

    /** @return array<mixed> */
    private function record(string $function, string $input): array
    {
        $decoded = $input === '' ? [] : json_decode($input, true, flags: JSON_THROW_ON_ERROR);
        $decoded = is_array($decoded) ? $decoded : [];
        $this->calls[] = ['function' => $function, 'input' => $decoded];

        return $decoded;
    }
}

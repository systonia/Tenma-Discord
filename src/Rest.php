<?php

namespace Tenma\Discord;

/** Discord's HTTP API with the bot's token. The fiber waits for the answer, the worker goes on. */
final class Rest
{
    public function __construct(private readonly Connection $connection) {}

    /**
     * Any endpoint of https://discord.com/api/v10, e.g. `request('GET', "/guilds/$id/roles")`.
     * @param array<mixed>|null $body
     * @throws \RuntimeException with Discord's answer when it refuses
     */
    public function request(string $method, string $path, ?array $body = null): Payload
    {
        $input = json_encode(
            ['method' => $method, 'path' => $path, 'body' => $body],
            JSON_THROW_ON_ERROR,
        );

        return Payload::decode($this->connection->await('rest', $input));
    }

    /** Posts a message in a channel and returns its id. */
    public function send(string $channelId, string|Message $message): string
    {
        $sent = $this->request('POST', "/channels/$channelId/messages", Message::from($message)->toArray());

        return $sent->string('id');
    }
}

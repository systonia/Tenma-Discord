<?php

namespace Tenma\Discord\Interactions;

use Tenma\Discord\{Bot, Message, Modal, Payload, User};

/**
 * A slash command or a click. Discord waits 3 seconds for the first answer: reply() at once, or defer() and
 * reply() when the work is done. A handler's return value (a string or a Message) is its reply.
 */
abstract class Interaction
{
    public readonly string $id;
    public readonly string $token;
    public readonly ?string $guildId;
    public readonly ?string $channelId;
    public readonly User $user;
    /** The voice channel the user sits in, in this guild; null outside of one. */
    public readonly ?string $voiceChannel;

    private bool $answered = false;
    private bool $deferred = false;

    public function __construct(Payload $data, protected readonly Bot $bot)
    {
        $this->id = $data->string('id');
        $this->token = $data->string('token');
        $this->guildId = $data->optionalString('guild_id');
        $this->channelId = $data->optionalString('channel_id');

        // In a guild the user comes inside the member, in a direct message on its own
        $user = $data->has('member') ? $data->get('member')->get('user') : $data->get('user');
        $this->user = User::fromPayload($user);

        $this->voiceChannel = $this->guildId === null ? null : $bot->voiceChannel($this->guildId, $this->user->id);
    }

    /** Answers; after defer() it replaces the "thinking" message, after a reply it adds another message. */
    public function reply(string|Message $message): void
    {
        $message = Message::from($message);

        if ($this->deferred) {
            $this->deferred = false;
            $this->edit($message);
            return;
        }
        if ($this->answered) {
            $this->followUp($message);
            return;
        }

        $this->respond(4, $message->toArray());
    }

    /** Shows "thinking" and gives the handler 15 minutes instead of 3 seconds. */
    public function defer(bool $ephemeral = false): void
    {
        if ($this->answered) {
            return;
        }
        $this->respond(5, ['flags' => $ephemeral ? 1 << 6 : 0]);
        $this->deferred = true;
    }

    /** Opens a form as the answer; only as the first one, so not after defer(). */
    public function modal(Modal $modal): void
    {
        $this->respond(9, $modal->toArray());
    }

    /** Another message after the first answer. */
    public function followUp(string|Message $message): void
    {
        $path = "/webhooks/{$this->bot->applicationId()}/{$this->token}";
        $this->bot->rest->request('POST', $path, Message::from($message)->toArray());
    }

    /** Changes the first answer. */
    public function edit(string|Message $message): void
    {
        $path = "/webhooks/{$this->bot->applicationId()}/{$this->token}/messages/@original";
        $this->bot->rest->request('PATCH', $path, Message::from($message)->toArray());
    }

    public function answered(): bool
    {
        return $this->answered;
    }

    /** @param array<string, mixed> $data */
    protected function respond(int $type, array $data): void
    {
        $this->bot->rest->request(
            'POST',
            "/interactions/{$this->id}/{$this->token}/callback",
            ['type' => $type, 'data' => $data],
        );
        $this->answered = true;
    }
}

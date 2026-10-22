<?php

namespace Tenma\Discord\Events;

use Tenma\Discord\{Bot, Message, Payload, User};

/**
 * Someone wrote a message. Needs Intents::GuildMessages or DirectMessages; without the privileged
 * Intents::MessageContent, $content is empty unless the bot is mentioned.
 */
final readonly class MessageCreated implements Event
{
    public const string TYPE = 'MESSAGE_CREATE';

    public function __construct(
        public string $id,
        public string $channelId,
        public ?string $guildId,
        public User $author,
        public string $content,
        private Bot $bot,
    ) {}

    public static function fromPayload(Payload $data, Bot $bot): static
    {
        return new static(
            $data->string('id'),
            $data->string('channel_id'),
            $data->optionalString('guild_id'),
            User::fromPayload($data->get('author')),
            $data->optionalString('content') ?? '',
            $bot,
        );
    }

    /** Answers in the same channel, as a reply to this message. */
    public function reply(string|Message $message): string
    {
        $body = Message::from($message)->toArray();
        $body['message_reference'] = ['message_id' => $this->id];

        return $this->bot->rest->request('POST', "/channels/{$this->channelId}/messages", $body)->string('id');
    }
}

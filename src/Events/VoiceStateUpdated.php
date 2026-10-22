<?php

namespace Tenma\Discord\Events;

use Tenma\Discord\{Bot, Payload};

/** Someone joined, left or moved between voice channels, or muted. $channelId is null after leaving. */
final readonly class VoiceStateUpdated implements Event
{
    public const string TYPE = 'VOICE_STATE_UPDATE';

    public function __construct(
        public string $guildId,
        public string $userId,
        public ?string $channelId,
    ) {}

    public static function fromPayload(Payload $data, Bot $bot): static
    {
        return new static(
            $data->string('guild_id'),
            $data->string('user_id'),
            $data->optionalString('channel_id'),
        );
    }
}

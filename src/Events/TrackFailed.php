<?php

namespace Tenma\Discord\Events;

use Tenma\Discord\{Bot, Payload};

/** A track could not play: the URL is unreachable, or nothing can decode its format. The queue moves on. */
final readonly class TrackFailed implements Event
{
    public const string TYPE = 'TRACK_FAILED';

    public function __construct(
        public string $guildId,
        public string $url,
        public string $error,
    ) {}

    public static function fromPayload(Payload $data, Bot $bot): static
    {
        return new static($data->string('guild_id'), $data->string('url'), $data->string('error'));
    }
}

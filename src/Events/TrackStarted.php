<?php

namespace Tenma\Discord\Events;

use Tenma\Discord\{Bot, Payload};

/** A track of the guild's queue started playing. */
final readonly class TrackStarted implements Event
{
    public const string TYPE = 'TRACK_STARTED';

    public function __construct(
        public string $guildId,
        public string $url,
    ) {}

    public static function fromPayload(Payload $data, Bot $bot): static
    {
        return new static($data->string('guild_id'), $data->string('url'));
    }
}

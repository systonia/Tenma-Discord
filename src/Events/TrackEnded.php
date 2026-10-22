<?php

namespace Tenma\Discord\Events;

use Tenma\Discord\{Bot, Payload};

/** A track ended: it played to the end, or was skipped or stopped. */
final readonly class TrackEnded implements Event
{
    public const string TYPE = 'TRACK_ENDED';

    public function __construct(
        public string $guildId,
        public string $url,
    ) {}

    public static function fromPayload(Payload $data, Bot $bot): static
    {
        return new static($data->string('guild_id'), $data->string('url'));
    }
}

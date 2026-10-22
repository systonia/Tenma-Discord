<?php

namespace Tenma\Discord\Events;

use Tenma\Discord\{Bot, Payload};

/** A guild became available: after connecting, once per guild, and when the bot joins a new one. */
final readonly class GuildCreated implements Event
{
    public const string TYPE = 'GUILD_CREATE';

    public function __construct(
        public string $id,
        public string $name,
        public int $memberCount,
    ) {}

    public static function fromPayload(Payload $data, Bot $bot): static
    {
        return new static(
            $data->string('id'),
            $data->string('name'),
            $data->int('member_count'),
        );
    }
}

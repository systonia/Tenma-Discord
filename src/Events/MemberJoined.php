<?php

namespace Tenma\Discord\Events;

use Tenma\Discord\{Bot, Payload, User};

/** Someone joined a guild. Needs the privileged Intents::GuildMembers. */
final readonly class MemberJoined implements Event
{
    public const string TYPE = 'GUILD_MEMBER_ADD';

    public function __construct(
        public string $guildId,
        public User $user,
    ) {}

    public static function fromPayload(Payload $data, Bot $bot): static
    {
        return new static(
            $data->string('guild_id'),
            User::fromPayload($data->get('user')),
        );
    }
}

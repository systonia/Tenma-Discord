<?php

namespace Tenma\Discord\Events;

use Tenma\Discord\{Bot, Payload, User};

/** The bot is connected. Comes again after a reconnect that could not resume the session. */
final readonly class Ready implements Event
{
    public const string TYPE = 'READY';

    public function __construct(
        public User $user,
        public string $applicationId,
    ) {}

    public static function fromPayload(Payload $data, Bot $bot): static
    {
        return new static(
            User::fromPayload($data->get('user')),
            $data->get('application')->string('id'),
        );
    }
}

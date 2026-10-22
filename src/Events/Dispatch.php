<?php

namespace Tenma\Discord\Events;

use Tenma\Discord\{Bot, Payload};

/** Every gateway event as it came, for the ones without a class of their own: `$bot->on(Dispatch::class, ...)`. */
final readonly class Dispatch implements Event
{
    public function __construct(
        public string $type,
        public Payload $data,
    ) {}

    public static function fromPayload(Payload $data, Bot $bot): static
    {
        return new static($data->string('t'), $data->get('d'));
    }
}

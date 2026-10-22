<?php

namespace Tenma\Discord\Tests;

use Tenma\Discord\{Bot, Payload};
use Tenma\Discord\Events\Event;

/** An event class the bot has no gateway name for. */
final readonly class UnknownEvent implements Event
{
    public static function fromPayload(Payload $data, Bot $bot): static
    {
        return new static();
    }
}

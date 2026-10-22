<?php

namespace Tenma\Discord\Events;

use Tenma\Discord\{Bot, Payload};

/** A gateway event a bot can listen to with Bot::on(). */
interface Event
{
    /** The gateway's name for it, e.g. MESSAGE_CREATE. */
    public const string TYPE = '';

    public static function fromPayload(Payload $data, Bot $bot): static;
}

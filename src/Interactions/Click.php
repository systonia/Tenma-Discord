<?php

namespace Tenma\Discord\Interactions;

use Tenma\Discord\{Bot, Message, Payload};

/** A click on a Button the bot sent. */
final class Click extends Interaction
{
    /** The Button's id. */
    public readonly string $buttonId;
    /** The message the button sits under. */
    public readonly string $messageId;

    public function __construct(Payload $data, Bot $bot)
    {
        parent::__construct($data, $bot);

        $this->buttonId = $data->get('data')->string('custom_id');
        $this->messageId = $data->get('message')->string('id');
    }

    /** Replaces the message the button sits under, as the answer to the click. */
    public function update(string|Message $message): void
    {
        $this->respond(7, Message::from($message)->toArray());
    }
}

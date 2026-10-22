<?php

namespace Tenma\Discord;

final readonly class User
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $bot = false,
    ) {}

    public static function fromPayload(Payload $user): self
    {
        return new self(
            $user->string('id'),
            $user->optionalString('global_name') ?? $user->string('username'),
            $user->value('bot') === true,
        );
    }

    /** `<@id>`: a mention in a message. */
    public function mention(): string
    {
        return "<@{$this->id}>";
    }
}

<?php

namespace Tenma\Discord;

/** An option of a slash command: `Option::string('url', 'Stream URL')`. */
final readonly class Option
{
    private function __construct(
        public int $type,
        public string $name,
        public string $description,
        public bool $required,
    ) {}

    public static function string(string $name, string $description, bool $required = false): self
    {
        return new self(3, $name, $description, $required);
    }

    public static function integer(string $name, string $description, bool $required = false): self
    {
        return new self(4, $name, $description, $required);
    }

    public static function boolean(string $name, string $description, bool $required = false): self
    {
        return new self(5, $name, $description, $required);
    }

    public static function user(string $name, string $description, bool $required = false): self
    {
        return new self(6, $name, $description, $required);
    }

    public static function channel(string $name, string $description, bool $required = false): self
    {
        return new self(7, $name, $description, $required);
    }

    public static function number(string $name, string $description, bool $required = false): self
    {
        return new self(10, $name, $description, $required);
    }

    /** @return array{type: int, name: string, description: string, required: bool} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'required' => $this->required,
        ];
    }
}

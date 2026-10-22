<?php

namespace Tenma\Discord;

use Tenma\Discord\Interactions\Autocomplete;

/**
 * An option of a slash command: `Option::string('url', 'Stream URL')`. Text and number options can suggest
 * values while the user types: the autocomplete handler gets an Autocomplete and returns the suggestions.
 */
final readonly class Option
{
    /** @param (\Closure(Autocomplete): array<string|int|float>)|null $autocomplete */
    private function __construct(
        public int $type,
        public string $name,
        public string $description,
        public bool $required,
        public ?\Closure $autocomplete = null,
    ) {}

    /** @param (\Closure(Autocomplete): array<string|int|float>)|null $autocomplete */
    public static function string(
        string $name,
        string $description,
        bool $required = false,
        ?\Closure $autocomplete = null,
    ): self {
        return new self(3, $name, $description, $required, $autocomplete);
    }

    /** @param (\Closure(Autocomplete): array<string|int|float>)|null $autocomplete */
    public static function integer(
        string $name,
        string $description,
        bool $required = false,
        ?\Closure $autocomplete = null,
    ): self {
        return new self(4, $name, $description, $required, $autocomplete);
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

    /** @param (\Closure(Autocomplete): array<string|int|float>)|null $autocomplete */
    public static function number(
        string $name,
        string $description,
        bool $required = false,
        ?\Closure $autocomplete = null,
    ): self {
        return new self(10, $name, $description, $required, $autocomplete);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $option = [
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'required' => $this->required,
        ];
        if ($this->autocomplete !== null) {
            $option['autocomplete'] = true;
        }

        return $option;
    }
}

<?php

namespace Tenma\Discord;

/** A JSON object or list from Discord, read with the type each field must have. */
final readonly class Payload
{
    /** @param array<mixed> $values */
    public function __construct(private array $values = []) {}

    public static function decode(string $json): self
    {
        if ($json === '') {
            return new self();
        }
        $values = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self(is_array($values) ? $values : []);
    }

    public function has(string $key): bool
    {
        return isset($this->values[$key]);
    }

    public function string(string $key): string
    {
        $value = $this->values[$key] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException("Discord sent no text for \"$key\"");
        }

        return $value;
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function int(string $key): int
    {
        $value = $this->values[$key] ?? null;

        return is_int($value) ? $value : 0;
    }

    public function value(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    /** The nested object under $key; empty when there is none. */
    public function get(string $key): self
    {
        $value = $this->values[$key] ?? null;

        return new self(is_array($value) ? $value : []);
    }

    /** @return list<self> the objects of the list under $key, or of this list itself without a key */
    public function list(?string $key = null): array
    {
        $values = $key === null ? $this->values : ($this->values[$key] ?? []);
        if (!is_array($values)) {
            return [];
        }

        $items = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                $items[] = new self($value);
            }
        }

        return $items;
    }

    /** @return array<mixed> */
    public function toArray(): array
    {
        return $this->values;
    }
}

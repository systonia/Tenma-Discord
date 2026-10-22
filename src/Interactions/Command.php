<?php

namespace Tenma\Discord\Interactions;

use Tenma\Discord\{Bot, Payload};

/** A slash command someone ran. */
final class Command extends Interaction
{
    public readonly string $name;

    /** @var array<string, mixed> option name => value */
    private array $options = [];

    public function __construct(Payload $data, Bot $bot)
    {
        parent::__construct($data, $bot);

        $command = $data->get('data');
        $this->name = $command->string('name');
        foreach ($command->list('options') as $option) {
            $this->options[$option->string('name')] = $option->value('value');
        }
    }

    /** An option's value: a string, int, float or bool; an id for users and channels; null when not given. */
    public function option(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    /** An option as text; null when it was not given. */
    public function string(string $name): ?string
    {
        $value = $this->option($name);

        return is_scalar($value) ? (string) $value : null;
    }

    /** An integer option; null when it was not given. */
    public function integer(string $name): ?int
    {
        $value = $this->option($name);

        return is_int($value) ? $value : null;
    }
}

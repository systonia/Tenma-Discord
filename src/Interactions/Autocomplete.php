<?php

namespace Tenma\Discord\Interactions;

use Tenma\Discord\{Bot, Payload};

/**
 * Someone types into an option that suggests values. The handler returns up to 25 suggestions: a list of
 * values, or names => values when the user should see something else than what the command gets.
 */
final class Autocomplete extends Interaction
{
    /** The command being typed. */
    public readonly string $command;
    /** The option the user types into. */
    public readonly string $option;
    /** What the user typed so far. */
    public readonly string $value;

    /** @var array<string, mixed> option name => value, of the options filled in already */
    private array $options = [];

    public function __construct(Payload $data, Bot $bot)
    {
        parent::__construct($data, $bot);

        $command = $data->get('data');
        $this->command = $command->string('name');

        $focused = '';
        foreach ($command->list('options') as $option) {
            $name = $option->string('name');
            $this->options[$name] = $option->value('value');
            if ($option->value('focused') === true) {
                $focused = $name;
            }
        }
        $this->option = $focused;
        $value = $this->options[$focused] ?? '';
        $this->value = is_scalar($value) ? (string) $value : '';
    }

    /** Another option's value, as far as the user filled it in; null when not. */
    public function option(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    /** @param array<mixed> $suggestions values, or names => values; other entries are left out */
    public function suggest(array $suggestions): void
    {
        $choices = [];
        foreach (array_slice($suggestions, 0, 25, true) as $name => $value) {
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                continue;
            }
            $name = is_string($name) ? $name : (string) $value;
            $choices[] = ['name' => mb_substr($name, 0, 100), 'value' => $value];
        }

        $this->respond(8, ['choices' => $choices]);
    }
}

<?php

namespace Tenma\Discord;

/**
 * A form that pops up over Discord, opened in answer to a command or a click. What the user enters reaches
 * the handler registered with Bot::modal() as a Submit.
 *
 *     $command->modal(new Modal('suggest', 'Suggest a station', [new TextInput('url', 'Stream URL')]));
 */
final readonly class Modal
{
    /** @param list<TextInput> $fields up to 5 */
    public function __construct(
        public string $id,
        public string $title,
        public array $fields,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'custom_id' => $this->id,
            'title' => $this->title,
            'components' => array_map(fn (TextInput $field) => $field->toArray(), $this->fields),
        ];
    }
}

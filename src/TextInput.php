<?php

namespace Tenma\Discord;

/** A text field of a Modal: one line, or several with $paragraph. $value fills it in beforehand. */
final readonly class TextInput
{
    public function __construct(
        public string $id,
        public string $label,
        public bool $paragraph = false,
        public bool $required = true,
        public ?string $value = null,
        public ?string $placeholder = null,
        public ?int $maxLength = null,
    ) {}

    /** @return array<string, mixed> the field inside its label */
    public function toArray(): array
    {
        $input = [
            'type' => 4,
            'custom_id' => $this->id,
            'style' => $this->paragraph ? 2 : 1,
            'required' => $this->required,
        ];
        if ($this->value !== null) {
            $input['value'] = $this->value;
        }
        if ($this->placeholder !== null) {
            $input['placeholder'] = $this->placeholder;
        }
        if ($this->maxLength !== null) {
            $input['max_length'] = $this->maxLength;
        }

        return ['type' => 18, 'label' => $this->label, 'component' => $input];
    }
}

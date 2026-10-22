<?php

namespace Tenma\Discord;

/** A button under a message. Its id reaches the handler registered with Bot::button(); a link button opens $url. */
final readonly class Button
{
    public function __construct(
        public string $id,
        public string $label,
        public ButtonStyle $style = ButtonStyle::Primary,
        public ?string $url = null,
        public bool $disabled = false,
    ) {}

    public static function link(string $label, string $url): self
    {
        return new self('', $label, ButtonStyle::Link, $url);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $button = [
            'type' => 2,
            'style' => $this->style->value,
            'label' => $this->label,
            'disabled' => $this->disabled,
        ];
        if ($this->style === ButtonStyle::Link) {
            $button['url'] = $this->url;
        } else {
            $button['custom_id'] = $this->id;
        }

        return $button;
    }
}

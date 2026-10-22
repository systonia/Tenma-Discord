<?php

namespace Tenma\Discord;

/** A card inside a message. */
final readonly class Embed
{
    /** @param array<string, string> $fields name => value, shown side by side where they fit */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $url = null,
        public ?int $color = null,
        public array $fields = [],
        public ?string $image = null,
        public ?string $footer = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $fields = [];
        foreach ($this->fields as $name => $value) {
            $fields[] = ['name' => $name, 'value' => $value, 'inline' => true];
        }

        $embed = [
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'color' => $this->color,
            'fields' => $fields,
            'image' => $this->image === null ? null : ['url' => $this->image],
            'footer' => $this->footer === null ? null : ['text' => $this->footer],
        ];

        return array_filter($embed, fn (mixed $value) => $value !== null && $value !== []);
    }
}

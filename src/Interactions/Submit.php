<?php

namespace Tenma\Discord\Interactions;

use Tenma\Discord\{Bot, Payload};

/** Someone filled in a Modal and sent it. */
final class Submit extends Interaction
{
    /** The Modal's id. */
    public readonly string $modalId;

    /** @var array<string, string> field id => what the user entered */
    private array $values = [];

    public function __construct(Payload $data, Bot $bot)
    {
        parent::__construct($data, $bot);

        $modal = $data->get('data');
        $this->modalId = $modal->string('custom_id');

        // A field sits inside a label, or inside an action row in older modals
        foreach ($modal->list('components') as $row) {
            $fields = $row->has('component') ? [$row->get('component')] : $row->list('components');
            foreach ($fields as $field) {
                $this->values[$field->string('custom_id')] = $field->optionalString('value') ?? '';
            }
        }
    }

    /** What the user entered in a field; null for a field the modal does not have. */
    public function value(string $id): ?string
    {
        return $this->values[$id] ?? null;
    }
}

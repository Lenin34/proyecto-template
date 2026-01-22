<?php

namespace App\DTO;

class FormTemplateFieldDTO
{
    public int $id;
    public string $label;
    public string $name;
    public string $type;
    public bool $required;
    public $options;
    public int $position;
    public ?string $help;
    public bool $multiple;
    public ?int $cols;
    public ?int $textarea_cols;

    public function __construct(
        int $id,
        string $label,
        string $name,
        string $type,
        bool $required,
        $options,
        int $position,
        ?string $help,
        bool $multiple,
        ?int $cols,
        ?int $textarea_cols
    ) {
        $this->id = $id;
        $this->label = $label;
        $this->name = $name;
        $this->type = $type;
        $this->required = $required;
        $this->options = $options;
        $this->position = $position;
        $this->help = $help;
        $this->multiple = $multiple;
        $this->cols = $cols;
        $this->textarea_cols = $textarea_cols;
    }
}

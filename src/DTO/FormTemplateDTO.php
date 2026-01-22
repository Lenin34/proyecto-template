<?php

namespace App\DTO;

class FormTemplateDTO
{
    public int $id;
    public string $name;
    public string $description;
    public string $created_at;
    public string $updated_at;
    /** @var FormTemplateFieldDTO[] */
    public array $fields;

    public function __construct(
        int $id,
        string $name,
        string $description,
        string $created_at,
        string $updated_at,
        array $fields = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
        $this->fields = $fields;
    }
}

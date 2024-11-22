<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension\QueryFilter;

class CamelizeNamingStrategy implements NamingStrategyInterface
{
    public function columnName(string $field): string
    {
        return $this->camelize($field);
    }

    /**
     * Converts a word into the format for a Doctrine class name. Converts 'table_name' to 'TableName'.
     */
    public function classify(string $word): string
    {
        return str_replace([' ', '_', '-'], '', ucwords($word, ' _-'));
    }

    /**
     * Camelizes a word. This uses the classify() method and turns the first character to lowercase.
     */
    public function camelize(string $word): string
    {
        return lcfirst($this->classify($word));
    }
}

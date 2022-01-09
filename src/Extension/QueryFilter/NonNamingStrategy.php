<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension\QueryFilter;

class NonNamingStrategy implements NamingStrategyInterface
{
    public function columnName(string $field): string
    {
        return $field;
    }
}

<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension\QueryFilter;

interface NamingStrategyInterface
{

    /**
     * Column name
     */
    public function columnName(string $field): string;
}

<?php


namespace Zfegg\ApiResourceDoctrine\Extension\QueryFilter;


interface NamingStrategyInterface
{

    /**
     * Column name
     */
    public function columnName(string $field): string;
}

<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension\QueryFilter;

interface QueryFilterInterface
{
    public const OPERATORS = [
        'eq'         => 'eq',
        'neq'        => 'neq',
        'lt'         => 'lt',
        'lte'        => 'lte',
        'gt'         => 'gt',
        'gte'        => 'gte',
        'startswith' => 'like',
        'endswith'   => 'like',
        'contains'   => 'like',
        'notlike'    => 'notLike',
        'in'         => 'in',
        'notin'      => 'notIn',
        'isnull'     => 'isNull',
        'isnotnull'  => 'isNotNull',
    ];
}

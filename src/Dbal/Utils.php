<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Dbal;

use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;

class Utils
{

    public static function getQueryParts(DBALQueryBuilder $query, string $part): ?array
    {
        /** @var DBALQueryBuilder $query */
        foreach (get_mangled_object_vars($query) as $key => $val) {
            if (str_ends_with($key, $part)) {
                return $val;
            }
        }

        return null;
    }
}

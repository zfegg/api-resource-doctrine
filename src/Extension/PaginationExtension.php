<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension;

use Zfegg\ApiResourceDoctrine\Dbal\Paginator as DbalPaginator;
use Zfegg\ApiResourceDoctrine\ORM\Paginator as ORMPaginator;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;

class PaginationExtension implements ExtensionInterface
{

    /**
     * PaginationExtension constructor.
     * @param int[] $pageSizeRange
     */
    public function __construct(
        protected int $pageSize = 20,
        protected array $pageSizeRange = [20, 50, 100, 200],
        protected ?array $disabledFormats = [],
        protected bool $pageable = false,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getList($query, string $table, array $context)
    {
        if ($this->pageable
            && isset($context['query']['pageable'])
            && boolval($context['query']['pageable']) === false
        ) {
            return ;
        }

        if (in_array($context['format'] ?? '', $this->disabledFormats)) {
            return ;
        }

        $page = $context['query']['page'] ?? 1;
        $pageSize = $this->getAllowedPageSize((int)($context['query']['page_size'] ?? 0));

        $query->setFirstResult(($page - 1) * $pageSize);
        $query->setMaxResults($pageSize);

        $class = $query instanceof ORMQueryBuilder ? ORMPaginator::class : DbalPaginator::class;
        $paginator = new $class($query);

        return $paginator;
    }

    protected function getAllowedPageSize(int $pageSize): int
    {
        if (in_array($pageSize, $this->pageSizeRange)) {
            return $pageSize;
        }

        return $this->pageSize;
    }

    /**
     * @inheritdoc
     */
    public function get($query, string $table, array $context)
    {
    }
}

<?php


namespace Zfegg\ApiResourceDoctrine\Factory;


use Psr\Container\ContainerInterface;
use Zfegg\ApiResourceDoctrine\Extension\CursorPaginationExtension;
use Zfegg\ApiResourceDoctrine\Extension\DefaultQueryExtension;
use Zfegg\ApiResourceDoctrine\Extension\ExtensionsFactory;
use Zfegg\ApiResourceDoctrine\Extension\PaginationExtension;
use Zfegg\ApiResourceDoctrine\Extension\QueryByContextExtension;
use Zfegg\ApiResourceDoctrine\Extension\QueryFilter;
use Zfegg\ApiResourceDoctrine\Extension\SortExtension;

class ExtensionsFactoryFactory
{
    public function __invoke(ContainerInterface $container): ExtensionsFactory
    {
        return new ExtensionsFactory(
            $container,
            [
                'abstract_factories' => [
                    ExtensionAbstractFactory::class,
                ],
                'aliases' =>  [
                    'cursor_pagination' => CursorPaginationExtension::class,
                    'pagination' => PaginationExtension::class,
                    'default_query' => DefaultQueryExtension::class,
                    'query_by_context' => QueryByContextExtension::class,
                    'query_filter' => QueryFilter\DefaultQueryFilter::class,
                    'json_api_query_filter' => QueryFilter\JsonApiQueryFilter::class,
                    'kendo_query_filter' => QueryFilter\KendoQueryFilter::class,
                    'sort' => SortExtension::class,
                ],
            ]
        );
    }
}

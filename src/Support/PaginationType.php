<?php

declare(strict_types=1);

namespace Sowork\GraphQL\Support;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type as GraphQLType;

class PaginationType extends ObjectType {

    public function __construct($typeName, $customName = null)
    {
        $name = $customName ?: $typeName . 'Pagination';

        $config = [
            'name'  => $name,
            'fields' => $this->getPaginationFields($typeName)
        ];

        parent::__construct($config);
    }

    protected function getPaginationFields($typeName): array
    {
        return [
            'data' => [
                'type'          => GraphQLType::listOf(graphql_app('graphql')->type($typeName)),
                'description'   => 'List of items on the current page',
                'resolve'       => function($data) { return $data->items;  },
            ],
            'current' => [ // 当前页
                'type'          => GraphQLType::nonNull(GraphQLType::int()),
                'description'   => 'Current page of the cursor',
                'resolve'       => function($data) { return $data->current; },
                'selectable'    => false,
            ],
            'first' => [ // 第一页
                'type'          => GraphQLType::nonNull(GraphQLType::int()),
                'description'   => 'first page of the cursor',
                'resolve'       => function($data) { return $data->first; },
                'selectable'    => false,
            ],
            'before' => [ // 上一页
                'type'          => GraphQLType::nonNull(GraphQLType::int()),
                'description'   => 'before of items returned per page',
                'resolve'       => function($data) { return $data->before; },
                'selectable'    => false,
            ],
            'next' => [ // 下一页
                'type'          => GraphQLType::nonNull(GraphQLType::int()),
                'description'   => 'next of items returned per page',
                'resolve'       => function($data) { return $data->next; },
                'selectable'    => false,
            ],
            'total_pages' => [ // 总页数
                'type'          => GraphQLType::nonNull(GraphQLType::int()),
                'description'   => 'total_pages of total items selected by the query',
                'resolve'       => function($data) { return $data->total_pages; },
                'selectable'    => false,
            ],
            'total_items' => [ // 总条目数
                'type'          => GraphQLType::nonNull(GraphQLType::int()),
                'description'   => 'total_items of the first item returned',
                'resolve'       => function($data) { return $data->total_items; },
                'selectable'    => false,
            ],
        ];
    }

}

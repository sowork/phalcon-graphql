<?php

declare(strict_types=1);

return [
    /**
     * 开发模式
     */
    'debug' => true,

    /**
     * 路由前缀
     */
    'prefix' => '/graphql',

    /**
     * 是否默认注入路由
     */
    'isInjectRoutes' => true,

    /**
     * graphql自定义请求入口路由定义
     * 默认路由是 prefix/{graphql_schema?}
     * 例如：
     * 'routes' => 'path/to/query/{graphql_schema?}',
     * 或者
     * 'routes' => [
     *      'query' => 'query/{graphql_schema?}'
     *      'mutation' => 'mutation/{graphql_schema?}'
     * ]
     */
    'routes' => '{graphql_schema?}',

    /**
     * graphql请求路由默认控制器和操作配置
     * 例如：
     * 'controllers' => 'Sowork\GraphQL\Http\Controllers\Graphql::query',
     * 或者
     * 'controllers' => [
     *      'query' => 'Sowork\GraphQL\Http\Controllers\Graphql::query',
     *      'mutation' => 'Sowork\GraphQL\Http\Controllers\Graphql::mutation',
     * ]
     */
    'controllers' => 'Sowork\GraphQL\Http\Controllers\GraphQL::query',

    /**
     * 默认的schema
     */
    'default_schema' => 'default',

    /**
     * schema配置
     * 多模块多入口schema配置
     * 当请求url为： prefix/routes/{graphql_schema}时
     * 当graphql_schema=default，会默认取以下default配置的query和mutation
     * 当graphql_schema=admin，会默认取以下admin配置的query和mutation
     */
    'schemas' => [
        'default' => [
            'query' => [
                // 'example_query' => ExampleQuery::class,
            ],
            'mutation' => [
                // 'example_mutation'  => ExampleMutation::class,
            ]
        ],
        'user/yy' => [
            'query' => [
                // 'example_query' => ExampleQuery::class,
            ],
            'mutation' => [
                // 'example_mutation'  => ExampleMutation::class,
            ]
        ],
    ],

    /*
     * You can define your own pagination type.
     * Reference \Rebing\GraphQL\Support\PaginationType::class
     */
    'pagination_type' => \Sowork\GraphQL\Support\PaginationType::class,

    /**
     * 自定义graphql类型
     */
    'types' => [
        // 'example'           => ExampleType::class,
        // 'relation_example'  => ExampleRelationType::class,
    ],

    'error_formatter' => ['\Sowork\GraphQL\GraphQL', 'formatError'],

    'errors_handler' => ['\Sowork\GraphQL\GraphQL', 'handleErrors'],

    /**
     * 参数的key命名字符串
     */
    'params_key'    => 'variables',

    /**
     * 验证服务名称
     */
    'validation_service_key' => 'validation',

    /**
     * 验证服务调用的方法名称
     */
    'validation_method_key' => 'validate',
];
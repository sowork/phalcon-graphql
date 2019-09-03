<?php

declare(strict_types=1);

namespace Sowork\GraphQL\Http\Controllers;

use Phalcon\Mvc\Controller;
use Sowork\GraphQL\GraphQLUploadMiddleware;

/**
 * graphql请求路由处理类
 * Class GraphqlController
 * @package Sowork\GraphQL\Http\Controllers
 */
class GraphQLController extends Controller
{
    /**
     * @param mixed ...$schema
     * @return string
     * @throws \GraphQL\Server\RequestError
     */
    public function queryAction(...$schema): string
    {
        $schema = implode('/', $schema);
        if (!$schema) {
            $schema = graphql_config('graphql.default_schema');
        }
        $middleware = new GraphQLUploadMiddleware();
        $body = $middleware->processRequest($this->request);
        // 如果query没有发现，意味着这是一个批量请求
        $batch = $body['query'] ? [$body] : $body;

        $completedQueries = [];
        $paramsKey = graphql_config('graphql.params_key');
        $opts = [
            'context' => [],
            'schema'  => $schema,
        ];

        foreach ($batch as $batchItem) {
            $query = $batchItem['query'];
            $params = $batchItem[$paramsKey] ?? null;
            if (is_string($params)) {
                $params = json_decode($params, true);
            }
            $completedQueries[] = $this->graphql->query($query, $params, array_merge($opts, [
                'operationName' => $batchItem['operationName'] ?? null,
            ]));
        }
        return !$body['query'] ? json_encode($completedQueries) : json_encode($completedQueries[0]);
    }
}
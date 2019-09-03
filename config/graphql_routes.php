<?php

declare(strict_types=1);

$di = \Phalcon\Di::getDefault();
if (graphql_config('graphql.isInjectRoutes')) {
    $customGraphQLRouteSegment = graphql_config('graphql.routes');
    if (is_array($customGraphQLRouteSegment)) {
        $queryGraphQLRouteSegment = $customGraphQLRouteSegment['query'];
        $mutationGraphQLRouteSegment = $customGraphQLRouteSegment['mutation'];
    } else {
        $queryGraphQLRouteSegment = $customGraphQLRouteSegment;
        $mutationGraphQLRouteSegment = $customGraphQLRouteSegment;
    }

    $controllers = graphql_config('graphql.controllers') ?? 'Sowork\GraphQL\Http\Controllers\GraphQLController::query';
    $queryGraphQLController = '';
    $mutationGraphQLController = '';
    if (is_array($controllers)) {
        $queryGraphQLController = $controllers['query'];
        $mutationGraphQLController = $controllers['mutation'];
    } else {
        $queryGraphQLController = $controllers;
        $mutationGraphQLController = $controllers;
    }

    /* @var $routerGroup \Phalcon\Mvc\Router\Group */
    $routerGroup = $di->getRouterGroup();
    $routerGroup->setPrefix(graphql_config('graphql.prefix'));
    /* @var $router Router */
    $router = $di->getRouter();

    $schemaParameterPattern = '/\{\s*graphql\_schema\s*\?\s*\}/';
    if ($queryGraphQLRouteSegment) {
        if(preg_match($schemaParameterPattern, $queryGraphQLRouteSegment)) {
            $routerGroup->add(preg_replace($schemaParameterPattern, '', $queryGraphQLRouteSegment), $queryGraphQLController);
        }

        foreach(graphql_config('graphql.schemas') as $name => $schema)
        {
            $route = $routerGroup->add(
                Sowork\GraphQL\GraphQL::routeNameTransformer($name, $schemaParameterPattern, $queryGraphQLRouteSegment),
                $queryGraphQLController
            );
        }
    }

    if ($mutationGraphQLRouteSegment) {
        if(preg_match($schemaParameterPattern, $queryGraphQLRouteSegment)) {
            $routerGroup->add(preg_replace($schemaParameterPattern, '', $mutationGraphQLRouteSegment),
                $mutationGraphQLController);
        }

        foreach(graphql_config('graphql.schemas') as $name => $schema)
        {
            $route = $routerGroup->add(
                Sowork\GraphQL\GraphQL::routeNameTransformer($name, $schemaParameterPattern, $mutationGraphQLRouteSegment),
                $mutationGraphQLController
            );
        }
    }

    /* 挂载路由 */
    $router->mount($routerGroup);
}
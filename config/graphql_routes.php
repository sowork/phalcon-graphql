<?php

$di = \Phalcon\Di::getDefault();
$defaultConfig = require_once __DIR__ . '/graphql.php';
if (graphql_config('graphql.isInjectRoutes')) {
    $customGrqphqlRouteSegment = graphql_config('graphql.routes');
    if (is_array($customGrqphqlRouteSegment)) {
        $queryGrqphqlRouteSegment = $customGrqphqlRouteSegment['query'];
        $mutationGrqphqlRouteSegment = $customGrqphqlRouteSegment['mutation'];
    } else {
        $queryGrqphqlRouteSegment = $customGrqphqlRouteSegment;
        $mutationGrqphqlRouteSegment = $customGrqphqlRouteSegment;
    }

    $controllers = graphql_config('graphql.controllers') ?? 'Sowork\GraphQL\Http\Controllers\Graphql::query';
    $queryGrqphqlController = '';
    $mutationGrqphqlController = '';
    if (is_array($controllers)) {
        $queryGrqphqlController = $controllers['query'];
        $mutationGrqphqlController = $controllers['mutation'];
    } else {
        $queryGrqphqlController = $controllers;
        $mutationGrqphqlController = $controllers;
    }

    /* @var $routerGroup \Phalcon\Mvc\Router\Group */
    $routerGroup = $di->getRouterGroup();
    $routerGroup->setPrefix(graphql_config('graphql.prefix'));
    /* @var $router Router */
    $router = $di->getRouter();

    $schemaParameterPattern = '/\{\s*graphql\_schema\s*\?\s*\}/';
    if ($queryGrqphqlRouteSegment) {
        if(preg_match($schemaParameterPattern, $queryGrqphqlRouteSegment)) {
            $routerGroup->add(preg_replace($schemaParameterPattern, '', $queryGrqphqlRouteSegment), $queryGrqphqlController);
        }

        foreach(graphql_config('graphql.schemas') as $name => $schema)
        {
            $route = $routerGroup->add(
                Sowork\GraphQL\GraphQL::routeNameTransformer($name, $schemaParameterPattern, $queryGrqphqlRouteSegment),
                $queryGrqphqlController
            );
        }
    }

    if ($mutationGrqphqlRouteSegment) {
        if(preg_match($schemaParameterPattern, $queryGrqphqlRouteSegment)) {
            $routerGroup->add(preg_replace($schemaParameterPattern, '', $mutationGrqphqlRouteSegment),
                $mutationGrqphqlController);
        }

        foreach(graphql_config('graphql.schemas') as $name => $schema)
        {
            $route = $routerGroup->add(
                Sowork\GraphQL\GraphQL::routeNameTransformer($name, $schemaParameterPattern, $mutationGrqphqlRouteSegment),
                $mutationGrqphqlController
            );
        }
    }

    /* 挂载路由 */
    $router->mount($routerGroup);
}
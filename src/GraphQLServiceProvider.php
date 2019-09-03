<?php

declare(strict_types=1);

namespace Sowork\GraphQL;

use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Validation;
use Phalcon\DiInterface;

/**
 * graphql服务提供者
 * Class GraphqlServiceProvider
 * @package Sowork\GraphQL
 */
class GraphQLServiceProvider implements ServiceProviderInterface
{
    /**
     * @param \Phalcon\DiInterface $di
     */
    public function register(DiInterface $di): void
    {
        $di->setShared('graphql', new GraphQL());
        $di->setShared('validation', new Validation());
        $this->mergeConfig();
        $this->bootTypes($di);
        $this->bootSchemas($di);
        $this->bootRouter();
    }

    public function bootTypes(DiInterface $di): void
    {
        $configTypes = graphql_config('graphql.types');
        foreach($configTypes as $name => $type)
        {
            if(is_numeric($name))
            {
                $di['graphql']->addType($type);
            }
            else
            {
                $di['graphql']->addType($type, $name);
            }
        }
    }

    public function bootSchemas(DiInterface $di): void
    {
        $configSchemas = graphql_config('graphql.schemas')->toArray();
        foreach ($configSchemas as $name => $schema) {
            $di['graphql']->addSchema($name, $schema);
        }
    }

    public function bootRouter(): void
    {
        require_once __DIR__ . '/../config/graphql_routes.php';
    }

    public function mergeConfig(): void
    {
        $configPath = __DIR__ . '/../config/graphql.php';
        $graphqlConfig = graphql_config('graphql');

        graphql_config()->offsetSet('graphql', array_merge(include $configPath, $graphqlConfig ? $graphqlConfig->toArray() : []));
    }
}
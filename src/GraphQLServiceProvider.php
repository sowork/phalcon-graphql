<?php

namespace Sowork\GraphQL;

use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Mvc\Model\Manager;

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
    public function register(\Phalcon\DiInterface $di)
    {
        $di->setShared('graphql', new GraphQL());
        $this->mergeConfig();
        $this->bootTypes($di);
        $this->bootSchemas($di);
        $this->bootRouter();
    }

    public function bootTypes($di)
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

    public function bootSchemas($di)
    {
        $configSchemas = graphql_config('graphql.schemas')->toArray();
        foreach ($configSchemas as $name => $schema) {
            $di['graphql']->addSchema($name, $schema);
        }
    }

    public function bootRouter()
    {
        require_once __DIR__ . '/../config/graphql_routes.php';
    }

    public function mergeConfig()
    {
        $configPath = __DIR__ . '/../config/graphql.php';
        $graphqlConfig = graphql_config('graphql');

        graphql_config()->offsetSet('graphql', array_merge(include $configPath, $graphqlConfig ? $graphqlConfig->toArray() : []));
    }
}
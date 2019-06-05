<?php

namespace Sowork\GraphQL;

use GraphQL\Error\Debug;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\Type\Schema as DefaultSchema;
use Sowork\GraphQL\Error\ValidationError;
use Sowork\GraphQL\Exceptions\SchemaNotFound;

/**
 * Class GraphQL
 * @package Sowork\GraphQL
 */
class GraphQL
{
    protected $app;
    protected $schemas = [];
    protected $types = [];
    protected $typesInstances = [];

    /**
     * @param       $query
     * @param array $params
     * @param array $opts
     * @param bool  $debugMode
     * @return mixed
     * @throws \Exception
     */
    public function query($query, $params = [], $opts = [], $debugMode = false)
    {
        return $this->queryAndReturnResult($query, $params, $opts, $debugMode);
    }

    /**
     * @param       $query
     * @param array $params
     * @param array $opts
     * @param bool  $debugMode
     * @return mixed
     * @throws \Exception
     */
    public function queryAndReturnResult($query, $params = [], $opts = [], $debugMode = false)
    {
        $context = $opts['context'];
        $schemaName = $opts['schema'];
        $operationName = $opts['operationName'];

        $schema = $this->schema($schemaName);

        $errorFormatter = (array) graphql_config('graphql.error_formatter', [static::class, 'formatError']);
        $errorsHandler = (array) graphql_config('graphql.errors_handler', [static::class, 'handleErrors']);

        $result = GraphQLBase::executeQuery($schema, $query, null, $context, $params, $operationName);
//            ->setErrorsHandler()
//            ->setErrorFormatter();
        return $result->toArray();
        print_r($result->toArray());
    }

    /**
     * @param $schema
     * @return DefaultSchema
     * @throws \Exception
     */
    public function schema($schema)
    {
        if ($schema instanceof DefaultSchema) {
            return $schema;
        }

        $this->typesInstances = [];
        foreach ($this->types as $name => $type) {
            $this->type($name);
        }

        $schema = $this->getSchemaConfiguration($schema);

        $schemaQuery = $schema['query'] ?? [];
        $schemaMutation = $schema['mutation'] ?? [];
        $schemaSubscription = $schema['subscription'] ?? [];
        $schemaTypes = $schema['types'] ?? [];

        //Get the types either from the schema, or the global types.
        $types = [];
        if (sizeof($schemaTypes)) {
            foreach ($schemaTypes as $name => $type) {
                $objectType = $this->objectType($type, is_numeric($name) ? [] : [
                    'name' => $name
                ]);
                $this->typesInstances[$name] = $objectType;
                $types[] = $objectType;
            }
        } else {
            foreach ($this->types as $name => $type) {
                $types[] = $this->type($name);
            }
        }

        $query = $this->objectType($schemaQuery, [
            'name' => 'Query'
        ]);

        $mutation = $this->objectType($schemaMutation, [
            'name' => 'Mutation'
        ]);

        $subscription = $this->objectType($schemaSubscription, [
            'name' => 'Subscription'
        ]);

        return new Schema([
            'query'        => $query,
            'mutation'     => !empty($schemaMutation) ? $mutation : null,
            'subscription' => !empty($schemaSubscription) ? $subscription : null,
            'types'        => $types
        ]);
    }

    public function type($name, $fresh = false)
    {
        if (!isset($this->types[$name])) {
            throw new \Exception('Type ' . $name . ' not found.');
        }

        if (!$fresh && isset($this->typesInstances[$name])) {
            return $this->typesInstances[$name];
        }

        /* @var $type Type */
        $type = $this->types[$name];
        if (!is_object($type)) {
            $type = new $type;
        }

        $instance = $type->toType();
        $this->typesInstances[$name] = $instance;

        return $instance;
    }

    public function objectType($type, $opts = [])
    {
        // If it's already an ObjectType, just update properties and return it.
        // If it's an array, assume it's an array of fields and build ObjectType
        // from it. Otherwise, build it from a string or an instance.
        $objectType = null;
        if ($type instanceof ObjectType) {
            $objectType = $type;
            foreach ($opts as $key => $value) {
                if (property_exists($objectType, $key)) {
                    $objectType->{$key} = $value;
                }
                if (isset($objectType->config[$key])) {
                    $objectType->config[$key] = $value;
                }
            }
        } elseif (is_array($type)) {
            $objectType = $this->buildObjectTypeFromFields($type, $opts);
        } else {
//            $objectType = $this->buildObjectTypeFromClass($type, $opts);
        }

        return $objectType;
    }

    protected function buildObjectTypeFromFields($fields, $opts = [])
    {
        $typeFields = [];
        foreach ($fields as $name => $field) {
            if (is_string($field)) {
                $field = new $field;
                $name = is_numeric($name) ? $field->name : $name;
                $field->name = $name;
                $field = $field->toArray();
            } else {
                $name = is_numeric($name) ? $field['name'] : $name;
                $field['name'] = $name;
            }
            $typeFields[$name] = $field;
        }

        return new ObjectType(array_merge([
            'fields' => $typeFields
        ], $opts));
    }

    public static function formatError(Error $e)
    {
        $debug = graphql_config('graphql.debug') ? (Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE) : 0;
        $formatter = FormattedError::prepareFormatter(null, $debug);
        $error = $formatter($e);

        $previous = $e->getPrevious();
        if($previous && $previous instanceof ValidationError)
        {
            $error['validation'] = $previous->getValidatorMessages();
        }

        return $error;
    }

    public static function handleErrors(array $errors, callable $formatter)
    {
        return array_map($formatter, $errors);
    }

    public function addSchema($name, $schema)
    {
        $this->mergeSchemas($name, $schema);
    }

    public function mergeSchemas($name, $schema)
    {
        if (isset($this->schemas[$name]) && $this->schemas[$name]) {
            $this->schemas[$name] = array_merge_recursive($this->schemas[$name], $schema);
        } else {
            $this->schemas[$name] = $schema;
        }
    }

    public function addType($class, $name = null)
    {
        if (!$name) {
            $type = is_object($class) ? $class : new $class;
            $name = $type->name;
        }

        $this->types[$name] = $class;
    }

    /**
     * Check if the schema expects a nest URI name and return the formatted version
     * Eg. 'user/me'
     * will open the query path /graphql/user/me
     *
     * @param $name
     * @param $schemaParameterPattern
     * @param $queryRoute
     *
     * @return mixed
     */
    public static function routeNameTransformer($name, $schemaParameterPattern, $queryRoute)
    {
        $multiLevelPath = explode('/', $name);
        $routeName = null;
        $schemaName = null;

        if (count($multiLevelPath) > 1) {
            foreach ($multiLevelPath as $multiName) {
                $schemaName = !$schemaName ? null : $schemaName . '/';
                $schemaName .= '{' . $multiName . '}';
            }
            $routeName = preg_replace($schemaParameterPattern, $schemaName, $queryRoute);
        }

        return ('/' . ($routeName ?: preg_replace($schemaParameterPattern, '{' . $name . '}', $queryRoute)));
    }

    protected function getSchemaConfiguration($schema)
    {
        $schemaName = is_string($schema) ? $schema : graphql_config('graphql.default_schema', 'default');

        if (!is_array($schema) && !isset($this->schemas[$schemaName])) {
            throw new SchemaNotFound('Type ' . $schemaName . ' not found.');
        }

        return is_array($schema) ? $schema : $this->schemas[$schemaName];
    }
}

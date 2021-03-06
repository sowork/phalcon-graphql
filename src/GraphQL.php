<?php

declare(strict_types=1);

namespace Sowork\GraphQL;

use GraphQL\Error\Debug;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Sowork\GraphQL\Error\ValidationError;
use Sowork\GraphQL\Exceptions\SchemaNotFound;
use Sowork\GraphQL\Support\PaginationType;
use Sowork\GraphQL\Support\Type;
use GraphQL\Type\Definition\Type as BaseType;

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
    public function query($query, $params = [], $opts = [], $debugMode = false): array
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
    public function queryAndReturnResult($query, $params = [], $opts = [], $debugMode = false): array
    {
        $context = $opts['context'];
        $schemaName = $opts['schema'];
        $operationName = $opts['operationName'];
        $schema = $this->schema($schemaName);
        $errorsHandler = graphql_config('graphql.errors_handler');
        $errorsHandler = $errorsHandler ? $errorsHandler->toArray() : [
            static::class,
            'handleErrors'
        ];
        $errorFormatter = graphql_config('graphql.error_formatter');
        $errorFormatter = $errorFormatter ? $errorFormatter->toArray() : [
            static::class,
            'formatError'
        ];
        $result = GraphQLBase::executeQuery($schema, $query, null, $context, $params, $operationName)
            ->setErrorsHandler($errorsHandler)
            ->setErrorFormatter($errorFormatter);
        return $result->toArray();
    }

    /**
     * @param $schema
     * @return Schema
     * @throws \Exception
     */
    public function schema($schema): Schema
    {
        if ($schema instanceof Schema) {
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
        if (count($schemaTypes)) {
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

    public function type($name, $fresh = false): BaseType
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

    public function objectType($type, $opts = []): BaseType
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

    protected function buildObjectTypeFromFields($fields, $opts = []): ObjectType
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

    public function paginate($typeName, $customName = null): BaseType
    {
        $name = $customName ?: $typeName . '_pagination';

        if (!isset($this->typesInstances[$name])) {
            $paginationType = graphql_config('graphql.pagination_type', PaginationType::class);
            $this->typesInstances[$name] = new $paginationType($typeName, $customName);
        }
        return $this->typesInstances[$name];
    }

    public static function formatError(Error $e): array
    {
        $debug = graphql_config('graphql.debug') ? (Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE) : 0;
        $formatter = FormattedError::prepareFormatter(null, $debug);
        $error = $formatter($e);

        $previous = $e->getPrevious();
        if($previous && $previous instanceof ValidationError)
        {
            $msg = [];
            foreach ($previous->getValidatorMessages() as $message) {
                $msg[$message->getField()] = $message->getMessage();
            }
            $error['extensions']['validation'] = $msg;
        }

        return $error;
    }

    public static function handleErrors(array $errors, callable $formatter): array
    {
        return array_map($formatter, $errors);
    }

    public function addSchema($name, $schema): void
    {
        $this->mergeSchemas($name, $schema);
    }

    public function mergeSchemas($name, $schema): void
    {
        if (isset($this->schemas[$name]) && $this->schemas[$name]) {
            $this->schemas[$name] = array_merge_recursive($this->schemas[$name], $schema);
        } else {
            $this->schemas[$name] = $schema;
        }
    }

    public function addType($class, $name = null): void
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
    public static function routeNameTransformer($name, $schemaParameterPattern, $queryRoute): string
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

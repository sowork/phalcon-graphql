<?php

declare(strict_types=1);

namespace Sowork\GraphQL\Support;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type as GraphqlType;
use Sowork\GraphQL\Support\Contracts\TypeConvertible;

/**
 * Class Type
 * @package Sowork\GraphQL
 */
abstract class Type implements TypeConvertible
{
    protected $attributes = [];

    public function attributes(): array
    {
        return [];
    }

    public function fields(): array
    {
        return [];
    }

    public function interfaces(): array
    {
        return [];
    }

    protected function getFieldResolver($name, $field): ?callable
    {
        if (isset($field['resolve'])) {
            return $field['resolve'];
        }

        $resolveMethod = 'resolve' . studly_case($name) . 'Field';

        if (method_exists($this, $resolveMethod)) {
            $resolver = array($this, $resolveMethod);
            return function () use ($resolver) {
                $args = func_get_args();
                return call_user_func_array($resolver, $args);
            };
        }

        return null;
    }

    public function getFields(): array
    {
        $fields = $this->fields();
        $allFields = [];
        foreach ($fields as $name => $field) {
            if (is_string($field)) {
                $field = new $field;
                $field->name = $name;
                $allFields[$name] = $field->toArray();
            } elseif ($field instanceof FieldDefinition) {
                $allFields[$field->name] = $field;
            } else {
                $resolver = $this->getFieldResolver($name, $field);
                if ($resolver) {
                    $field['resolve'] = $resolver;
                }
                $allFields[$name] = $field;
            }
        }

        return $allFields;
    }

    /**
     * Get the attributes from the container.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        $attributes = $this->attributes();
        $interfaces = $this->interfaces();

        $attributes = array_merge($this->attributes, [
            'fields' => function () {
                return $this->getFields();
            }
        ], $attributes);

        if (count($interfaces)) {
            $attributes['interfaces'] = $interfaces;
        }

        return $attributes;
    }

    /**
     * Convert the Fluent instance to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->getAttributes();
    }

    public function toType(): GraphqlType
    {
        return new ObjectType($this->toArray());
    }

    /**
     * Dynamically retrieve the value of an attribute.
     *
     * @param  string $key
     * @return mixed
     */
    public function __get($key)
    {
        $attributes = $this->getAttributes();
        return isset($attributes[$key]) ? $attributes[$key] : null;
    }

    public function __set(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }
}
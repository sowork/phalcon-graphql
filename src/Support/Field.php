<?php

declare(strict_types=1);

namespace Sowork\GraphQL\Support;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\WrappingType;
use Phalcon\Validation;
use Sowork\GraphQL\Error\AuthorizationError;
use Sowork\GraphQL\Error\ValidationError;
use Sowork\GraphQL\SelectFields;
use Phalcon\Validation\Validator;
use GraphQL\Type\Definition\Type as GraphqlType;

abstract class Field
{
    protected $attributes = [];

    /**
     * Override this in your queries or mutations
     * to provide custom authorization
     */
    public function authorize(array $args): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return [];
    }

    public function type(): GraphqlType
    {
        return null;
    }

    public function args(): array
    {
        return [];
    }

    /**
     * Define custom Laravel Validator messages as per Laravel 'custom error messages'
     * @param array $args submitted arguments
     * @return array
     */
    public function validationErrorMessages(array $args = []): array
    {
        return [];
    }

    protected function rules(array $args = []): array
    {
        return [];
    }

    public function getRules(): array
    {
        $arguments = func_get_args();
        $rules = call_user_func_array([
            $this,
            'rules'
        ], $arguments);
        $argsRules = [];
        foreach ($this->args() as $name => $arg) {
            if (isset($arg['rules'])) {
                if (is_callable($arg['rules'])) {
                    $argsRules[$name] = $this->resolveRules($arg['rules'], $arguments);
                } else {
                    $argsRules[$name] = $arg['rules'];
                }
            }
            if (isset($arg['type']) && ($arg['type'] instanceof NonNull || isset($arguments[0][$name]))) {
                $argsRules = array_merge($argsRules, $this->inferRulesFromType($arg['type'], $name, $arguments));
            }
        }
        return array_merge($argsRules, $rules);
    }

    public function resolveRules($rules, $arguments)
    {
        if (is_callable($rules)) {
            return call_user_func_array($rules, $arguments);
        }
        return $rules;
    }

    public function inferRulesFromType($type, $prefix, $resolutionArguments): array
    {
        $rules = [];
        // make sure we are dealing with the actual type
        if ($type instanceof NonNull) {
            $type = $type->getWrappedType();
        }
        // if it is an array type, add an array validation component
        if ($type instanceof ListOfType) {
            $prefix = "{$prefix}.*";
        }
        // make sure we are dealing with the actual type
        if ($type instanceof WrappingType) {
            $type = $type->getWrappedType();
        }
        // if it is an input object type - the only type we care about here...
        if ($type instanceof InputObjectType) {
            // merge in the input type's rules
            $rules = array_merge($rules, $this->getInputTypeRules($type, $prefix, $resolutionArguments));
        }
        // Ignore scalar types
        return $rules;
    }

    public function getInputTypeRules(InputObjectType $input, $prefix, $resolutionArguments): array
    {
        $rules = [];
        foreach ($input->getFields() as $name => $field) {
            $key = "{$prefix}.{$name}";
            // get any explicitly set rules
            if (isset($field->rules)) {
                $rules[$key] = $this->resolveRules($field->rules, $resolutionArguments);
            }
            // then recursively call the parent method to see if this is an
            // input object, passing in the new prefix
            if ($field->type instanceof InputObjectType) {
                // in case the field is a self reference we must not do
                // a recursive call as it will never stop
                if ($field->type->toString() == $input->toString()) {
                    continue;
                }
            }
            $rules = array_merge($rules, $this->inferRulesFromType($field->type, $key, $resolutionArguments));
        }
        return $rules;
    }

    protected function getResolver(): ?\Closure
    {
        if (!method_exists($this, 'resolve')) {
            return null;
        }

        $resolver = [$this, 'resolve'];
        $authorize = [$this, 'authorize'];

        return function() use ($resolver, $authorize){
            $arguments = func_get_args();
            // Get all given arguments
            if (!is_null($arguments[2]) && is_array($arguments[2])) {
                $arguments[1] = array_merge($arguments[1], $arguments[2]);
            }
            // Validate mutation arguments
            if(method_exists($this, 'getRules'))
            {
                $args = $arguments[1] ?? [];
                $rules = call_user_func_array([$this, 'getRules'], [$args]);
                if(count($rules))
                {

                    // allow our error messages to be customised
                    $customMessages = $this->validationErrorMessages($args);

                    /** @var Validation $validation */
                    $validation = graphql_app(graphql_config('graphql.validation_service_key') ?? 'validation');
                    $validatorMap = graphql_config()->path('validator');
                    foreach ($rules as $name => $ruleArr) {
                        foreach ($ruleArr as $rule) {
                            if (empty($validatorMap[$rule])) {
                                throw new \RuntimeException("unknown `{$rule}` validation rule");
                            }
                            $validator = new $validatorMap[$rule]([
                                'message' => $customMessages[$name] ?? null
                            ]);
                            if (!($validator instanceof Validator)) {
                                throw new \RuntimeException("`{$rule}` is not a standard Validator instance");
                            }
                            $validation->add($name, $validator);
                        }
                    };
                    $messages = $validation->validate($args);
                    if($messages->count()) {
                        throw new ValidationError('validation', $validation);
                    }
                }
            }

            // Authorize
            if(call_user_func($authorize, $arguments[1]) != true)
            {
                throw new AuthorizationError('Unauthorized');
            }

            // Add the 'selects and relations' feature as 5th arg
            if (isset($arguments[3])) {
                $arguments[] = function () use ($arguments): SelectFields {
                    return new SelectFields($arguments[3], $this->type(), $arguments[1]);
                };
            }
            return call_user_func_array($resolver, $arguments);
        };
    }

    /**
     * Get the attributes from the container.
     * @return array
     */
    public function getAttributes(): array
    {
        $attributes = $this->attributes();
        $attributes = array_merge($this->attributes, [
            'args' => $this->args()
        ], $attributes);
        $type = $this->type();
        if (isset($type)) {
            $attributes['type'] = $type;
        }
        $resolver = $this->getResolver();
        if (isset($resolver)) {
            $attributes['resolve'] = $resolver;
        }
        return $attributes;
    }

    /**
     * Convert the Fluent instance to an array.
     * @return array
     */
    public function toArray(): array
    {
        return $this->getAttributes();
    }

    /**
     * Dynamically retrieve the value of an attribute.
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
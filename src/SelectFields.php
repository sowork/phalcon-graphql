<?php

declare(strict_types=1);

namespace Sowork\GraphQL;

use Closure;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\Type as GraphqlType;
use Phalcon\Mvc\Model;
use Sowork\GraphQL\Support\PaginationType;

class SelectFields
{
    /** @var array */
    private $select = [];
    /** @var array */
    private $relations = [];
    /** @var array */
    private static $privacyValidations = [];
    const FOREIGN_KEY = 'foreignKey';

    /**
     * @param ResolveInfo $info
     * @param             $parentType
     * @param array       $queryArgs - arguments given with the query
     */
    public function __construct(ResolveInfo $info, GraphqlType $parentType, array $queryArgs)
    {
        if (!is_null($info->fieldNodes[0]->selectionSet)) {
            $requestedFields = $this->getFieldSelection($info, $queryArgs, 5);
            $fields = self::getSelectableFieldsAndRelations($queryArgs, $requestedFields, $parentType);
            $this->select = $fields[0];
            $this->relations = $fields[1];
        }
    }

    private function getFieldSelection(ResolveInfo $resolveInfo, array $args, int $depth): array
    {
        $resolveInfoFieldsAndArguments = new ResolveInfoFieldsAndArguments($resolveInfo);

        return [
            'args' => $args,
            'fields' => $resolveInfoFieldsAndArguments->getFieldsAndArgumentsSelection($depth),
        ];
    }

    /**
     * Retrieve the fields (top level) and relations that
     * will be selected with the query.
     *
     * @param  array  $queryArgs Arguments given with the query/mutation
     * @param  array  $requestedFields
     * @param  GraphqlType  $parentType
     * @param  Closure|null  $customQuery
     * @param  bool  $topLevel
     * @return array|Closure - if first recursion, return an array,
     *               where the first key is 'select' array and second is 'with' array.
     *               On other recursions return a closure that will be used in with
     */
    public static function getSelectableFieldsAndRelations(array $queryArgs, array $requestedFields, $parentType, ?\Closure $customQuery = null, $topLevel = true)
    {
        $select = [];
        $with = [];
        if (is_a($parentType, ListOfType::class)) {
            $parentType = $parentType->getWrappedType();
        }
//        $parentTable = self::getTableNameFromParentType($parentType);
        $primaryKey = self::getPrimaryKeyFromParentType($parentType);
        self::handleFields($queryArgs, $requestedFields, $parentType, $select, $with);
        // If a primary key is given, but not in the selects, add it
        if (!is_null($primaryKey)) {
            if (is_array($primaryKey)) {
                foreach ($primaryKey as $key) {
                    if (!in_array($key, $select)) {
                        $select[] = $key;
                    }
                }
            } else {
                if (!in_array($primaryKey, $select)) {
                    $select[] = $primaryKey;
                }
            }
        }
        if ($topLevel) {
            return [
                $select,
                $with
            ];
        } else {
            return function($query) use ($with, $select, $customQuery, $requestedFields){
                if ($customQuery) {
                    $query = $customQuery($requestedFields['args'], $query);
                }
                $query->columns($select);
                $query->with($with);
            };
        }
    }

    /**
     * Get the selects and withs from the given fields
     * and recurse if necessary.
     *
     * @param  array  $queryArgs Arguments given with the query/mutation
     * @param  array<string,mixed>  $requestedFields
     * @param  GraphqlType  $parentType
     * @param  array  $select Passed by reference, adds further fields to select
     * @param  array  $with Passed by reference, adds further relations
     */
    protected static function handleFields(array $queryArgs, array $requestedFields, GraphqlType $parentType, array &$select, array &$with): void
    {
        $parentTable = self::getTableNameFromParentType($parentType);
        foreach ($requestedFields['fields'] as $key => $field) {
            // Ignore __typename, as it's a special case
            if ($key === '__typename') {
                continue;
            }
            // Always select foreign key
            if ($field === self::FOREIGN_KEY) {
                self::addFieldToSelect($key, $select, $parentTable, false);
                continue;
            }
            // If field doesn't exist on definition we don't select it
            try {
                if (method_exists($parentType, 'getField')) {
                    $fieldObject = $parentType->getField($key);
                } else {
                    continue;
                }
            } catch(InvariantViolation $e) {
                continue;
            }
            // First check if the field is even accessible
            $canSelect = self::validateField($fieldObject, $queryArgs);
            if ($canSelect === true) {
                // Add a query, if it exists
                $customQuery = $fieldObject->config['query'] ?? null;
                // Check if the field is a relation that needs to be requested from the DB
                $queryable = self::isQueryable($fieldObject->config);
                // Pagination
                if (is_a($parentType, graphql_config('graphql.pagination_type', PaginationType::class))) {
                    self::handleFields($queryArgs, $field, $fieldObject->config['type']->getWrappedType(), $select, $with);
                } // With
                else if (is_array($field['fields']) && $queryable) {
                    if (isset($parentType->config['model'])) {
                        // Get the next parent type, so that 'with' queries could be made
                        // Both keys for the relation are required (e.g 'id' <-> 'user_id')
                        /** @var Model\Manager $manager */
                        $manager = graphql_app(Model\Manager::class);
                        $manager->load($parentType->config['model']);
                        $relation = $manager->getRelationByAlias($parentType->config['model'], $key);
                        if (!$relation) {
                            throw new \RuntimeException(sprintf('%s unknown relation: %s', $parentType->config['model'], $key));
                        }
                        $foreignKey = '';
                        if (method_exists($relation, 'getReferencedFields')) {
                            $foreignKey = $relation->getReferencedFields();
                        }
                        if (is_string($foreignKey)) {
                            $foreignKey = [$foreignKey];
                        }
                        foreach ($foreignKey as $foreign) {
                            $field['fields'][$foreign] = self::FOREIGN_KEY;
                        }
                        // New parent type, which is the relation
                        $newParentType = $parentType->getField($key)->config['type'];
                        self::addAlwaysFields($fieldObject, $field, $parentTable, true);
                        $with[$key] = self::getSelectableFieldsAndRelations($queryArgs, $field, $newParentType, $customQuery, false);
                    } else {
                        self::handleFields($queryArgs, $field, $fieldObject->config['type'], $select, $with);
                    }
                } // Select
                else {
                    $key = isset($fieldObject->config['alias']) ? $fieldObject->config['alias'] : $key;
                    $key = $key instanceof Closure ? $key() : $key;
                    self::addFieldToSelect($key, $select, $parentTable, false);
                    self::addAlwaysFields($fieldObject, $select, $parentTable);
                }
            } // If privacy does not allow the field, return it as null
            else if ($canSelect === null) {
                $fieldObject->resolveFn = function(){
                    return null;
                };
            } // If allowed field, but not selectable
            else if ($canSelect === false) {
                self::addAlwaysFields($fieldObject, $select, $parentTable);
            }
        }
        // If parent type is an interface or union we select all fields
        // because we don't know which other fields are required
        // from types which implement this interface
        if (is_a($parentType, UnionType::class)) {
            $select = ['*'];
        }
    }

    /**
     * Check the privacy status, if it's given.
     *
     * @param  FieldDefinition  $fieldObject
     * @param  array  $queryArgs  Arguments given with the query/mutation
     * @return bool|null `true`  if selectable
     *                   `false` if not selectable, but allowed
     *                   `null`  if not allowed
     */
    protected static function validateField(FieldDefinition $fieldObject, array $queryArgs): ?bool
    {
        $selectable = true;
        // If not a selectable field
        if (isset($fieldObject->config['selectable']) && $fieldObject->config['selectable'] === false) {
            $selectable = false;
        }
        if (isset($fieldObject->config['privacy'])) {
            $privacyClass = $fieldObject->config['privacy'];
            // If privacy given as a closure
            if (is_callable($privacyClass) && call_user_func($privacyClass, $queryArgs) === false) {
                $selectable = null;
            } // If Privacy class given
            else if (is_string($privacyClass)) {
                if (in_array($privacyClass, self::$privacyValidations)) {
                    $validated = self::$privacyValidations[$privacyClass];
                } else {
                    $validated = call_user_func([
                        graphql_app($privacyClass),
                        'fire'
                    ], $queryArgs);
                    self::$privacyValidations[$privacyClass] = $validated;
                }
                if (!$validated) {
                    $selectable = null;
                }
            }
        }
        return $selectable;
    }

    /**
     * Determines whether the fieldObject is queryable.
     *
     * @param array $fieldObject
     *
     * @return bool
     */
    private static function isQueryable(array $fieldObject): bool
    {
        return $fieldObject['is_relation'] ?? true === true;
    }

    /**
     * Add selects that are given by the 'always' attribute.
     *
     * @param  FieldDefinition  $fieldObject
     * @param  array  $select Passed by reference, adds further fields to select
     * @param  string|null  $parentTable
     * @param  bool  $forRelation
     */
    protected static function addAlwaysFields(FieldDefinition $fieldObject, array &$select, ?string $parentTable, bool $forRelation = false): void
    {
        if (isset($fieldObject->config['always'])) {
            $always = $fieldObject->config['always'];
            if (is_string($always)) {
                $always = explode(',', $always);
            }
            // Get as 'field' => true
            foreach ($always as $field) {
                self::addFieldToSelect($field, $select, $parentTable, $forRelation);
            }
        }
    }

    /**
     * @param  string  $field
     * @param  array  $select Passed by reference, adds further fields to select
     * @param  string|null  $parentTable
     * @param  bool  $forRelation
     */
    protected static function addFieldToSelect($field, array &$select, ?string $parentTable, bool $forRelation): void
    {
        if ($forRelation && !array_key_exists($field, $select)) {
            $select[$field] = true;
        } else if (!$forRelation && !in_array($field, $select)) {
//            $field = $parentTable ? ($parentTable.'.'.$field) : $field;
            if (!in_array($field, $select)) {
                $select[] = $field;
            }
        }
    }

    private static function getPrimaryKeyFromParentType($parentType): ?array
    {
        if (!isset($parentType->config['model'])) {
            return null;
        }
        /** @var Model $model */
        $model = graphql_app($parentType->config['model']);
        return $model->getModelsMetaData()->getPrimaryKeyAttributes($model);
    }

    private static function getTableNameFromParentType($parentType): ?string
    {
        return isset($parentType->config['model']) ? graphql_app($parentType->config['model'])->getSource() : null;
    }

    public function getSelect(): array
    {
        return $this->select;
    }

    public function getRelations(): array
    {
        return $this->relations;
    }
}
<?php
/**
 * @author: xingshenqiang<xingshenqiang@uniondrug.cn>
 * @date  :   2019-05-25
 */
namespace Sowork\GraphQL;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ResolveInfo;
use Phalcon\Mvc\Model;

class SelectFields
{
    /** @var array */
    private static $args = [];
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
     * @param array       $args - arguments given with the query
     */
    public function __construct(ResolveInfo $info, $parentType, array $args)
    {
        if (!is_null($info->fieldNodes[0]->selectionSet)) {
            self::$args = $args;
            $fields = self::getSelectableFieldsAndRelations($info->getFieldSelection(5), $parentType);
            $this->select = $fields[0];
            $this->relations = $fields[1];
        }
    }

    /**
     * Retrieve the fields (top level) and relations that
     * will be selected with the query
     * @param array $requestedFields
     * @param       $parentType
     * @param null  $customQuery
     * @param bool  $topLevel
     * @return array | Closure - if first recursion, return an array,
     *      where the first key is 'select' array and second is 'with' array.
     *      On other recursions return a closure that will be used in with
     */
    public static function getSelectableFieldsAndRelations(array $requestedFields, $parentType, $customQuery = null, $topLevel = true)
    {
        $select = [];
        $with = [];
        if (is_a($parentType, ListOfType::class)) {
            $parentType = $parentType->getWrappedType();
        }
//        $parentTable = self::getTableNameFromParentType($parentType);
        $primaryKey = self::getPrimaryKeyFromParentType($parentType);
        self::handleFields($requestedFields, $parentType, $select, $with);
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
            return function($query) use ($with, $select, $customQuery){
                if ($customQuery) {
                    $query = $customQuery(self::$args, $query);
                }
                $query->columns($select);
                $query->with($with);
            };
        }
    }

    /**
     * Get the selects and withs from the given fields
     * and recurse if necessary
     * @param array $requestedFields
     * @param       $parentType
     * @param array $select
     * @param array $with
     */
    protected static function handleFields(array $requestedFields, $parentType, array &$select, array &$with)
    {
        $parentTable = self::getTableNameFromParentType($parentType);
        foreach ($requestedFields as $key => $field) {
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
            $canSelect = self::validateField($fieldObject);
            if ($canSelect === true) {
                // Add a query, if it exists
                $customQuery = $fieldObject->config['query'] ?? null;
                // Check if the field is a relation that needs to be requested from the DB
                $queryable = self::isQueryable($fieldObject->config);
                // Pagination
                if (is_a($parentType, graphql_config('graphql.pagination_type', PaginationType::class))) {
                    self::handleFields($field, $fieldObject->config['type']->getWrappedType(), $select, $with);
                } // With
                else if (is_array($field) && $queryable) {
                    if (isset($parentType->config['model'])) {
                        // Get the next parent type, so that 'with' queries could be made
                        // Both keys for the relation are required (e.g 'id' <-> 'user_id')
                        /** @var Model\Manager $manager */
                        $manager = graphql_app(Model\Manager::class);
                        $manager->load($parentType->config['model']);
                        $relation = $manager->getRelationByAlias($parentType->config['model'], $key);
                        $foreignKey = $relation->getReferencedFields();
                        if (is_string($foreignKey)) {
                            $foreignKey = [$foreignKey];
                        }
                        foreach ($foreignKey as $foreign) {
                            $field[$foreign] = self::FOREIGN_KEY;
                        }
                        // New parent type, which is the relation
                        $newParentType = $parentType->getField($key)->config['type'];
                        self::addAlwaysFields($fieldObject, $field, $parentTable, true);
                        $with[$key] = self::getSelectableFieldsAndRelations($field, $newParentType, $customQuery, false);
                    } else {
                        self::handleFields($field, $fieldObject->config['type'], $select, $with);
                    }
                } // Select
                else {
                    $key = isset($fieldObject->config['alias']) ? $fieldObject->config['alias'] : $key;
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
        if (is_a($parentType, InterfaceType::class) || is_a($parentType, UnionType::class)) {
            $select = ['*'];
        }
    }

    /**
     * Check the privacy status, if it's given
     * @param $fieldObject
     * @return boolean | null - true, if selectable; false, if not selectable, but allowed;
     *                          null, if not allowed
     */
    protected static function validateField($fieldObject)
    {
        $selectable = true;
        // If not a selectable field
        if (isset($fieldObject->config['selectable']) && $fieldObject->config['selectable'] === false) {
            $selectable = false;
        }
        if (isset($fieldObject->config['privacy'])) {
            $privacyClass = $fieldObject->config['privacy'];
            // If privacy given as a closure
            if (is_callable($privacyClass) && call_user_func($privacyClass, self::$args) === false) {
                $selectable = null;
            } // If Privacy class given
            else if (is_string($privacyClass)) {
                if (in_array($privacyClass, self::$privacyValidations)) {
                    $validated = self::$privacyValidations[$privacyClass];
                } else {
                    $validated = call_user_func([
                        graphql_app($privacyClass),
                        'fire'
                    ], self::$args);
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
     * @param $fieldObject
     * @return bool
     */
    private static function isQueryable($fieldObject)
    {
        return $fieldObject['is_relation'] ?? true === true;
    }

    /**
     * Add selects that are given by the 'always' attribute
     */
    protected static function addAlwaysFields($fieldObject, array &$select, $parentTable, $forRelation = false)
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

    protected static function addFieldToSelect($field, &$select, $parentTable, $forRelation)
    {
        if ($forRelation && !array_key_exists($field, $select)) {
            $select[$field] = true;
        } else if (!$forRelation && !in_array($field, $select)) {
//            $field = $parentTable ? ($parentTable.'.'.$field) : $field;
            if (!in_array($field, $select)) {
                $select[] = "[$field]";
            }
        }
    }

    /**
     * 获取表主键名
     * @param $parentType
     * @return null|array
     */
    private static function getPrimaryKeyFromParentType($parentType)
    {
        if (!isset($parentType->config['model'])) {
            return null;
        }
        /** @var Model $model */
        $model = graphql_app($parentType->config['model']);
        return $model->getModelsMetaData()->getPrimaryKeyAttributes($model);
    }

    /**
     * 获取表名
     * @param $parentType
     * @return null|string
     */
    private static function getTableNameFromParentType($parentType)
    {
        return isset($parentType->config['model']) ? graphql_app($parentType->config['model'])->getSource() : null;
    }

    public function getSelect()
    {
        return $this->select;
    }

    public function getRelations()
    {
        return $this->relations;
    }
}
<?php

namespace ipl\Orm;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterExpression;
use ipl\Orm\Contract\PersistBehavior;
use ipl\Orm\Contract\PropertyBehavior;
use ipl\Orm\Contract\RetrieveBehavior;
use ipl\Orm\Contract\RewriteFilterBehavior;

class Behaviors
{
    /** @var RetrieveBehavior[] Registered retrieve behaviors */
    protected $retrieveBehaviors = [];

    /** @var PersistBehavior[] Registered persist behaviors */
    protected $persistBehaviors = [];

    /** @var PropertyBehavior[] Registered property behaviors */
    protected $propertyBehaviors = [];

    /** @var RewriteFilterBehavior[] Registered rewrite filter behaviors */
    protected $rewriteFilterBehaviors = [];

    /**
     * Add a behavior
     *
     * @param PersistBehavior|PropertyBehavior|RetrieveBehavior|RewriteFilterBehavior $behavior
     */
    public function add(Behavior $behavior)
    {
        if ($behavior instanceof PropertyBehavior) {
            $this->retrieveBehaviors[] = $behavior;
            $this->persistBehaviors[] = $behavior;
            $this->propertyBehaviors[] = $behavior;
        } else {
            if ($behavior instanceof RetrieveBehavior) {
                $this->retrieveBehaviors[] = $behavior;
            }

            if ($behavior instanceof PersistBehavior) {
                $this->persistBehaviors[] = $behavior;
            }
        }

        if ($behavior instanceof RewriteFilterBehavior) {
            $this->rewriteFilterBehaviors[] = $behavior;
        }
    }

    /**
     * Apply all retrieve behaviors on the given model
     *
     * @param Model $model
     */
    public function retrieve(Model $model)
    {
        foreach ($this->retrieveBehaviors as $behavior) {
            $behavior->retrieve($model);
        }
    }

    /**
     * Apply all persist behaviors on the given model
     *
     * @param Model $model
     */
    public function persist(Model $model)
    {
        foreach ($this->persistBehaviors as $behavior) {
            $behavior->persist($model);
        }
    }

    /**
     * Transform the retrieved key's value by use of all property behaviors
     *
     * @param mixed  $value
     * @param string $key
     *
     * @return mixed
     */
    public function retrieveProperty($value, $key)
    {
        foreach ($this->propertyBehaviors as $behavior) {
            $value = $behavior->retrieveProperty($value, $key);
        }

        return $value;
    }

    /**
     * Transform the to be persisted key's value by use of all property behaviors
     *
     * @param mixed  $value
     * @param string $key
     *
     * @return mixed
     */
    public function persistProperty($value, $key)
    {
        foreach ($this->propertyBehaviors as $behavior) {
            $value = $behavior->persistProperty($value, $key);
        }

        return $value;
    }

    /**
     * Rewrite the given filter expression
     *
     * @param FilterExpression $expression
     * @param string           $relation Absolute path of the model
     *
     * @return Filter|null
     */
    public function rewriteCondition(FilterExpression $expression, $relation = null)
    {
        $filter = null;
        foreach ($this->rewriteFilterBehaviors as $behavior) {
            $replacement = $behavior->rewriteCondition($filter ?: $expression, $relation);
            if ($replacement !== null) {
                $filter = $replacement;
            }
        }

        return $filter;
    }
}

<?php

namespace ipl\Sql;

/**
 * Implementation for the {@link WhereInterface}
 */
trait Where
{
    /** @var array|null Internal representation for the WHERE part of the query */
    protected $where;

    public function getWhere()
    {
        return $this->where;
    }

    public function where($condition, $operator = Sql::ALL)
    {
        $this->mergeCondition($this->where, $this->buildCondition($condition, $operator), Sql::ALL);

        return $this;
    }

    public function orWhere($condition, $operator = Sql::ALL)
    {
        $this->mergeCondition($this->where, $this->buildCondition($condition, $operator), Sql::ANY);

        return $this;
    }

    /**
     * Make $condition an array and build an array like this: [$operator] + $condition
     *
     * If $condition is empty, replace it with a boolean constant depending on the operator.
     *
     * @param   string|array    $condition
     * @param   string          $operator
     *
     * @return  array
     */
    protected function buildCondition($condition, $operator)
    {
        if (is_array($condition)) {
            if (empty($condition)) {
                return [$operator, $operator === Sql::ALL ? '1' : '0'];
            }
        } else {
            $condition = [$condition];
        }

        return array_merge([$operator], $condition);
    }

    /**
     * Merge the given condition with ours via the given operator
     *
     * @param   mixed   $base       Our condition
     * @param   array   $condition  As returned by {@link buildCondition()}
     * @param   string  $operator
     */
    protected function mergeCondition(& $base, array $condition, $operator)
    {
        if ($base === null) {
            $base = [$operator, $condition];
        } else {
            if ($base[0] === $operator) {
                $base[] = $condition;
            } else {
                $base = [$operator, $base, $condition];
            }
        }
    }

    /**
     * Clone the properties provided by this trait
     *
     * Shall be called by using classes in their __clone()
     */
    protected function cloneWhere()
    {
        if ($this->where !== null) {
            $this->cloneCondition($this->where);
        }
    }

    /**
     * Clone a condition in-place
     *
     * @param   array   $condition  As returned by {@link buildCondition()}
     */
    protected function cloneCondition(array &$condition)
    {
        foreach ($condition as &$subCondition) {
            if (is_array($subCondition)) {
                $this->cloneCondition($subCondition);
            } elseif ($subCondition instanceof ExpressionInterface || $subCondition instanceof Select) {
                $subCondition = clone $subCondition;
            }
        }
        unset($subCondition);
    }
}

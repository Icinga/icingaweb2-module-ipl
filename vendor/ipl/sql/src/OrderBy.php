<?php

namespace ipl\Sql;

/**
 * Trait for the ORDER BY part of a query
 */
trait OrderBy
{
    /**
     * ORDER BY part of the query
     *
     * @var array
     */
    protected $orderBy;

    public function hasOrderBy()
    {
        return $this->orderBy !== null;
    }

    public function getOrderBy()
    {
        return $this->orderBy;
    }

    public function orderBy($orderBy, $direction = null)
    {
        if (! is_array($orderBy)) {
            $orderBy = [$orderBy];
        }

        foreach ($orderBy as $column => $dir) {
            if (is_int($column)) {
                $column = $dir;
                $dir = $direction;
            }

            if ($dir === SORT_ASC) {
                $dir = 'ASC';
            } elseif ($dir === SORT_DESC) {
                $dir = 'DESC';
            }

            $this->orderBy[] = [$column, $dir];
        }

        return $this;
    }

    /**
     * Clone the properties provided by this trait
     *
     * Shall be called by using classes in their __clone()
     */
    protected function cloneOrderBy()
    {
        if ($this->orderBy !== null) {
            foreach ($this->orderBy as &$orderBy) {
                if ($orderBy[0] instanceof ExpressionInterface || $orderBy[0] instanceof Select) {
                    $orderBy[0] = clone $orderBy[0];
                }
            }
            unset($orderBy);
        }
    }
}

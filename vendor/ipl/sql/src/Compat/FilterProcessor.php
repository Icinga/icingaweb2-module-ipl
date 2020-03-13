<?php

namespace ipl\Sql\Compat;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Data\Filter\FilterNot;
use Icinga\Data\Filter\FilterOr;
use InvalidArgumentException;
use ipl\Sql\Expression;
use ipl\Sql\Sql;

class FilterProcessor
{
    public static function assembleFilter(Filter $filter, $level = 0)
    {
        $condition = null;

        if ($filter->isChain()) {
            if ($filter instanceof FilterAnd) {
                $operator = Sql::ALL;
            } elseif ($filter instanceof FilterOr) {
                $operator = Sql::ANY;
            } elseif ($filter instanceof FilterNot) {
                $operator = Sql::NOT_ALL;
            }

            if (! isset($operator)) {
                throw new InvalidArgumentException(sprintf('Cannot render filter: %s', get_class($filter)));
            }

            if (! $filter->isEmpty()) {
                foreach ($filter->filters() as $filterPart) {
                    $part = static::assembleFilter($filterPart, $level + 1);
                    if ($part) {
                        if ($condition === null) {
                            $condition = [$operator, [$part]];
                        } else {
                            if ($condition[0] === $operator) {
                                $condition[1][] = $part;
                            } elseif ($operator === Sql::NOT_ALL) {
                                $condition = [Sql::ALL, [$condition, [$operator, [$part]]]];
                            } elseif ($operator === Sql::NOT_ANY) {
                                $condition = [Sql::ANY, [$condition, [$operator, [$part]]]];
                            } else {
                                $condition = [$operator, [$condition, $part]];
                            }
                        }
                    }
                }
            } else {
                // TODO(el): Explicitly return the empty string due to the FilterNot case?
            }
        } else {
            /** @var FilterExpression $filter */
            $condition = [Sql::ALL,
                static::assemblePredicate($filter->getColumn(), $filter->getSign(), $filter->getExpression())
            ];
        }

        return $condition;
    }

    public static function assemblePredicate($column, $operator, $expression)
    {
        if (is_array($expression)) {
            if ($operator === '=') {
                return ["$column IN (?)" => $expression];
            } elseif ($operator === '!=') {
                return ["($column NOT IN (?) OR $column IS NULL)" => $expression];
            }

            throw new InvalidArgumentException(
                'Unable to render array expressions with operators other than equal or not equal'
            );
        } elseif ($operator === '=' && strpos($expression, '*') !== false) {
            if ($expression === '*') {
                // We'll ignore such filters as it prevents index usage and because "*" means anything. So whether we're
                // using a real column with a valid comparison here or just an expression which can only be evaluated to
                // true makes no difference, except for performance reasons
                return [new Expression('TRUE')];
            }

            return ["$column LIKE ?" => str_replace('*', '%', $expression)];
        } elseif ($operator === '!=' && strpos($expression, '*') !== false) {
            if ($expression === '*') {
                // We'll ignore such filters as it prevents index usage and because "*" means nothing. So whether we're
                // using a real column with a valid comparison here or just an expression which cannot be evaluated to
                // true makes no difference, except for performance reasons
                return [new Expression('FALSE')];
            }

            return ["($column NOT LIKE ? OR $column IS NULL)" => str_replace('*', '%', $expression)];
        } elseif ($operator === '!=') {
            return ["($column != ? OR $column IS NULL)" => $expression];
        } else {
            return ["$column $operator ?" => $expression];
        }
    }
}

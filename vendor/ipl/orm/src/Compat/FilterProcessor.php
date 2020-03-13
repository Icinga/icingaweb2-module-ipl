<?php

namespace ipl\Orm\Compat;

use AppendIterator;
use ArrayIterator;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Data\Filter\FilterOr;
use ipl\Orm\Query;
use ipl\Orm\Relation;
use ipl\Orm\UnionQuery;
use ipl\Sql\Expression;

class FilterProcessor extends \ipl\Sql\Compat\FilterProcessor
{
    protected $baseJoins = [];

    protected $madeJoins = [];

    public static function apply(Filter $filter, Query $query)
    {
        if ($query instanceof UnionQuery) {
            foreach ($query->getUnions() as $union) {
                static::apply($filter, $union);
            }

            return;
        }

        if (! $filter->isEmpty()) {
            $filter = clone $filter;
            if (! $filter->isChain()) {
                // TODO: Quickfix, there's probably a better solution?
                $filter = Filter::matchAll($filter);
            }

            $processor = new static();
            foreach ($query->getUtilize() as $path => $_) {
                $processor->baseJoins[$path] = true;
            }

            $rewrittenFilter = $processor->requireAndResolveFilterColumns($filter, $query);
            if ($rewrittenFilter !== null) {
                $filter = $rewrittenFilter;
            }

            $where = static::assembleFilter($filter);

            if ($where) {
                $operator = array_shift($where);
                $conditions = array_shift($where);

                $query->getSelectBase()->where($conditions, $operator);
            }
        }
    }

    protected function requireAndResolveFilterColumns(Filter $filter, Query $query)
    {
        if ($filter instanceof FilterExpression) {
            if ($filter->getExpression() === '*') {
                // Wildcard only filters are ignored so stop early here to avoid joining a table for nothing
                return;
            }

            $column = $filter->getColumn();
            if (isset($filter->metaData)) {
                $column = $filter->metaData['relationCol'];
            }

            $resolver = $query->getResolver();
            $baseTable = $query->getModel()->getTableName();
            $column = $resolver->qualifyPath($column, $baseTable);

            $filter->metaData['column'] = $column;

            list($relationPath, $columnName) = preg_split('/\.(?=[^.]+$)/', $column);

            $relations = new AppendIterator();
            $relations->append(new ArrayIterator([$baseTable => null]));
            $relations->append($resolver->resolveRelations($relationPath));
            foreach ($relations as $path => $relation) {
                $columnName = substr($column, strlen($path) + 1);

                if ($path === $baseTable) {
                    $subject = $query->getModel();
                } else {
                    /** @var Relation $relation */
                    $subject = $relation->getTarget();
                }

                $subjectBehaviors = $resolver->getBehaviors($subject);

                // Prepare filter as if it were final to allow full control for rewrite filter behaviors
                $filter->setExpression($subjectBehaviors->persistProperty($filter->getExpression(), $columnName));
                $filter->setColumn($resolver->getAlias($subject) . '.' . $columnName);
                $filter->metaData['relationCol'] = $columnName;
                $filter->metaData['relationPath'] = $path;

                $rewrittenFilter = $subjectBehaviors->rewriteCondition($filter, $path . '.');
                if ($rewrittenFilter !== null) {
                    return $this->requireAndResolveFilterColumns($rewrittenFilter, $query) ?: $rewrittenFilter;
                }
            }

            if ($relationPath !== $baseTable) {
                $query->utilize($relationPath);
                $this->madeJoins[$relationPath][] = $filter;
            }
        } else {
            /** @var FilterChain $filter */

            $subQueryFilters = [];
            foreach ($filter->filters() as $child) {
                /** @var Filter $child */
                $rewrittenFilter = $this->requireAndResolveFilterColumns($child, $query);
                if ($rewrittenFilter !== null) {
                    $filter->replaceById($child->getId(), $rewrittenFilter);
                    $child = $rewrittenFilter;
                }

                // We optimize only single expressions
                if (isset($child->metaData) && $child instanceof FilterExpression) {
                    $relationPath = $child->metaData['relationPath'];
                    if ($relationPath !== $query->getModel()->getTableName()) {
                        if (! $query->getResolver()->isDistinctRelation($relationPath)) {
                            if (isset($child->metaData['original'])) {
                                $column = $child->metaData['original']->metaData['column'];
                                $sign = $child->metaData['original']->getSign();
                            } else {
                                $column = $child->getColumn();
                                $sign = $child->getSign();
                            }

                            $subQueryFilters[$sign][$column][] = $child;
                        }
                    }
                }
            }

            foreach ($subQueryFilters as $sign => $filterCombinations) {
                foreach ($filterCombinations as $column => $filters) {
                    // The relation path must be the same for all entries
                    $relationPath = $filters[0]->metaData['relationPath'];

                    // In case the parent query also selects the relation we may not require a subquery.
                    // Otherwise we form a cartesian product and get unwanted results back.
                    $selectedByParent = isset($query->getWith()[$relationPath]);

                    // Though, only single equal comparisons or those chained with an OR may be evaluated on the base
                    if ($selectedByParent && $sign !== '!=' && (count($filters) === 1 || $filter instanceof FilterOr)) {
                        continue;
                    }

                    $relation = $query->getResolver()->resolveRelation($relationPath);
                    $subQuery = $query->createSubQuery($relation->getTarget(), $relationPath);
                    $subQuery->columns([new Expression('1')]);

                    if ($sign === '!=' || $filter instanceof FilterAnd) {
                        $targetKeys = join(',', array_values(
                            $subQuery->getResolver()->qualifyColumns(
                                (array) $subQuery->getModel()->getKeyName(),
                                $subQuery->getResolver()->getAlias($subQuery->getModel())
                            )
                        ));

                        if ($sign !== '!=' || $filter instanceof FilterOr) {
                            // Unequal (!=) comparisons chained with an OR are considered an XOR
                            $count = count(array_unique(array_map(function (FilterExpression $f) {
                                return $f->getExpression();
                            }, $filters)));
                        } else {
                            // Unequal (!=) comparisons are transformed to equal (=) ones. If chained with an AND
                            // we just have to check for a single result as an object must not match any of these
                            // comparisons
                            $count = 1;
                        }

                        $subQuery->getSelectBase()->having(["COUNT(DISTINCT $targetKeys) >= ?" => $count]);
                    }

                    foreach ($filters as $i => $child) {
                        if ($sign === '!=') {
                            // Unequal comparisons must be negated since the sub-query is an inverse of the outer one
                            if ($child->isExpression()) {
                                $filters[$i] = $child->setSign('=');
                                $filters[$i]->metaData = $child->metaData;
                            } else {
                                $childId = $child->getId(); // Gets re-indexed otherwise
                                $filters[$i] = Filter::not($child)->setId($childId);
                            }
                        }

                        // Remove joins solely used for filter conditions
                        foreach ($this->madeJoins as $joinPath => &$madeBy) {
                            $madeBy = array_filter($madeBy, function ($relationFilter) use ($child) {
                                return $child->getId() !== $relationFilter->getId()
                                    && ! $child->hasId($relationFilter->getId());
                            });

                            if (empty($madeBy)) {
                                if (! isset($this->baseJoins[$joinPath])) {
                                    $query->omit($joinPath);
                                }

                                unset($this->madeJoins[$joinPath]);
                            }
                        }

                        $filter->removeId($child->getId());
                    }

                    static::apply(Filter::matchAny($filters), $subQuery);

                    $filter->addFilter(new FilterExpression(
                        '',
                        ($sign === '!=' ? 'NOT ' : '') . 'EXISTS',
                        $subQuery->assembleSelect()->resetOrderBy()
                    ));
                }
            }
        }
    }
}

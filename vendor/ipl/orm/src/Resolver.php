<?php

namespace ipl\Orm;

use Generator;
use InvalidArgumentException;
use ipl\Orm\Relation\BelongsToMany;
use ipl\Sql\ExpressionInterface;
use OutOfBoundsException;
use RuntimeException;
use SplObjectStorage;

/**
 * Column and relation resolver. Acts as glue between queries and models
 */
class Resolver
{
    /** @var Query The query to resolve */
    protected $query;

    /** @var SplObjectStorage Model relations */
    protected $relations;

    /** @var SplObjectStorage Model behaviors */
    protected $behaviors;

    /** @var SplObjectStorage Model aliases */
    protected $aliases;

    /** @var string The alias prefix to use */
    protected $aliasPrefix;

    /** @var SplObjectStorage Selectable columns from resolved models */
    protected $selectableColumns;

    /** @var SplObjectStorage Select columns from resolved models */
    protected $selectColumns;

    /** @var Relation[] Resolved relations */
    protected $resolvedRelations = [];

    /**
     * Create a new resolver
     */
    public function __construct()
    {
        $this->relations = new SplObjectStorage();
        $this->behaviors = new SplObjectStorage();
        $this->aliases = new SplObjectStorage();
        $this->selectableColumns = new SplObjectStorage();
        $this->selectColumns = new SplObjectStorage();
    }

    /**
     * Set the query this resolver belongs to
     *
     * @param Query $query
     *
     * @return $this
     */
    public function setQuery(Query $query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Get a model's relations
     *
     * @param Model $model
     *
     * @return Relations
     */
    public function getRelations(Model $model)
    {
        if (! $this->relations->contains($model)) {
            $relations = new Relations();
            $model->createRelations($relations);
            $this->relations->attach($model, $relations);
        }

        return $this->relations[$model];
    }

    /**
     * Get a model's behaviors
     *
     * @param Model $model
     *
     * @return Behaviors
     */
    public function getBehaviors(Model $model)
    {
        if (! $this->behaviors->contains($model)) {
            $behaviors = new Behaviors();
            $model->createBehaviors($behaviors);
            $this->behaviors->attach($model, $behaviors);
        }

        return $this->behaviors[$model];
    }

    /**
     * Get a model alias
     *
     * @param Model $model
     *
     * @return string
     *
     * @throws OutOfBoundsException If no alias exists for the given model
     */
    public function getAlias(Model $model)
    {
        if (! $this->aliases->contains($model)) {
            throw new OutOfBoundsException(sprintf(
                "Can't get alias for model '%s'. Alias does not exist",
                get_class($model)
            ));
        }

        return $this->aliasPrefix . $this->aliases[$model];
    }

    /**
     * Set a model alias
     *
     * @param Model  $model
     * @param string $alias
     *
     * @return $this
     */
    public function setAlias(Model $model, $alias)
    {
        $this->aliases[$model] = $alias;

        return $this;
    }

    /**
     * Get the alias prefix
     *
     * @return string
     */
    public function getAliasPrefix()
    {
        return $this->aliasPrefix;
    }

    /**
     * Set the alias prefix
     *
     * @param string $alias
     *
     * @return $this
     */
    public function setAliasPrefix($alias)
    {
        $this->aliasPrefix = $alias;

        return $this;
    }

    /**
     * Get whether the specified model provides the given selectable column
     *
     * @param Model  $subject
     * @param string $column
     *
     * @return bool
     */
    public function hasSelectableColumn(Model $subject, $column)
    {
        if (! $this->selectableColumns->contains($subject)) {
            $this->collectColumns($subject);
        }

        $columns = $this->selectableColumns[$subject];

        return isset($columns[$column]);
    }

    /**
     * Get all selectable columns from the given model
     *
     * @param Model $subject
     *
     * @return array
     */
    public function getSelectableColumns(Model $subject)
    {
        if (! $this->selectableColumns->contains($subject)) {
            $this->collectColumns($subject);
        }

        return array_keys($this->selectableColumns[$subject]);
    }

    /**
     * Get all select columns from the given model
     *
     * @param Model $subject
     *
     * @return array Select columns suitable for {@link \ipl\Sql\Select::columns()}
     */
    public function getSelectColumns(Model $subject)
    {
        if (! $this->selectColumns->contains($subject)) {
            $this->collectColumns($subject);
        }

        return $this->selectColumns[$subject];
    }

    /**
     * Qualify the given alias by the specified table name
     *
     * @param string $alias
     * @param string $tableName
     *
     * @return string
     */
    public function qualifyColumnAlias($alias, $tableName)
    {
        return $tableName . '_' . $alias;
    }

    /**
     * Qualify the given column by the specified table name
     *
     * @param string $column
     * @param string $tableName
     *
     * @return string
     */
    public function qualifyColumn($column, $tableName)
    {
        return $tableName . '.' . $column;
    }

    /**
     * Qualify the given columns by the specified table name
     *
     * @param array  $columns
     * @param string $tableName
     *
     * @return array
     */
    public function qualifyColumns(array $columns, $tableName)
    {
        $qualified = [];

        foreach ($columns as $alias => $column) {
            if (! $column instanceof ExpressionInterface) {
                $column = $this->qualifyColumn($column, $tableName);
            }

            $qualified[$alias] = $column;
        }

        return $qualified;
    }

    /**
     * Qualify the given columns and aliases by the specified table name
     *
     * @param array  $columns
     * @param string $tableName
     *
     * @return array
     */
    public function qualifyColumnsAndAliases(array $columns, $tableName)
    {
        $qualified = [];

        foreach ($columns as $alias => $column) {
            if (is_int($alias)) {
                $alias = $this->qualifyColumnAlias($column, $tableName);
                $column = $this->qualifyColumn($column, $tableName);
            } elseif (! $column instanceof ExpressionInterface) {
                $column = $this->qualifyColumn($column, $tableName);
            }

            $qualified[$alias] = $column;
        }

        return $qualified;
    }

    /**
     * Qualify the given path by the specified table name
     *
     * @param string $path
     * @param string $tableName
     *
     * @return string
     */
    public function qualifyPath($path, $tableName)
    {
        $segments = explode('.', $path, 2);

        if ($segments[0] !== $tableName) {
            array_unshift($segments, $tableName);
        }

        $path = implode('.', $segments);

        return $path;
    }

    /**
     * Get whether the given relation path points to a distinct entity
     *
     * @param string $path
     *
     * @return bool
     */
    public function isDistinctRelation($path)
    {
        foreach ($this->resolveRelations($path) as $relation) {
            if (! $relation->isOne()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve the rightmost relation of the given path
     *
     * Also resolves all other relations.
     *
     * @param string $path
     *
     * @return Relation
     */
    public function resolveRelation($path)
    {
        if (! isset($this->resolvedRelations[$path])) {
            $this->resolvedRelations += iterator_to_array($this->resolveRelations($path));
        }

        return $this->resolvedRelations[$path];
    }

    /**
     * Resolve all relations of the given path
     *
     * Traverses the entire path and yields the path travelled so far as key and the relation as value.
     *
     * @param string $path
     *
     * @return Generator
     * @throws InvalidArgumentException In case $path is not fully qualified or a relation is unknown
     */
    public function resolveRelations($path)
    {
        $relations = explode('.', $path);
        $subject = $this->query->getModel();

        if ($relations[0] !== $subject->getTableName()) {
            throw new InvalidArgumentException(sprintf(
                'Cannot resolve relation path "%s". Base table name is missing.',
                $path
            ));
        }

        $segments = [array_shift($relations)];
        foreach ($relations as $relationName) {
            $segments[] = $relationName;
            $relationPath = join('.', $segments);

            if (isset($this->resolvedRelations[$relationPath])) {
                $relation = $this->resolvedRelations[$relationPath];
            } else {
                $subjectRelations = $this->getRelations($subject);
                if (! $subjectRelations->has($relationName)) {
                    throw new InvalidArgumentException(sprintf(
                        'Cannot join relation "%s" in model "%s". Relation not found.',
                        $relationName,
                        get_class($subject)
                    ));
                }

                $relation = $subjectRelations->get($relationName);
                $relation->setSource($subject);

                $this->resolvedRelations[$relationPath] = $relation;

                if ($relation instanceof BelongsToMany) {
                    $through = $relation->getThrough();
                    $this->setAlias($through, join('_', array_merge(
                        array_slice($segments, 0, -1),
                        [$through->getTableName()]
                    )));
                }

                $this->setAlias($relation->getTarget(), join('_', $segments));
            }

            yield $relationPath => $relation;

            $subject = $relation->getTarget();
        }
    }

    /**
     * Require and resolve columns
     *
     * Related models will be automatically added for eager-loading.
     *
     * @param array $columns
     *
     * @return Generator
     *
     * @throws RuntimeException If a column does not exist
     */
    public function requireAndResolveColumns(array $columns)
    {
        $model = $this->query->getModel();
        $tableName = $model->getTableName();

        foreach ($columns as $alias => $column) {
            if ($column === '*' || $column instanceof ExpressionInterface) {
                yield [$model, $alias, $column];

                continue;
            }

            $dot = strrpos($column, '.');

            switch (true) {
                /** @noinspection PhpMissingBreakStatementInspection */
                case $dot !== false:
                    $relation = substr($column, 0, $dot);
                    $column = substr($column, $dot + 1);

                    if ($relation !== $tableName) {
                        $relation = $this->qualifyPath($relation, $tableName);

                        $this->query->with($relation);
                        $target = $this->resolvedRelations[$relation]->getTarget();

                        break;
                    }
                // Move to default
                default:
                    $relation = null;
                    $target = $model;
            }

            if (! $this->hasSelectableColumn($target, $column)) {
                throw new RuntimeException(sprintf(
                    "Can't require column '%s' in model '%s'. Column not found.",
                    $column,
                    get_class($target)
                ));
            }

            yield [$target, $alias, $column];
        }
    }

    /**
     * Collect all selectable columns from the given model
     *
     * @param Model $subject
     */
    protected function collectColumns(Model $subject)
    {
        // Don't fail if Model::getColumns() also contains the primary key columns
        $columns = array_merge((array) $subject->getKeyName(), (array) $subject->getColumns());

        $this->selectColumns->attach($subject, $columns);

        $selectable = [];

        foreach ($columns as $alias => $column) {
            if (is_int($alias)) {
                $selectable[$column] = true;
            } else {
                $selectable[$alias] = true;
            }
        }

        $this->selectableColumns->attach($subject, $selectable);
    }
}

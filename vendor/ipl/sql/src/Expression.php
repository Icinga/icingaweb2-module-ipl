<?php

namespace ipl\Sql;

/**
 * A database expression that does need quoting or escaping, e.g. new Expression('NOW()');
 */
class Expression implements ExpressionInterface
{
    /** @var string The statement of the expression */
    protected $statement;

    /** @var array The values for the expression */
    protected $values;

    /**
     * Create a new database expression
     *
     * @param   string  $statement  The statement of the expression
     * @param   mixed   ...$values  The values for the expression
     */
    public function __construct($statement, $values = null)
    {
        $values = func_get_args();
        array_shift($values);
        $this->statement = $statement;
        $this->values = $values;
    }

    public function getStatement()
    {
        return $this->statement;
    }

    public function getValues()
    {
        return $this->values;
    }
}

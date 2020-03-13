<?php

namespace ipl\Orm\Contract;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterExpression;
use ipl\Orm\Behavior;

interface RewriteFilterBehavior extends Behavior
{
    /**
     * Rewrite the given filter expression
     *
     * The expression can either be adjusted directly or replaced by an entirely new filter. The result must be
     * returned otherwise (NULL is returned) the original expression is kept as is.
     *
     * @param FilterExpression $expression
     * @param string           $relation The absolute path (with a trailing dot) of the model
     *
     * @return Filter|null
     */
    public function rewriteCondition(FilterExpression $expression, $relation = null);
}

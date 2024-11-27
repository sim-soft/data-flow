<?php

namespace Simsoft\DB\MySQL\Builder\Conditions;

use Simsoft\DB\MySQL\Builder\ActiveQuery;
use Simsoft\DB\MySQL\Builder\Clauses\Clause;

/**
 * Class InCondition
 *
 */
class InCondition extends Clause
{
    /**
     * {@inheritdoc}
     */
    protected function buildSQL(): string
    {
        $this->appendBinds($this->value);
        return $this->queryAttribute($this->attribute)
            . ($this->is ? '' : ' NOT')
            . ' IN ('
            . match (true) {
                is_array($this->value) => implode(',', array_fill(0, count($this->value), $this->getPlaceHolder())),
                $this->value instanceof ActiveQuery => $this->value->buildSQL(false),
                default => null,
            }
            . ')';
    }
}

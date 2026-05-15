<?php

namespace App\Database;

use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Builder;
use App\Support\QueryContext;

class CommenterMySqlGrammar extends MySqlGrammar
{
    /**
     * Compile a select query into SQL.
     */
    public function compileSelect(Builder $query)
    {
        return parent::compileSelect($query) . $this->appendComment();
    }

    /**
     * Compile an insert statement into SQL.
     */
    public function compileInsert(Builder $query, array $values)
    {
        return parent::compileInsert($query, $values) . $this->appendComment();
    }

    /**
     * Compile an update statement into SQL.
     */
    public function compileUpdate(Builder $query, array $values)
    {
        return parent::compileUpdate($query, $values) . $this->appendComment();
    }

    /**
     * Compile a delete statement into SQL.
     */
    public function compileDelete(Builder $query)
    {
        return parent::compileDelete($query) . $this->appendComment();
    }

    /**
     * Append the current context as a SQL comment.
     */
    private function appendComment(): string
    {
        return QueryContext::$currentAction 
            ? " /* Source: " . QueryContext::$currentAction . " */" 
            : "";
    }
}

<?php

namespace App\Support;

class QueryContext
{
    /**
     * The current controller/action or console command being executed.
     *
     * @var string|null
     */
    public static $currentAction = null;
}

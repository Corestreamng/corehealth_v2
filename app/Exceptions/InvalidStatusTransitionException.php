<?php

namespace App\Exceptions;

use App\Enums\QueueStatus;
use RuntimeException;

class InvalidStatusTransitionException extends RuntimeException
{
    public int $fromStatus;
    public int $toStatus;

    public function __construct(int $fromStatus, int $toStatus)
    {
        $this->fromStatus = $fromStatus;
        $this->toStatus   = $toStatus;

        $fromLabel = QueueStatus::label($fromStatus);
        $toLabel   = QueueStatus::label($toStatus);

        parent::__construct(
            "Invalid status transition: cannot move from '{$fromLabel}' ({$fromStatus}) to '{$toLabel}' ({$toStatus})."
        );
    }
}

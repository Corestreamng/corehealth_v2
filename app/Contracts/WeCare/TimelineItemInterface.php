<?php

namespace App\Contracts\WeCare;

use App\Enums\WeCare\TimelineType;

/**
 * Interface TimelineItemInterface
 *
 * Contract for individual clinical event entries in the unified timeline.
 */
interface TimelineItemInterface
{
    public function getType(): TimelineType;

    public function getTypeLabel(): string;

    public function getBadgeColor(): string;

    public function getIcon(): string;

    public function getTitle(): string;

    public function getSubtitle(): string;

    public function getDetails(): ?string;

    public function getTimestamp(): ?string;

    public function getDateFormatted(): ?string;

    public function getData(): array;

    public function toArray(): array;
}

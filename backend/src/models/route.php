<?php

declare(strict_types=1);

final class Route
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $description,
        public readonly string $difficulty,
        public readonly float $distanceKm,
    ) {
    }
}

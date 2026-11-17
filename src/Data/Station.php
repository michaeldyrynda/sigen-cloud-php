<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Data;

use Saloon\Http\Response;

final readonly class Station
{
    public function __construct(
        public int $id,
        public bool $onGrid,
        public float $pvCapacity,
        public float $batteryCapacity,
    ) {}

    public static function fromResponse(Response $response): self
    {
        /** @var array{stationId: int, onGrid: bool, pvCapacity: float|int, batteryCapacity: float|int} $station */
        $station = $response->json('data');

        return new self(
            id: $station['stationId'],
            onGrid: $station['onGrid'],
            pvCapacity: (float) $station['pvCapacity'],
            batteryCapacity: (float) $station['batteryCapacity'],
        );
    }
}

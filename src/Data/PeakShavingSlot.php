<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Data;

final readonly class PeakShavingSlot
{
    /** @param list<int> $days */
    public function __construct(
        public int $index,
        public array $days,
        public string $startTime,
        public string $endTime,
        public float $peakPowerKilowatts,
    ) {}

    /** @return array{whichDay: string, startTime: string, endTime: string, peakPower: float} */
    public function toArray(): array
    {
        return ['whichDay' => implode(',', $this->days), 'startTime' => $this->startTime, 'endTime' => $this->endTime, 'peakPower' => $this->peakPowerKilowatts];
    }
}

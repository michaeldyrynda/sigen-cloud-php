<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Data;

use InvalidArgumentException;
use Saloon\Http\Response;
use UnexpectedValueException;

final readonly class PeakShavingSchedule
{
    /** @param list<PeakShavingSlot> $slots */
    public function __construct(public bool $enabled, public int $shavingSoc, public array $slots = []) {}

    public static function fromResponse(Response $response): self
    {
        $data = $response->json('data');
        if (! is_array($data) || ! is_array($data['settingList'] ?? [])) {
            throw new UnexpectedValueException('Invalid peak shaving schedule response.');
        }

        $soc = $data['shavingSOC'] ?? 0;
        if (! is_numeric($soc)) {
            throw new UnexpectedValueException('Invalid peak shaving state of charge.');
        }

        $slots = [];
        foreach ($data['settingList'] ?? [] as $slot) {
            if (! is_array($slot)
                || ! is_string($slot['whichDay'] ?? null)
                || ! is_string($slot['startTime'] ?? null)
                || ! is_string($slot['endTime'] ?? null)
                || ! is_numeric($slot['peakPower'] ?? null)) {
                throw new UnexpectedValueException('Invalid peak shaving slot response.');
            }

            $slots[] = new PeakShavingSlot(count($slots), array_map('intval', array_values(array_filter(explode(',', $slot['whichDay']), fn (string $day): bool => $day !== ''))), $slot['startTime'], $slot['endTime'], (float) $slot['peakPower']);
        }

        return new self(($data['controlMode'] ?? 0) === 1, (int) $soc, $slots);
    }

    /** @return array{controlMode: int, shavingSOC: int, settingList: list<array{whichDay: string, startTime: string, endTime: string, peakPower: float}>} */
    public function toArray(): array
    {
        return ['controlMode' => $this->enabled ? 1 : 0, 'shavingSOC' => $this->shavingSoc, 'settingList' => array_map(fn (PeakShavingSlot $slot): array => $slot->toArray(), $this->slots)];
    }

    public function withSlot(PeakShavingSlot $slot): self
    {
        if ($slot->index < 0 || $slot->index >= count($this->slots)) {
            throw new InvalidArgumentException("Slot index {$slot->index} is out of range.");
        }
        $slots = $this->slots;
        $slots[$slot->index] = $slot;

        return new self($this->enabled, $this->shavingSoc, array_values($slots));
    }
}

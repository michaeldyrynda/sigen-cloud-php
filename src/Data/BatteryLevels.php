<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Data;

use Saloon\Http\Response;

/**
 * The battery state of charge thresholds, as whole percentages.
 */
final readonly class BatteryLevels
{
    public function __construct(
        public int $chargeSoc,
        public int $dischargeSoc,
        public ?int $peakShavingSoc,
        public int $backupSoc,
    ) {}

    /**
     * Sigen reports each threshold as a decimal string, such as "100.0", and a blank
     * peak shaving threshold when peak shaving has never been set up.
     */
    public static function fromResponse(Response $response): self
    {
        /** @var array{chargeSOC: string, dischargeSOC: string, peakShavingSOC: string, backupSOC: string} $levels */
        $levels = $response->json('data');

        return new self(
            chargeSoc: (int) (float) $levels['chargeSOC'],
            dischargeSoc: (int) (float) $levels['dischargeSOC'],
            peakShavingSoc: $levels['peakShavingSOC'] === '' ? null : (int) (float) $levels['peakShavingSOC'],
            backupSoc: (int) (float) $levels['backupSOC'],
        );
    }

    /**
     * Sigen expects each threshold as a whole-number string.
     *
     * @return array{chargeSOC: string, dischargeSOC: string, peakShavingSOC: string, backupSOC: string}
     */
    public function toArray(): array
    {
        return [
            'chargeSOC' => (string) $this->chargeSoc,
            'dischargeSOC' => (string) $this->dischargeSoc,
            'peakShavingSOC' => (string) $this->peakShavingSoc,
            'backupSOC' => (string) $this->backupSoc,
        ];
    }
}

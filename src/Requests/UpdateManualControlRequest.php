<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Dyrynda\SigenCloud\Enums\ManualControlMode;
use Dyrynda\SigenCloud\SigenCloud;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Starts Instant Manual Control in the given mode, or stops it when no mode is given.
 */
final class UpdateManualControlRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly int $stationId,
        private readonly ?ManualControlMode $mode = null,
        private readonly ?int $minutes = null,
        private readonly ?float $powerLimitationKilowatts = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/device/energy-profile/instant/manunal';
    }

    /**
     * @return array{enable: bool, stationId: int, mode: string, duration: string, powerLimitation: string}
     */
    protected function defaultBody(): array
    {
        if ($this->mode === null) {
            return [
                'enable' => false,
                'stationId' => $this->stationId,
                'mode' => '',
                'duration' => '',
                'powerLimitation' => '',
            ];
        }

        return [
            'enable' => true,
            'stationId' => $this->stationId,
            'mode' => $this->mode->value,
            'duration' => (string) $this->minutes,
            'powerLimitation' => $this->powerLimitationKilowatts !== null
                ? number_format($this->powerLimitationKilowatts, 3, '.', '')
                : ($this->mode->hasPowerLimit() ? number_format(SigenCloud::UNLIMITED_KILOWATTS, 3, '.', '') : ''),
        ];
    }
}

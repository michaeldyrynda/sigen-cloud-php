<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateBatteryPowerLimitRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly int $stationId,
        private readonly float $maxChargeKilowatts,
        private readonly float $maxDischargeKilowatts,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/device/energy-profile/battery/limit';
    }

    /**
     * @return array{stationId: int, batteryMaxChargingPower: string, batteryMaxDischargingPower: string}
     */
    protected function defaultBody(): array
    {
        return [
            'stationId' => $this->stationId,
            'batteryMaxChargingPower' => number_format($this->maxChargeKilowatts, 3, '.', ''),
            'batteryMaxDischargingPower' => number_format($this->maxDischargeKilowatts, 3, '.', ''),
        ];
    }
}

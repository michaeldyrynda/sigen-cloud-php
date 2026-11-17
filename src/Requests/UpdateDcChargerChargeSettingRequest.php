<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateDcChargerChargeSettingRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly int $stationId, private readonly string $serial, private readonly float $allowedChargePower, private readonly int $vehicleChargingCutoffSoc) {}

    public function resolveEndpoint(): string
    {
        return '/device/dcevse/charge/setting';
    }

    /** @return array{stationId: int, snCode: string, allowedChargePower: float, vehicleChargingCutoffSoc: int} */
    protected function defaultBody(): array
    {
        return ['stationId' => $this->stationId, 'snCode' => $this->serial, 'allowedChargePower' => $this->allowedChargePower, 'vehicleChargingCutoffSoc' => $this->vehicleChargingCutoffSoc];
    }
}

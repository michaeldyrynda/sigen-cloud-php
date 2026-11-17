<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateDcChargerModeRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly int $stationId, private readonly string $serial, private readonly int $chargeMode, private readonly ?bool $enableFromPack, private readonly ?float $cutoffSocFromPack, private readonly ?float $allowsDischargePower, private readonly ?int $vehicleDischargeCutoffSoc) {}

    public function resolveEndpoint(): string
    {
        return '/device/charge/mode/dc';
    }

    /** @return array<string, int|float|bool|string> */
    protected function defaultBody(): array
    {
        return array_filter([
            'stationId' => $this->stationId,
            'snCode' => $this->serial,
            'chargeMode' => $this->chargeMode,
            'enableFromPack' => $this->enableFromPack,
            'cutoffSocFromPack' => $this->cutoffSocFromPack,
            'allowsDischargePower' => $this->allowsDischargePower,
            'vehicleDischargeCutoffSoc' => $this->vehicleDischargeCutoffSoc,
        ], static fn ($value): bool => $value !== null);
    }
}

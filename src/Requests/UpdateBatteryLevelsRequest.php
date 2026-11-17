<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Dyrynda\SigenCloud\Data\BatteryLevels;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateBatteryLevelsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly int $stationId,
        private readonly BatteryLevels $levels,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/device/energy-profile/battery/level';
    }

    /**
     * @return array{stationId: int, chargeSOC: string, dischargeSOC: string, peakShavingSOC: string, backupSOC: string}
     */
    protected function defaultBody(): array
    {
        return [
            'stationId' => $this->stationId,
            ...$this->levels->toArray(),
        ];
    }
}

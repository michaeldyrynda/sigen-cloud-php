<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Dyrynda\SigenCloud\Data\PeakShavingSchedule;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateBatteryPeakShavingScheduleRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly int $stationId, private readonly PeakShavingSchedule $schedule) {}

    public function resolveEndpoint(): string
    {
        return '/device/dischargesetting/peak/shaving';
    }

    /** @return array{stationId: int, controlMode: int, shavingSOC: int, settingList: list<array{whichDay: string, startTime: string, endTime: string, peakPower: float}>} */
    protected function defaultBody(): array
    {
        return ['stationId' => $this->stationId, ...$this->schedule->toArray()];
    }
}

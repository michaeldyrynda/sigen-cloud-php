<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Resources;

use Dyrynda\SigenCloud\Requests\GetV2xDischargeInfoRequest;
use Dyrynda\SigenCloud\Requests\GetV2xDischargeSettingsRequest;
use Dyrynda\SigenCloud\Requests\StartV2xDischargeRequest;
use Dyrynda\SigenCloud\Requests\StopV2xDischargeRequest;
use Dyrynda\SigenCloud\Requests\UpdateV2xDischargeEnabledRequest;
use Saloon\Http\BaseResource;

final class V2xResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function dischargeInfo(int $stationId, string $serial): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetV2xDischargeInfoRequest($stationId, $serial))->json('data');
    }

    /** @return array<string, mixed> */
    public function dischargeSettings(int $stationId, string $serial): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetV2xDischargeSettingsRequest($stationId, $serial))->json('data');
    }

    public function setDischargeEnabled(int $stationId, string $serial, bool $enabled): void
    {
        $this->connector->send(new UpdateV2xDischargeEnabledRequest($stationId, $serial, $enabled));
    }

    public function startDischarge(int $stationId, string $serial, int $durationMinutes = 600, ?float $powerCapKw = null): void
    {
        $this->connector->send(new StartV2xDischargeRequest($stationId, $serial, $durationMinutes, $powerCapKw));
    }

    public function stopDischarge(int $stationId, string $serial): void
    {
        $this->connector->send(new StopV2xDischargeRequest($stationId, $serial));
    }
}

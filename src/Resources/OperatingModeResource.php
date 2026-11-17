<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Resources;

use Dyrynda\SigenCloud\Requests\GetOperatingModeRequest;
use Dyrynda\SigenCloud\Requests\GetOperatingModesRequest;
use Dyrynda\SigenCloud\Requests\UpdateOperatingModeRequest;
use Saloon\Http\BaseResource;

final class OperatingModeResource extends BaseResource
{
    /**
     * The station's built-in working modes, keyed by a numeric string value, and its custom energy profiles.
     *
     * @return array{
     *     defaultWorkingModes: list<array{label: string, sortOrder: int, remarks: string, value: string}>,
     *     energyProfileItems: list<array{profileId: int, name: string, ...}>,
     * }
     */
    public function available(int $stationId): array
    {
        /** @var array{defaultWorkingModes: list<array{label: string, sortOrder: int, remarks: string, value: string}>, energyProfileItems: list<array{profileId: int, name: string, ...}>} */
        return $this->connector->send(new GetOperatingModesRequest($stationId))->json('data');
    }

    /**
     * The label of the station's current operating mode, or null when it is not one of the available modes.
     */
    public function current(int $stationId): ?string
    {
        $modes = $this->available($stationId);

        /** @var array{currentMode: int, currentProfileId: int} $current */
        $current = $this->connector->send(new GetOperatingModeRequest($stationId))->json('data');

        if ($current['currentMode'] === 9) {
            foreach ($modes['energyProfileItems'] as $profile) {
                if ($profile['profileId'] === $current['currentProfileId']) {
                    return $profile['name'];
                }
            }
        } else {
            foreach ($modes['defaultWorkingModes'] as $mode) {
                if ($mode['value'] === (string) $current['currentMode']) {
                    return $mode['label'];
                }
            }
        }

        return null;
    }

    public function set(int $stationId, int $mode, int $profileId = -1): void
    {
        $this->connector->send(new UpdateOperatingModeRequest($stationId, $mode, $profileId));
    }
}

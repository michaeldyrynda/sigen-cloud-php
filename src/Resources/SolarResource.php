<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Resources;

use Dyrynda\SigenCloud\Requests\GetSolarPowerLimitRequest;
use Dyrynda\SigenCloud\Requests\UpdateSolarPowerLimitRequest;
use Dyrynda\SigenCloud\SigenCloud;
use InvalidArgumentException;
use Saloon\Http\BaseResource;

final class SolarResource extends BaseResource
{
    /**
     * The PV power limit in kilowatts, or null when PV output is not limited.
     */
    public function powerLimit(int $stationId): ?float
    {
        /** @var string $powerLimit */
        $powerLimit = $this->connector->send(
            new GetSolarPowerLimitRequest($stationId),
        )->json('data.powerLimit');

        $kilowatts = (float) $powerLimit;

        return $kilowatts >= SigenCloud::UNLIMITED_KILOWATTS ? null : $kilowatts;
    }

    public function removePowerLimit(int $stationId): void
    {
        $this->setPowerLimit($stationId, SigenCloud::UNLIMITED_KILOWATTS);
    }

    public function setPowerLimit(int $stationId, float $kilowatts): void
    {
        if ($kilowatts < 0) {
            throw new InvalidArgumentException(
                "The PV power limit must be zero or more kilowatts, [{$kilowatts}] given."
            );
        }

        $this->connector->send(
            new UpdateSolarPowerLimitRequest($stationId, $kilowatts),
        );
    }
}

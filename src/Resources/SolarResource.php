<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Resources;

use Dyrynda\SigenCloud\Requests\GetSolarPowerLimitRequest;
use Dyrynda\SigenCloud\Requests\UpdateSolarPowerLimitRequest;
use InvalidArgumentException;
use Saloon\Http\BaseResource;

final class SolarResource extends BaseResource
{
    /**
     * The value Sigenergy uses to mean "no limit" (0xFFFFFFFF watts).
     */
    private const float UNLIMITED_KILOWATTS = 4294967.295;

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

        return $kilowatts >= self::UNLIMITED_KILOWATTS ? null : $kilowatts;
    }

    public function removePowerLimit(int $stationId): void
    {
        $this->setPowerLimit($stationId, self::UNLIMITED_KILOWATTS);
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

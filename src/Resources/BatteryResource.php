<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Resources;

use Dyrynda\SigenCloud\Enums\ManualControlMode;
use Dyrynda\SigenCloud\Requests\UpdateManualControlRequest;
use InvalidArgumentException;
use Saloon\Http\BaseResource;

final class BatteryResource extends BaseResource
{
    /**
     * Override the active strategy, such as a VPP, for the given minutes.
     */
    public function startManualControl(int $stationId, ManualControlMode $mode, int $minutes): void
    {
        if ($minutes < 30 || $minutes > 120) {
            throw new InvalidArgumentException("Manual control must last between 30 and 120 minutes, [{$minutes}] given.");
        }

        $this->connector->send(new UpdateManualControlRequest($stationId, $mode, $minutes));
    }

    /**
     * Hand the battery back to the active strategy.
     */
    public function stopManualControl(int $stationId): void
    {
        $this->connector->send(new UpdateManualControlRequest($stationId));
    }
}

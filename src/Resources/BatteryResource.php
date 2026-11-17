<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Resources;

use Dyrynda\SigenCloud\Data\ManualControl;
use Dyrynda\SigenCloud\Enums\ManualControlMode;
use Dyrynda\SigenCloud\Requests\GetManualControlDisplayRequest;
use Dyrynda\SigenCloud\Requests\GetManualControlRequest;
use Dyrynda\SigenCloud\Requests\UpdateManualControlRequest;
use InvalidArgumentException;
use Saloon\Http\BaseResource;

final class BatteryResource extends BaseResource
{
    public function manualControl(int $stationId): ManualControl
    {
        return ManualControl::fromResponse(
            $this->connector->send(new GetManualControlRequest($stationId))
        );
    }

    /**
     * The live battery power and state of charge shown on the app's manual control screen.
     *
     * @return array<string, mixed>
     */
    public function manualControlDisplay(int $stationId): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetManualControlDisplayRequest($stationId))->json('data');
    }

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

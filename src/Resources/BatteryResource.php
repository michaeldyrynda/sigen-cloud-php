<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Resources;

use Dyrynda\SigenCloud\Data\BatteryLevels;
use Dyrynda\SigenCloud\Data\ManualControl;
use Dyrynda\SigenCloud\Data\PeakShavingSchedule;
use Dyrynda\SigenCloud\Data\PeakShavingSlot;
use Dyrynda\SigenCloud\Enums\ManualControlMode;
use Dyrynda\SigenCloud\Requests\GetBatteryBackupReserveRequest;
use Dyrynda\SigenCloud\Requests\GetBatteryExportLimitationRequest;
use Dyrynda\SigenCloud\Requests\GetBatteryLevelsRequest;
use Dyrynda\SigenCloud\Requests\GetBatteryPeakShavingScheduleRequest;
use Dyrynda\SigenCloud\Requests\GetBatteryPowerLimitRequest;
use Dyrynda\SigenCloud\Requests\GetManualControlDisplayRequest;
use Dyrynda\SigenCloud\Requests\GetManualControlRequest;
use Dyrynda\SigenCloud\Requests\UpdateBatteryBackupReserveRequest;
use Dyrynda\SigenCloud\Requests\UpdateBatteryExportLimitationRequest;
use Dyrynda\SigenCloud\Requests\UpdateBatteryLevelsRequest;
use Dyrynda\SigenCloud\Requests\UpdateBatteryPeakShavingScheduleRequest;
use Dyrynda\SigenCloud\Requests\UpdateBatteryPowerLimitRequest;
use Dyrynda\SigenCloud\Requests\UpdateManualControlRequest;
use Dyrynda\SigenCloud\SigenCloud;
use InvalidArgumentException;
use Saloon\Http\BaseResource;

final class BatteryResource extends BaseResource
{
    /**
     * The backup reserve settings, with the reserve as a whole percentage in backupReserve.
     *
     * @return array{stationId: int, backupReserve: int, operationMode: int, ja12Soc: string}
     */
    public function backupReserve(int $stationId): array
    {
        /** @var array{stationId: int, backupReserve: int, operationMode: int, ja12Soc: string} */
        return $this->connector->send(new GetBatteryBackupReserveRequest($stationId))->json('data');
    }

    /**
     * Whether the battery may export to the grid. currentEnable is the effective setting; the owner
     * and installer settings are null until someone changes them.
     *
     * @return array{currentEnable: bool, ownerSetEnable: ?bool, installerSetEnable: ?bool, nearModify: mixed}
     */
    public function exportLimitation(int $stationId): array
    {
        /** @var array{currentEnable: bool, ownerSetEnable: ?bool, installerSetEnable: ?bool, nearModify: mixed} */
        return $this->connector->send(new GetBatteryExportLimitationRequest($stationId))->json('data');
    }

    public function levels(int $stationId): BatteryLevels
    {
        return BatteryLevels::fromResponse(
            $this->connector->send(new GetBatteryLevelsRequest($stationId))
        );
    }

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

    public function peakShavingSchedule(int $stationId): PeakShavingSchedule
    {
        return PeakShavingSchedule::fromResponse($this->connector->send(new GetBatteryPeakShavingScheduleRequest($stationId)));
    }

    /**
     * The battery's maximum charge and discharge power in kilowatts, as decimal strings such as "5.000".
     * SigenCloud::UNLIMITED_KILOWATTS means the battery is not limited.
     *
     * @return array{batteryMaxChargingPower: string, batteryMaxDischargingPower: string}
     */
    public function powerLimit(int $stationId): array
    {
        /** @var array{batteryMaxChargingPower: string, batteryMaxDischargingPower: string} */
        return $this->connector->send(new GetBatteryPowerLimitRequest($stationId))->json('data');
    }

    /**
     * Keep the given percentage of the battery in reserve for backup power.
     */
    public function setBackupReserve(int $stationId, int $percent): void
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException("The backup reserve must be between 0 and 100 percent, [{$percent}] given.");
        }

        $this->connector->send(new UpdateBatteryBackupReserveRequest($stationId, $percent));
    }

    /**
     * Allow or stop the battery exporting to the grid.
     */
    public function setExportLimitation(int $stationId, bool $enabled): void
    {
        $this->connector->send(new UpdateBatteryExportLimitationRequest($stationId, $enabled));
    }

    public function setLevels(int $stationId, BatteryLevels $levels): void
    {
        $this->connector->send(new UpdateBatteryLevelsRequest($stationId, $levels));
    }

    public function setPeakShavingSchedule(int $stationId, PeakShavingSchedule $schedule): void
    {
        $this->connector->send(new UpdateBatteryPeakShavingScheduleRequest($stationId, $schedule));
    }

    public function setPeakShavingSlot(int $stationId, PeakShavingSlot $slot): void
    {
        $this->setPeakShavingSchedule($stationId, $this->peakShavingSchedule($stationId)->withSlot($slot));
    }

    /**
     * Limit the battery's charge and discharge power in kilowatts, where null means unlimited.
     */
    public function setPowerLimit(int $stationId, ?float $maxChargeKilowatts, ?float $maxDischargeKilowatts): void
    {
        foreach (['charge' => $maxChargeKilowatts, 'discharge' => $maxDischargeKilowatts] as $direction => $kilowatts) {
            if ($kilowatts < 0) {
                throw new InvalidArgumentException(
                    "The battery {$direction} power limit must be zero or more kilowatts, [{$kilowatts}] given."
                );
            }
        }

        $this->connector->send(new UpdateBatteryPowerLimitRequest(
            $stationId,
            $maxChargeKilowatts ?? SigenCloud::UNLIMITED_KILOWATTS,
            $maxDischargeKilowatts ?? SigenCloud::UNLIMITED_KILOWATTS,
        ));
    }

    /**
     * Override the active strategy, such as a VPP, for the given minutes.
     */
    public function startManualControl(int $stationId, ManualControlMode $mode, int $minutes, ?float $powerLimitationKilowatts = null): void
    {
        if ($minutes < 30 || $minutes > 120) {
            throw new InvalidArgumentException("Manual control must last between 30 and 120 minutes, [{$minutes}] given.");
        }

        $this->connector->send(new UpdateManualControlRequest($stationId, $mode, $minutes, $powerLimitationKilowatts));
    }

    /**
     * Hand the battery back to the active strategy.
     */
    public function stopManualControl(int $stationId): void
    {
        $this->connector->send(new UpdateManualControlRequest($stationId));
    }
}

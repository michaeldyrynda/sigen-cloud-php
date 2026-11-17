<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Resources;

use DateTimeInterface;
use Dyrynda\SigenCloud\Requests\GetDcChargerChargeRealtimeRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerChargeSettingRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerDischargeRealtimeRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerEnergyTotalsRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerLifetimeTotalsRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerModeRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerModeSocRangeRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerOcppStatusRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerPlugStatusRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerSessionRecordsRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerStationChargingRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerStatusRequest;
use Dyrynda\SigenCloud\Requests\UpdateDcChargerChargeEnabledRequest;
use Dyrynda\SigenCloud\Requests\UpdateDcChargerChargeSettingRequest;
use Dyrynda\SigenCloud\Requests\UpdateDcChargerModeRequest;
use Saloon\Http\BaseResource;

final class DcChargerResource extends BaseResource
{
    /**
     * The charger's live charging values.
     *
     * @return array<string, mixed>
     */
    public function chargeRealtime(int $stationId, string $serial): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetDcChargerChargeRealtimeRequest($stationId, $serial))->json('data');
    }

    /**
     * The charger's allowed charge power and the vehicle SOC it stops charging at.
     *
     * @return array<string, mixed>
     */
    public function chargeSetting(int $stationId, string $serial): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetDcChargerChargeSettingRequest($stationId, $serial))->json('data');
    }

    /**
     * The charger's live discharging (V2X) values.
     *
     * @return array<string, mixed>
     */
    public function dischargeRealtime(int $stationId, string $serial): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetDcChargerDischargeRealtimeRequest($stationId, $serial))->json('data');
    }

    /**
     * The energy the charger has charged and discharged over recent periods.
     *
     * @return array<string, mixed>
     */
    public function energyTotals(int $stationId, string $serial): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetDcChargerEnergyTotalsRequest($stationId, $serial))->json('data');
    }

    /**
     * The energy the charger has charged and discharged over its lifetime.
     *
     * @return array<string, mixed>
     */
    public function lifetimeTotals(int $stationId, string $serial): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetDcChargerLifetimeTotalsRequest($stationId, $serial))->json('data');
    }

    /**
     * The charger's current charge mode and its mode-specific settings.
     *
     * @return array<string, mixed>
     */
    public function mode(int $stationId, string $serial): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetDcChargerModeRequest($stationId, $serial))->json('data');
    }

    /**
     * The SOC ranges the station allows for DC charger mode settings.
     *
     * @return array<string, mixed>
     */
    public function modeSocRange(int $stationId): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetDcChargerModeSocRangeRequest($stationId))->json('data');
    }

    /**
     * The charger's OCPP connection status.
     *
     * @return array<string, mixed>
     */
    public function ocppStatus(int $stationId, string $serial): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetDcChargerOcppStatusRequest($stationId, $serial))->json('data');
    }

    public function plugStatus(int $stationId, string $serial): ?bool
    {
        $data = $this->connector->send(new GetDcChargerPlugStatusRequest($stationId, $serial))->json('data');
        if ($data === null || is_bool($data)) {
            return $data;
        }
        if (is_array($data)) {
            foreach (['plugged', 'pluggedIn', 'isPlugged', 'plugStatus', 'status'] as $key) {
                if (array_key_exists($key, $data)) {
                    return (bool) $data[$key];
                }
            }
        }

        return (bool) $data;
    }

    /**
     * A page of the charger's charge and discharge sessions between two dates, inclusive.
     *
     * @return array<string, mixed>
     */
    public function sessionRecords(
        int $stationId,
        string $serial,
        DateTimeInterface $from,
        DateTimeInterface $to,
        int $page = 1,
        int $perPage = 10,
    ): array {
        /** @var array<string, mixed> */
        return $this->connector->send(
            new GetDcChargerSessionRecordsRequest($stationId, $serial, $from, $to, $page, $perPage),
        )->json('data');
    }

    public function setChargeEnabled(int $stationId, string $serial, bool $enabled): void
    {
        $this->connector->send(new UpdateDcChargerChargeEnabledRequest($stationId, $serial, $enabled));
    }

    public function setChargeSetting(int $stationId, string $serial, float $allowedChargePower, int $vehicleChargingCutoffSoc): void
    {
        $this->connector->send(new UpdateDcChargerChargeSettingRequest($stationId, $serial, $allowedChargePower, $vehicleChargingCutoffSoc));
    }

    public function setMode(int $stationId, string $serial, int $chargeMode, ?bool $enableFromPack = null, ?float $cutoffSocFromPack = null, ?float $allowsDischargePower = null, ?int $vehicleDischargeCutoffSoc = null): void
    {
        $this->connector->send(new UpdateDcChargerModeRequest($stationId, $serial, $chargeMode, $enableFromPack, $cutoffSocFromPack, $allowsDischargePower, $vehicleDischargeCutoffSoc));
    }

    public function stationIsCharging(int $stationId): bool
    {
        return (bool) $this->connector->send(new GetDcChargerStationChargingRequest($stationId))->json('data');
    }

    /**
     * The charger's overall status.
     *
     * @return array<string, mixed>
     */
    public function status(int $stationId, string $serial): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetDcChargerStatusRequest($stationId, $serial))->json('data');
    }
}

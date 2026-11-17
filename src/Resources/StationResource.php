<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Resources;

use Dyrynda\SigenCloud\Data\Station;
use Dyrynda\SigenCloud\Data\StationTopology;
use Dyrynda\SigenCloud\Data\TopologyNode;
use Dyrynda\SigenCloud\Exceptions\SigenCloudException;
use Dyrynda\SigenCloud\Exceptions\SigenCloudRateLimitException;
use Dyrynda\SigenCloud\Requests\GetStationAioSerialsRequest;
use Dyrynda\SigenCloud\Requests\GetStationAlarmsRequest;
use Dyrynda\SigenCloud\Requests\GetStationEnergyFlowAsyncRequest;
use Dyrynda\SigenCloud\Requests\GetStationEnergyFlowRequest;
use Dyrynda\SigenCloud\Requests\GetStationGatewayRequest;
use Dyrynda\SigenCloud\Requests\GetStationGridConnectionPointDevicesRequest;
use Dyrynda\SigenCloud\Requests\GetStationPredictionRequest;
use Dyrynda\SigenCloud\Requests\GetStationRequest;
use Dyrynda\SigenCloud\Requests\GetStationTopologyRequest;
use Saloon\Http\BaseResource;

final class StationResource extends BaseResource
{
    /**
     * A page of the station's active alarms, with the alarms under `records`.
     *
     * @return array<string, mixed>
     */
    public function activeAlarms(int $stationId, int $page = 1, int $pageSize = 10): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetStationAlarmsRequest($stationId, $page, $pageSize))->json('data');
    }

    /**
     * The serial numbers of the station's all-in-one (SigenStor) units.
     *
     * @return list<string>
     */
    public function aioSerials(int $stationId): array
    {
        $data = $this->connector->send(new GetStationAioSerialsRequest($stationId))->json('data');
        if (! is_array($data)) {
            return [];
        }

        if (! array_is_list($data)) {
            foreach (['snList', 'aioSnList', 'list', 'data'] as $key) {
                if (isset($data[$key]) && is_array($data[$key]) && array_is_list($data[$key])) {
                    $data = $data[$key];
                    break;
                }
            }
        }

        if (! array_is_list($data)) {
            return [];
        }

        return array_values(array_map(strval(...), array_filter($data, fn ($value): bool => (is_string($value) && $value !== '') || (is_int($value) && $value !== 0))));
    }

    public function current(): Station
    {
        return Station::fromResponse($this->connector->send(
            new GetStationRequest
        ));
    }

    /**
     * The real-time power flowing between solar, battery, grid and load.
     *
     * Falls back to the station energy flow endpoint when Sigen rejects the async one, unless it is rate limiting.
     *
     * @return array<string, mixed>
     */
    public function energyFlow(int $stationId): array
    {
        try {
            $response = $this->connector->send(new GetStationEnergyFlowAsyncRequest);
        } catch (SigenCloudRateLimitException $exception) {
            throw $exception;
        } catch (SigenCloudException) {
            $response = $this->connector->send(new GetStationEnergyFlowRequest($stationId));
        }

        /** @var array<string, mixed> */
        return $response->json('data');
    }

    /**
     * The Sigen Gateway's details with its per-phase grid-side values as display strings.
     *
     * @return array<string, mixed>
     */
    public function gateway(int $stationId): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetStationGatewayRequest($stationId))->json('data');
    }

    /**
     * The devices attached to the grid connection point, such as the gateway.
     *
     * @return list<array<string, mixed>>
     */
    public function gridConnectionPointDevices(int $stationId): array
    {
        /** @var list<array<string, mixed>> */
        return $this->connector->send(new GetStationGridConnectionPointDevicesRequest($stationId))->json('data');
    }

    /**
     * Sigen AI's forecast and plan series, or an empty array when it has no forecast.
     *
     * @return array<string, mixed>
     */
    public function prediction(int $stationId): array
    {
        /** @var array<string, mixed> */
        return $this->connector->send(new GetStationPredictionRequest($stationId))->json('data');
    }

    public function topology(int $stationId): StationTopology
    {
        return StationTopology::fromResponse($this->connector->send(new GetStationTopologyRequest($stationId)));
    }

    /** @return array{station_status: ?int, sn_code: ?string, device_type: ?int, device_type_desc: ?string, device_status: ?int, communicate_status: ?int, offline: ?bool, node_found: bool} */
    public function topologyEvdcStatus(int $stationId, ?string $dcSn = null): array
    {
        $topology = $this->topology($stationId);
        $node = $topology->findNode(TopologyNode::DC_CHARGER, $dcSn)
            ?? $topology->findNode(TopologyNode::DC_CHARGER);

        return [
            'station_status' => $topology->stationStatus,
            'sn_code' => $node?->snCode ?: $node?->showSnCode,
            'device_type' => $node?->deviceType,
            'device_type_desc' => $node?->deviceTypeDesc,
            'device_status' => $node?->deviceStatus,
            'communicate_status' => $node?->communicateStatus,
            'offline' => $node?->isOffline(),
            'node_found' => $node !== null,
        ];
    }
}

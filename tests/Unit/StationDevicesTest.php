<?php

use Dyrynda\SigenCloud\Exceptions\SigenCloudRateLimitException;
use Dyrynda\SigenCloud\Requests\GetStationAioSerialsRequest;
use Dyrynda\SigenCloud\Requests\GetStationAlarmsRequest;
use Dyrynda\SigenCloud\Requests\GetStationEnergyFlowAsyncRequest;
use Dyrynda\SigenCloud\Requests\GetStationEnergyFlowRequest;
use Dyrynda\SigenCloud\Requests\GetStationGatewayRequest;
use Dyrynda\SigenCloud\Requests\GetStationGridConnectionPointDevicesRequest;
use Dyrynda\SigenCloud\Requests\GetStationPredictionRequest;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('reads the real-time energy flow', function () {
    $mockClient = MockClient::global([
        GetStationEnergyFlowAsyncRequest::class => MockResponse::make([
            'code' => 0,
            'msg' => 'success',
            'data' => ['pvPower' => 11.3, 'batterySoc' => 92.5],
        ]),
    ]);

    $energyFlow = authenticatedSigen()->stations()->energyFlow(120250610002197);

    expect($energyFlow)->toBe(['pvPower' => 11.3, 'batterySoc' => 92.5])
        ->and(sentRequest($mockClient, GetStationEnergyFlowAsyncRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/sigen/station/energyflow/async')
        ->query()->all()->toBe(['refreshFlag' => 'false']);
});

it('falls back to the station energy flow when Sigen rejects the async request', function () {
    $mockClient = MockClient::global([
        GetStationEnergyFlowAsyncRequest::class => MockResponse::make(['code' => 1000, 'msg' => 'param illegal', 'data' => []]),
        GetStationEnergyFlowRequest::class => MockResponse::make([
            'code' => 0,
            'msg' => 'success',
            'data' => ['pvPower' => 11.3, 'batterySoc' => 92.5],
        ]),
    ]);

    $energyFlow = authenticatedSigen()->stations()->energyFlow(120250610002197);

    expect($energyFlow)->toBe(['pvPower' => 11.3, 'batterySoc' => 92.5])
        ->and(sentRequest($mockClient, GetStationEnergyFlowRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/sigen/station/energyflow')
        ->query()->all()->toBe(['id' => 120250610002197]);
});

it('does not fall back to the station energy flow when Sigen rate limits the async request', function () {
    $mockClient = MockClient::global([
        GetStationEnergyFlowAsyncRequest::class => MockResponse::make(['code' => 429, 'msg' => 'Too many requests', 'data' => null]),
        GetStationEnergyFlowRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => []]),
    ]);

    expect(fn () => authenticatedSigen()->stations()->energyFlow(120250610002197))
        ->toThrow(SigenCloudRateLimitException::class);

    $mockClient->assertNotSent(GetStationEnergyFlowRequest::class);
});

it('reads the AI forecast and plan for the station', function () {
    $mockClient = MockClient::global([
        GetStationPredictionRequest::class => MockResponse::make([
            'code' => 0,
            'msg' => 'success',
            'data' => ['nowTime' => '2026-05-19 21:46', 'pvList' => [['timestamp' => 1779192000, 'value' => 10.5]]],
        ]),
    ]);

    $prediction = authenticatedSigen()->stations()->prediction(120250610002197);

    expect($prediction)->toBe(['nowTime' => '2026-05-19 21:46', 'pvList' => [['timestamp' => 1779192000, 'value' => 10.5]]])
        ->and(sentRequest($mockClient, GetStationPredictionRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/prediction/predictData/get/predictData/120250610002197');
});

it('reads the gateway and its grid-side values', function () {
    $gateway = [
        'snCode' => '110G138D1898',
        'deviceType' => 8,
        'gridSideInfoList' => [['paramKey' => 'Phase A Voltage', 'paramValue' => '237.72 V']],
    ];
    $mockClient = MockClient::global([
        GetStationGatewayRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => $gateway]),
    ]);

    expect(authenticatedSigen()->stations()->gateway(120250610002197))->toBe($gateway)
        ->and(sentRequest($mockClient, GetStationGatewayRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/gateway/120250610002197');
});

it('reads the devices attached to the grid connection point', function () {
    $devices = [['snCode' => '110G138D1898', 'deviceType' => 8, 'deviceStatus' => 1, 'communicationStatus' => 2]];
    $mockClient = MockClient::global([
        GetStationGridConnectionPointDevicesRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => $devices]),
    ]);

    expect(authenticatedSigen()->stations()->gridConnectionPointDevices(120250610002197))->toBe($devices)
        ->and(sentRequest($mockClient, GetStationGridConnectionPointDevicesRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/station/grid-connection-point/support-devices')
        ->query()->all()->toBe(['stationId' => 120250610002197]);
});

it('reads a page of active alarms', function () {
    $alarms = ['records' => [['alarmCode' => 1001]], 'total' => 21, 'size' => 20, 'current' => 2, 'pages' => 2];
    $mockClient = MockClient::global([
        GetStationAlarmsRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => $alarms]),
    ]);

    expect(authenticatedSigen()->stations()->activeAlarms(120250610002197, page: 2, pageSize: 20))->toBe($alarms)
        ->and(sentRequest($mockClient, GetStationAlarmsRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/alarm/page')
        ->query()->all()->toBe(['stationId' => 120250610002197, 'current' => 2, 'size' => 20]);
});

it('reads the all-in-one serial numbers of the station', function () {
    $mockClient = MockClient::global([
        GetStationAioSerialsRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => ['CMU110A335X0463', 'CMU110A335X0464']]),
    ]);

    expect(authenticatedSigen()->stations()->aioSerials(120250610002197))->toBe(['CMU110A335X0463', 'CMU110A335X0464'])
        ->and(sentRequest($mockClient, GetStationAioSerialsRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/aio/by/station')
        ->query()->all()->toBe(['stationId' => 120250610002197]);
});

it('normalizes wrapped all-in-one serial numbers and discards empty entries', function () {
    MockClient::global([
        GetStationAioSerialsRequest::class => MockResponse::make(['code' => 0, 'data' => ['aioSnList' => [123, '', null, 'CMU2']]]),
    ]);

    expect(authenticatedSigen()->stations()->aioSerials(123))->toBe(['123', 'CMU2']);
});

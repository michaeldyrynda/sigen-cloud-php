<?php

use Dyrynda\SigenCloud\Requests\GetDcChargerChargeRealtimeRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerChargeSettingRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerDischargeRealtimeRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerEnergyTotalsRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerLifetimeTotalsRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerModeRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerModeSocRangeRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerOcppStatusRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerSessionRecordsRequest;
use Dyrynda\SigenCloud\Requests\GetDcChargerStatusRequest;
use Dyrynda\SigenCloud\Resources\DcChargerResource;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('reads raw charger data from its endpoint for the charger', function (Closure $read, string $requestClass, string $path, array $query) {
    $data = ['chargeMode' => 2, 'snCode' => 'DC1102512345678'];
    $mockClient = MockClient::global([
        $requestClass => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => $data]),
    ]);

    expect($read(authenticatedSigen()->dcCharger()))->toBe($data);

    $pendingRequest = sentRequest($mockClient, $requestClass);

    expect($pendingRequest)
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe("https://api-aus.sigencloud.com{$path}")
        ->query()->all()->toBe($query);
})->with([
    'charge mode' => [
        fn (DcChargerResource $dcCharger) => $dcCharger->mode(120250610002197, 'DC1102512345678'),
        GetDcChargerModeRequest::class,
        '/device/charge/mode/dc',
        ['stationId' => 120250610002197, 'snCode' => 'DC1102512345678'],
    ],
    'charge mode SOC ranges' => [
        fn (DcChargerResource $dcCharger) => $dcCharger->modeSocRange(120250610002197),
        GetDcChargerModeSocRangeRequest::class,
        '/device/charge/mode/soc/range',
        ['stationId' => 120250610002197],
    ],
    'charge setting' => [
        fn (DcChargerResource $dcCharger) => $dcCharger->chargeSetting(120250610002197, 'DC1102512345678'),
        GetDcChargerChargeSettingRequest::class,
        '/device/dcevse/charge/setting',
        ['stationId' => 120250610002197, 'snCode' => 'DC1102512345678'],
    ],
    'status' => [
        fn (DcChargerResource $dcCharger) => $dcCharger->status(120250610002197, 'DC1102512345678'),
        GetDcChargerStatusRequest::class,
        '/device/dcevse/status',
        ['stationId' => 120250610002197, 'snCode' => 'DC1102512345678'],
    ],
    'charging in real time' => [
        fn (DcChargerResource $dcCharger) => $dcCharger->chargeRealtime(120250610002197, 'DC1102512345678'),
        GetDcChargerChargeRealtimeRequest::class,
        '/device/dcevse/charge/realtime',
        ['stationId' => 120250610002197, 'snCode' => 'DC1102512345678'],
    ],
    'discharging in real time' => [
        fn (DcChargerResource $dcCharger) => $dcCharger->dischargeRealtime(120250610002197, 'DC1102512345678'),
        GetDcChargerDischargeRealtimeRequest::class,
        '/device/dcevse/discharge/realtime',
        ['stationId' => 120250610002197, 'snCode' => 'DC1102512345678'],
    ],
    'period energy totals' => [
        fn (DcChargerResource $dcCharger) => $dcCharger->energyTotals(120250610002197, 'DC1102512345678'),
        GetDcChargerEnergyTotalsRequest::class,
        '/data-process/dcevse/energy',
        ['stationId' => 120250610002197, 'snCode' => 'DC1102512345678'],
    ],
    'OCPP status' => [
        fn (DcChargerResource $dcCharger) => $dcCharger->ocppStatus(120250610002197, 'DC1102512345678'),
        GetDcChargerOcppStatusRequest::class,
        '/device/dcevse/ocpp/status',
        ['stationId' => 120250610002197, 'snCode' => 'DC1102512345678'],
    ],
    'lifetime totals' => [
        fn (DcChargerResource $dcCharger) => $dcCharger->lifetimeTotals(120250610002197, 'DC1102512345678'),
        GetDcChargerLifetimeTotalsRequest::class,
        '/data-process/dcevse/total/120250610002197/DC1102512345678',
        [],
    ],
]);

it('reads a page of charge and discharge sessions between two dates', function () {
    $records = ['records' => [['chargeEnergy' => 12.4]], 'total' => 31, 'current' => 3, 'size' => 5];
    $mockClient = MockClient::global([
        GetDcChargerSessionRecordsRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => $records]),
    ]);

    $sessions = authenticatedSigen()->dcCharger()->sessionRecords(
        120250610002197,
        'DC1102512345678',
        from: new DateTimeImmutable('2026-03-05 23:30:00'),
        to: new DateTimeImmutable('2026-04-09 00:15:00'),
        page: 3,
        perPage: 5,
    );

    expect($sessions)->toBe($records)
        ->and(sentRequest($mockClient, GetDcChargerSessionRecordsRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/data-process/dcevse/record/page')
        ->query()->all()->toBe([
            'stationId' => 120250610002197,
            'snCode' => 'DC1102512345678',
            'current' => 3,
            'size' => 5,
            'startTime' => '20260305',
            'endTime' => '20260409',
        ]);
});

it('updates the charger mode preserving optional false and zero values', function () {
    $mock = MockClient::global(['*' => MockResponse::make(['code' => 0, 'data' => true])]);
    authenticatedSigen()->dcCharger()->setMode(12345, 'DC123', 2, enableFromPack: false, cutoffSocFromPack: 0.0);
    expect(sentRequest($mock, Dyrynda\SigenCloud\Requests\UpdateDcChargerModeRequest::class))
        ->getMethod()->toBe(Method::POST)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/charge/mode/dc')
        ->body()->all()->toBe(['stationId' => 12345, 'snCode' => 'DC123', 'chargeMode' => 2, 'enableFromPack' => false, 'cutoffSocFromPack' => 0.0]);
});

it('updates DC charge power and cutoff', function () {
    $mock = MockClient::global(['*' => MockResponse::make(['code' => 0])]);
    authenticatedSigen()->dcCharger()->setChargeSetting(12345, 'DC123', 6.5, 83);
    expect(sentRequest($mock, Dyrynda\SigenCloud\Requests\UpdateDcChargerChargeSettingRequest::class))
        ->getMethod()->toBe(Method::POST)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/dcevse/charge/setting')
        ->body()->all()->toBe(['stationId' => 12345, 'snCode' => 'DC123', 'allowedChargePower' => 6.5, 'vehicleChargingCutoffSoc' => 83]);
});

it('starts and stops DC charging with inverted enable values', function (bool $enabled, int $value) {
    $mock = MockClient::global(['*' => MockResponse::make(['code' => 0])]);
    authenticatedSigen()->dcCharger()->setChargeEnabled(12345, 'DC123', $enabled);
    expect(sentRequest($mock, Dyrynda\SigenCloud\Requests\UpdateDcChargerChargeEnabledRequest::class))
        ->getMethod()->toBe(Method::PUT)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/dcevse/charge/start')
        ->query()->all()->toBe(['stationId' => 12345, 'snCode' => 'DC123', 'enable' => $value]);
})->with([[true, 0], [false, 1]]);

it('checks whether the station is charging', function () {
    $mock = MockClient::global(['*' => MockResponse::make(['code' => 0, 'data' => true])]);
    expect(authenticatedSigen()->dcCharger()->stationIsCharging(12345))->toBeTrue();
    expect(sentRequest($mock, Dyrynda\SigenCloud\Requests\GetDcChargerStationChargingRequest::class))
        ->getMethod()->toBe(Method::POST)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/charge/check/charge')
        ->query()->all()->toBe(['stationId' => 12345]);
});

it('interprets DC plug status response variants', function (mixed $data, ?bool $expected) {
    $mock = MockClient::global(['*' => MockResponse::make(['code' => 0, 'data' => $data])]);
    expect(authenticatedSigen()->dcCharger()->plugStatus(12345, 'DC123'))->toBe($expected);
    expect(sentRequest($mock, Dyrynda\SigenCloud\Requests\GetDcChargerPlugStatusRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/dcevse/plug/status')
        ->query()->all()->toBe(['stationId' => 12345, 'snCode' => 'DC123']);
})->with([[true, true], [false, false], [null, null], [['pluggedIn' => 0], false], [['isPlugged' => 1], true], [0, false]]);

it('defaults session pagination to the first ten records', function () {
    $mock = MockClient::global(['*' => MockResponse::make(['code' => 0, 'data' => ['records' => []]])]);
    authenticatedSigen()->dcCharger()->sessionRecords(12345, 'DC123', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-02-01'));
    expect(sentRequest($mock, GetDcChargerSessionRecordsRequest::class)->query()->all())->toBe([
        'stationId' => 12345, 'snCode' => 'DC123', 'current' => 1, 'size' => 10, 'startTime' => '20260101', 'endTime' => '20260201',
    ]);
});

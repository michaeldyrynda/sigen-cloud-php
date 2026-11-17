<?php

use Dyrynda\SigenCloud\Requests\GetBatteryPowerLimitRequest;
use Dyrynda\SigenCloud\Requests\UpdateBatteryPowerLimitRequest;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('reads the battery charge and discharge power limits', function () {
    $mockClient = MockClient::global([
        GetBatteryPowerLimitRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => [
            'batteryMaxChargingPower' => '4294967.295',
            'batteryMaxDischargingPower' => '5.000',
        ]]),
    ]);

    expect(authenticatedSigen()->battery()->powerLimit(120250610002197))->toBe([
        'batteryMaxChargingPower' => '4294967.295',
        'batteryMaxDischargingPower' => '5.000',
    ]);
    expect(sentRequest($mockClient, GetBatteryPowerLimitRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/energy-profile/battery/limit/120250610002197');
});

it('limits battery power in kilowatts, sending the unlimited sentinel for a null limit', function () {
    $mockClient = MockClient::global([
        UpdateBatteryPowerLimitRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    authenticatedSigen()->battery()->setPowerLimit(120250610002197, maxChargeKilowatts: null, maxDischargeKilowatts: 4.25);

    expect(sentRequest($mockClient, UpdateBatteryPowerLimitRequest::class))
        ->getMethod()->toBe(Method::PUT)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/energy-profile/battery/limit')
        ->body()->all()->toBe([
            'stationId' => 120250610002197,
            'batteryMaxChargingPower' => '4294967.295',
            'batteryMaxDischargingPower' => '4.250',
        ]);
});

it('rejects a negative battery power limit without sending it', function (?float $maxCharge, ?float $maxDischarge) {
    $mockClient = MockClient::global([
        UpdateBatteryPowerLimitRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    expect(fn () => authenticatedSigen()->battery()->setPowerLimit(120250610002197, $maxCharge, $maxDischarge))
        ->toThrow(InvalidArgumentException::class);

    $mockClient->assertNothingSent();
})->with([
    'charging' => [-0.001, null],
    'discharging' => [null, -0.001],
]);

it('blocks battery charging with a zero limit rather than treating it as unlimited', function () {
    $mockClient = MockClient::global([
        UpdateBatteryPowerLimitRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    authenticatedSigen()->battery()->setPowerLimit(120250610002197, maxChargeKilowatts: 0.0, maxDischargeKilowatts: null);

    expect(sentRequest($mockClient, UpdateBatteryPowerLimitRequest::class)->body()?->all())->toBe([
        'stationId' => 120250610002197,
        'batteryMaxChargingPower' => '0.000',
        'batteryMaxDischargingPower' => '4294967.295',
    ]);
});

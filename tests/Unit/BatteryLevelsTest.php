<?php

use Dyrynda\SigenCloud\Data\BatteryLevels;
use Dyrynda\SigenCloud\Requests\GetBatteryLevelsRequest;
use Dyrynda\SigenCloud\Requests\UpdateBatteryLevelsRequest;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('reads the battery state of charge thresholds as whole percentages', function () {
    $mockClient = MockClient::global([
        GetBatteryLevelsRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => [
            'chargeSOC' => '95.0',
            'dischargeSOC' => '5.0',
            'backupSOC' => '4.0',
            'peakShavingSOC' => '11',
            'ja12SOC' => '0.0',
        ]]),
    ]);

    $levels = authenticatedSigen()->battery()->levels(120250610002197);

    expect($levels)
        ->chargeSoc->toBe(95)
        ->dischargeSoc->toBe(5)
        ->peakShavingSoc->toBe(11)
        ->backupSoc->toBe(4);
    expect(sentRequest($mockClient, GetBatteryLevelsRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/energy-profile/battery/level/120250610002197');
});

it('reads a blank peak shaving threshold as unset, as an Australian station reports it', function () {
    MockClient::global([
        GetBatteryLevelsRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => [
            'chargeSOC' => '100.0',
            'dischargeSOC' => '0.0',
            'backupSOC' => '4.0',
            'peakShavingSOC' => '',
            'ja12SOC' => '0.0',
        ]]),
    ]);

    expect(authenticatedSigen()->battery()->levels(120250610002197))
        ->chargeSoc->toBe(100)
        ->dischargeSoc->toBe(0)
        ->peakShavingSoc->toBeNull()
        ->backupSoc->toBe(4);
});

it('replaces the battery thresholds with each percentage sent as a string', function () {
    $mockClient = MockClient::global([
        UpdateBatteryLevelsRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    authenticatedSigen()->battery()->setLevels(120250610002197, new BatteryLevels(
        chargeSoc: 95,
        dischargeSoc: 5,
        peakShavingSoc: 11,
        backupSoc: 4,
    ));

    expect(sentRequest($mockClient, UpdateBatteryLevelsRequest::class))
        ->getMethod()->toBe(Method::PUT)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/energy-profile/battery/level')
        ->body()->all()->toBe([
            'stationId' => 120250610002197,
            'chargeSOC' => '95',
            'dischargeSOC' => '5',
            'peakShavingSOC' => '11',
            'backupSOC' => '4',
        ]);
});

it('sends an unset peak shaving threshold back blank rather than as zero', function () {
    $mockClient = MockClient::global([
        GetBatteryLevelsRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => [
            'chargeSOC' => '100.0',
            'dischargeSOC' => '0.0',
            'backupSOC' => '4.0',
            'peakShavingSOC' => '',
            'ja12SOC' => '0.0',
        ]]),
        UpdateBatteryLevelsRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);
    $battery = authenticatedSigen()->battery();

    $levels = $battery->levels(120250610002197);
    $battery->setLevels(120250610002197, new BatteryLevels(
        chargeSoc: 90,
        dischargeSoc: $levels->dischargeSoc,
        peakShavingSoc: $levels->peakShavingSoc,
        backupSoc: $levels->backupSoc,
    ));

    expect(sentRequest($mockClient, UpdateBatteryLevelsRequest::class)->body()?->all())->toBe([
        'stationId' => 120250610002197,
        'chargeSOC' => '90',
        'dischargeSOC' => '0',
        'peakShavingSOC' => '',
        'backupSOC' => '4',
    ]);
});

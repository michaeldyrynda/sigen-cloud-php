<?php

use Dyrynda\SigenCloud\Data\PeakShavingSlot;
use Dyrynda\SigenCloud\Requests\GetBatteryPeakShavingScheduleRequest;
use Dyrynda\SigenCloud\Requests\UpdateBatteryPeakShavingScheduleRequest;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('reads the peak shaving schedule with each slot numbered in order', function () {
    $mockClient = MockClient::global([
        GetBatteryPeakShavingScheduleRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => [
            'controlMode' => 1,
            'shavingSOC' => 10,
            'settingList' => [
                ['whichDay' => '1,2,3,4,5', 'startTime' => '00:00', 'endTime' => '06:00', 'peakPower' => '9.5'],
                ['whichDay' => '6,7', 'startTime' => '16:00', 'endTime' => '21:00', 'peakPower' => '3.25'],
            ],
        ]]),
    ]);

    $schedule = authenticatedSigen()->battery()->peakShavingSchedule(120250610002197);

    expect($schedule)
        ->enabled->toBeTrue()
        ->shavingSoc->toBe(10)
        ->slots->toHaveCount(2)
        ->slots->{0}->index->toBe(0)
        ->slots->{0}->days->toBe([1, 2, 3, 4, 5])
        ->slots->{0}->startTime->toBe('00:00')
        ->slots->{0}->endTime->toBe('06:00')
        ->slots->{0}->peakPowerKilowatts->toBe(9.5)
        ->slots->{1}->index->toBe(1)
        ->slots->{1}->days->toBe([6, 7])
        ->slots->{1}->startTime->toBe('16:00')
        ->slots->{1}->endTime->toBe('21:00')
        ->slots->{1}->peakPowerKilowatts->toBe(3.25);
    expect(sentRequest($mockClient, GetBatteryPeakShavingScheduleRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/dischargesetting/peak/shaving/120250610002197');
});

it('rejects a malformed schedule response', function () {
    MockClient::global([
        GetBatteryPeakShavingScheduleRequest::class => MockResponse::make(['code' => 0, 'data' => [
            'settingList' => [['whichDay' => '1', 'startTime' => '10:00', 'endTime' => '12:00', 'peakPower' => []]],
        ]]),
    ]);

    expect(fn () => authenticatedSigen()->battery()->peakShavingSchedule(42))
        ->toThrow(UnexpectedValueException::class);
});

it('updates one slot while preserving the rest of the schedule', function () {
    $mockClient = MockClient::global([
        GetBatteryPeakShavingScheduleRequest::class => MockResponse::make(['code' => 0, 'data' => [
            'controlMode' => 1, 'shavingSOC' => 17, 'settingList' => [
                ['whichDay' => '1,3', 'startTime' => '01:00', 'endTime' => '04:00', 'peakPower' => '8.5'],
                ['whichDay' => '6,7', 'startTime' => '16:00', 'endTime' => '21:00', 'peakPower' => '3.25'],
            ],
        ]]),
        UpdateBatteryPeakShavingScheduleRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
    ]);

    authenticatedSigen()->battery()->setPeakShavingSlot(42, new PeakShavingSlot(1, [2, 4], '12:00', '15:00', 5.75));

    $request = sentRequest($mockClient, UpdateBatteryPeakShavingScheduleRequest::class);
    expect($request->getMethod())->toBe(Method::POST)
        ->and($request->getUrl())->toBe('https://api-aus.sigencloud.com/device/dischargesetting/peak/shaving')
        ->and($request->body()->all())->toBe([
            'stationId' => 42, 'controlMode' => 1, 'shavingSOC' => 17, 'settingList' => [
                ['whichDay' => '1,3', 'startTime' => '01:00', 'endTime' => '04:00', 'peakPower' => 8.5],
                ['whichDay' => '2,4', 'startTime' => '12:00', 'endTime' => '15:00', 'peakPower' => 5.75],
            ],
        ]);
});

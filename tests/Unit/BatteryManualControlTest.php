<?php

use Dyrynda\SigenCloud\Enums\ManualControlMode;
use Dyrynda\SigenCloud\Requests\GetManualControlDisplayRequest;
use Dyrynda\SigenCloud\Requests\GetManualControlRequest;
use Dyrynda\SigenCloud\Requests\UpdateManualControlRequest;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

function manualControlBodySent(MockClient $mockClient): ?array
{
    $body = null;

    $mockClient->assertSent(function (Request $request, Response $response) use (&$body) {
        $pendingRequest = $response->getPendingRequest();

        if ($pendingRequest->getMethod() !== Method::PUT
            || $pendingRequest->getUrl() !== 'https://api-aus.sigencloud.com/device/energy-profile/instant/manunal') {
            return false;
        }

        $body = $pendingRequest->body()?->all();

        return true;
    });

    return $body;
}

it('charges the battery at full power for the given minutes', function () {
    $mockClient = MockClient::global([
        UpdateManualControlRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    authenticatedSigen()->battery()->startManualControl(120250610002197, ManualControlMode::Charging, minutes: 30);

    expect(manualControlBodySent($mockClient))->toBe([
        'enable' => true,
        'stationId' => 120250610002197,
        'mode' => '0',
        'duration' => '30',
        'powerLimitation' => '4294967.295',
    ]);
});

it('holds the battery without a power limitation', function () {
    $mockClient = MockClient::global([
        UpdateManualControlRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    authenticatedSigen()->battery()->startManualControl(120250610002197, ManualControlMode::HoldBattery, minutes: 45);

    expect(manualControlBodySent($mockClient))->toBe([
        'enable' => true,
        'stationId' => 120250610002197,
        'mode' => '2',
        'duration' => '45',
        'powerLimitation' => '',
    ]);
});

it('rejects a manual control duration the app does not allow without sending it', function (int $minutes) {
    $mockClient = MockClient::global([
        UpdateManualControlRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    expect(fn () => authenticatedSigen()->battery()->startManualControl(120250610002197, ManualControlMode::Charging, $minutes))
        ->toThrow(InvalidArgumentException::class);

    $mockClient->assertNothingSent();
})->with([
    'under 30 minutes' => 29,
    'over 120 minutes' => 121,
]);

it('hands the battery back to the active strategy', function () {
    $mockClient = MockClient::global([
        UpdateManualControlRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    authenticatedSigen()->battery()->stopManualControl(120250610002197);

    expect(manualControlBodySent($mockClient))->toBe([
        'enable' => false,
        'stationId' => 120250610002197,
        'mode' => '',
        'duration' => '',
        'powerLimitation' => '',
    ]);
});

it('reads the active manual control mode and when it ends', function () {
    MockClient::global([
        GetManualControlRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => [
            'enable' => true,
            'mode' => '1',
            'endTime' => '1776471497',
        ]]),
    ]);

    $control = authenticatedSigen()->battery()->manualControl(120250610002197);

    expect($control)
        ->enabled->toBeTrue()
        ->mode->toBe(ManualControlMode::Discharging)
        ->endsAt->format(DATE_ATOM)->toBe('2026-04-18T00:18:17+00:00');
});

it('reads manual control as off with no mode or end when the battery follows its strategy', function (array $control) {
    MockClient::global([
        GetManualControlRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => $control]),
    ]);

    expect(authenticatedSigen()->battery()->manualControl(120250610002197))
        ->enabled->toBeFalse()
        ->mode->toBeNull()
        ->endsAt->toBeNull();
})->with([
    'zeroed, as an Australian station reports it' => [['enable' => false, 'mode' => '0', 'endTime' => '0']],
    'blank' => [['enable' => false, 'mode' => '', 'endTime' => '']],
]);

it('reads the battery power and charge shown on the manual control screen', function () {
    $mockClient = MockClient::global([
        GetManualControlDisplayRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => [
            'batteryPower' => 6.7,
            'batterySoc' => 11.1,
        ]]),
    ]);

    expect(authenticatedSigen()->battery()->manualControlDisplay(120250610002197))->toBe([
        'batteryPower' => 6.7,
        'batterySoc' => 11.1,
    ]);
    $mockClient->assertSent(fn (Request $request, Response $response) => $response->getPendingRequest()->getUrl()
        === 'https://api-aus.sigencloud.com/device/energy-profile/instant/manunal/display/120250610002197');
});

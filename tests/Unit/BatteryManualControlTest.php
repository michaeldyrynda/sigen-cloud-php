<?php

use Dyrynda\SigenCloud\Data\AccessToken;
use Dyrynda\SigenCloud\Enums\ManualControlMode;
use Dyrynda\SigenCloud\Enums\Region;
use Dyrynda\SigenCloud\Requests\UpdateManualControlRequest;
use Dyrynda\SigenCloud\SigenCloud;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

function authenticatedSigen(): SigenCloud
{
    return new SigenCloud('owner@example.com', 'secret', Region::Australia, new AccessToken(
        accessToken: 'cached-token',
        expiresAt: new DateTimeImmutable('+1 hour'),
    ));
}

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

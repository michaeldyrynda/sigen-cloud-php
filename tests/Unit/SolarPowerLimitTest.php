<?php

use Dyrynda\SigenCloud\Data\AccessToken;
use Dyrynda\SigenCloud\Enums\Region;
use Dyrynda\SigenCloud\Requests\GetSolarPowerLimitRequest;
use Dyrynda\SigenCloud\Requests\UpdateSolarPowerLimitRequest;
use Dyrynda\SigenCloud\SigenCloud;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

function sigen(): SigenCloud
{
    return new SigenCloud('owner@example.com', 'secret', Region::Australia, new AccessToken(
        accessToken: 'cached-token',
        expiresAt: new DateTimeImmutable('+1 hour'),
    ));
}

it('reads the PV power limit in kilowatts', function (string $powerLimit, float $kilowatts) {
    MockClient::global([
        GetSolarPowerLimitRequest::class => MockResponse::make([
            'code' => 0,
            'msg' => 'success',
            'data' => ['powerLimit' => $powerLimit],
        ]),
    ]);

    expect(sigen()->solar()->powerLimit(120250610002197))->toBe($kilowatts);
})->with([
    'curtailed to 6.5 kW' => ['6.500', 6.5],
    'fully curtailed' => ['0.000', 0.0],
]);

it('reads an unlimited PV power limit as null', function () {
    MockClient::global([
        GetSolarPowerLimitRequest::class => MockResponse::make([
            'code' => 0,
            'msg' => 'success',
            'data' => ['powerLimit' => '4294967.295'],
        ]),
    ]);

    expect(sigen()->solar()->powerLimit(120250610002197))->toBeNull();
});

it('limits PV power to the given kilowatts', function () {
    $mockClient = MockClient::global([
        UpdateSolarPowerLimitRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    sigen()->solar()->setPowerLimit(120250610002197, 2.5);

    $mockClient->assertSent(function (Request $request, Response $response) {
        $pendingRequest = $response->getPendingRequest();

        return $pendingRequest->getMethod() === Method::PUT
            && $pendingRequest->getUrl() === 'https://api-aus.sigencloud.com/device/energy-profile/solar/limit'
            && $pendingRequest->body()?->all() === ['powerLimit' => '2.500', 'stationId' => 120250610002197];
    });
});

it('rejects a negative PV power limit without sending it', function () {
    $mockClient = MockClient::global([
        UpdateSolarPowerLimitRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    expect(fn () => sigen()->solar()->setPowerLimit(120250610002197, -0.5))
        ->toThrow(InvalidArgumentException::class);

    $mockClient->assertNothingSent();
});

it('removes the PV power limit', function () {
    $mockClient = MockClient::global([
        UpdateSolarPowerLimitRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    sigen()->solar()->removePowerLimit(120250610002197);

    $mockClient->assertSent(fn (Request $request, Response $response) => $response->getPendingRequest()->body()?->all() === [
        'powerLimit' => '4294967.295',
        'stationId' => 120250610002197,
    ]);
});

<?php

use Dyrynda\SigenCloud\Enums\Region;
use Dyrynda\SigenCloud\Requests\AccessTokenRequest;
use Dyrynda\SigenCloud\Requests\GetStationRequest;
use Dyrynda\SigenCloud\SigenCloud;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

it('returns the station for the account', function () {
    MockClient::global([
        AccessTokenRequest::class => fixtureResponse('token'),
        GetStationRequest::class => fixtureResponse('station'),
    ]);

    $station = (new SigenCloud('owner@example.com', 'secret', Region::Australia))->stations()->current();

    expect($station)
        ->id->toBe(120250610002197)
        ->onGrid->toBeTrue()
        ->pvCapacity->toBe(13.6)
        ->batteryCapacity->toBe(32.24);
});

it('exposes the charger serials and home status of the station', function () {
    MockClient::global([
        GetStationRequest::class => MockResponse::make([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'stationId' => 120250610002197,
                'status' => 3,
                'statusDesc' => 'Shutdown',
                'shutdownReason' => 'Manual shutdown',
                'onGrid' => false,
                'pvCapacity' => 13.6,
                'batteryCapacity' => 32.24,
                'acSnList' => ['AC0001', 'AC0002'],
                'dcSnList' => [120250610002198],
            ],
        ]),
    ]);

    expect(authenticatedSigen()->stations()->current())
        ->status->toBe(3)
        ->statusDesc->toBe('Shutdown')
        ->shutdownReason->toBe('Manual shutdown')
        ->onGrid->toBeFalse()
        ->acSnList->toBe(['AC0001', 'AC0002'])
        ->dcSnList->toBe(['120250610002198']);
});

it('reads a station without a shutdown reason or charger serials', function () {
    MockClient::global([
        GetStationRequest::class => MockResponse::make([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'stationId' => 120250610002197,
                'status' => 1,
                'statusDesc' => 'Normal',
                'shutdownReason' => null,
                'onGrid' => true,
                'pvCapacity' => 13.6,
                'batteryCapacity' => 32.24,
                'acSnList' => null,
            ],
        ]),
    ]);

    expect(authenticatedSigen()->stations()->current())
        ->shutdownReason->toBeNull()
        ->acSnList->toBe([])
        ->dcSnList->toBe([]);
});

it('logs in with the mySigen app credentials and an encrypted password', function () {
    $mockClient = MockClient::global([
        AccessTokenRequest::class => fixtureResponse('token'),
        GetStationRequest::class => fixtureResponse('station'),
    ]);

    (new SigenCloud('owner@example.com', 'secret', Region::Australia))->stations()->current();

    $mockClient->assertSent(function (Request $request, Response $response) {
        $pendingRequest = $response->getPendingRequest();

        return $request instanceof AccessTokenRequest
            && $pendingRequest->getUrl() === 'https://api-aus.sigencloud.com/auth/oauth/token'
            && $pendingRequest->headers()->get('Authorization') === 'Basic '.base64_encode('sigen:sigen')
            && $pendingRequest->body()?->all() === [
                'username' => 'owner@example.com',
                'password' => 'HQxKZVgFCWUD/IS8SMsOBw==',
                'grant_type' => 'password',
            ];
    });
});

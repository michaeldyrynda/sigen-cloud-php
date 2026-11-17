<?php

use Dyrynda\SigenCloud\Enums\Region;
use Dyrynda\SigenCloud\Requests\AccessTokenRequest;
use Dyrynda\SigenCloud\Requests\GetStationRequest;
use Dyrynda\SigenCloud\SigenCloud;
use Saloon\Http\Faking\MockClient;
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

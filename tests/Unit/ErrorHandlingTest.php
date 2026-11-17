<?php

use Dyrynda\SigenCloud\Data\AccessToken;
use Dyrynda\SigenCloud\Enums\Region;
use Dyrynda\SigenCloud\Exceptions\SigenCloudAuthenticationException;
use Dyrynda\SigenCloud\Exceptions\SigenCloudException;
use Dyrynda\SigenCloud\Exceptions\SigenCloudRateLimitException;
use Dyrynda\SigenCloud\Requests\AccessTokenRequest;
use Dyrynda\SigenCloud\Requests\GetStationRequest;
use Dyrynda\SigenCloud\Requests\UpdateSolarPowerLimitRequest;
use Dyrynda\SigenCloud\SigenCloud;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('throws when Sigen rejects a request with a successful HTTP status', function () {
    MockClient::global([
        UpdateSolarPowerLimitRequest::class => MockResponse::make(['code' => 1000, 'msg' => 'param illegal', 'data' => []]),
    ]);

    $sigen = new SigenCloud('owner@example.com', 'secret', Region::Australia, new AccessToken(
        accessToken: 'cached-token',
        expiresAt: new DateTimeImmutable('+1 hour'),
    ));

    expect(fn () => $sigen->solar()->setPowerLimit(120250610002197, 0))
        ->toThrow(SigenCloudException::class, 'param illegal');
});

it('throws an authentication exception with the reason when the login is rejected', function () {
    $mockClient = MockClient::global([
        AccessTokenRequest::class => MockResponse::make(['error' => 'invalid_grant', 'error_description' => 'Bad credentials'], 401),
        GetStationRequest::class => fixtureResponse('station'),
    ]);

    expect(fn () => (new SigenCloud('owner@example.com', 'wrong', Region::Australia))->stations()->current())
        ->toThrow(SigenCloudAuthenticationException::class, 'Bad credentials');

    $mockClient->assertNotSent(GetStationRequest::class);
});

it('throws a rate limit exception when Sigen rate limits a request', function (MockResponse $response) {
    MockClient::global([
        GetStationRequest::class => $response,
    ]);

    expect(fn () => authenticatedSigen()->stations()->current())
        ->toThrow(SigenCloudRateLimitException::class, 'Too many requests');
})->with([
    'HTTP 429' => fn () => MockResponse::make(['msg' => 'Too many requests'], 429),
    'code 429 with a successful HTTP status' => fn () => MockResponse::make(['code' => 429, 'msg' => 'Too many requests']),
]);

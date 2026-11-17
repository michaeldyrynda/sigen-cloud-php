<?php

use Dyrynda\SigenCloud\Data\AccessToken;
use Dyrynda\SigenCloud\Enums\Region;
use Dyrynda\SigenCloud\Requests\AccessTokenRequest;
use Dyrynda\SigenCloud\Requests\GetStationRequest;
use Dyrynda\SigenCloud\SigenCloud;
use Saloon\Http\Faking\MockClient;

function sentWithBearer(MockClient $mockClient, string $requestClass, string $token): bool
{
    foreach ($mockClient->getRecordedResponses() as $response) {
        if ($response->getRequest() instanceof $requestClass) {
            return $response->getPendingRequest()->headers()->get('Authorization') === "Bearer {$token}";
        }
    }

    return false;
}

it('exposes the access token from login so it can be cached', function () {
    MockClient::global([
        AccessTokenRequest::class => fixtureResponse('token'),
    ]);

    $before = new DateTimeImmutable('+43199 seconds');
    $token = (new SigenCloud('owner@example.com', 'secret', Region::Australia))->accessToken();
    $after = new DateTimeImmutable('+43199 seconds');

    expect($token)
        ->accessToken->toBe('access-token-from-login')
        ->expiresAt->toBeGreaterThanOrEqual($before)
        ->expiresAt->toBeLessThanOrEqual($after);
});

it('reuses a cached access token that has not expired', function () {
    $mockClient = MockClient::global([
        AccessTokenRequest::class => fixtureResponse('token'),
        GetStationRequest::class => fixtureResponse('station'),
    ]);

    $sigen = new SigenCloud('owner@example.com', 'secret', Region::Australia, new AccessToken(
        accessToken: 'cached-token',
        expiresAt: new DateTimeImmutable('+1 hour'),
    ));

    $sigen->stations()->current();

    $mockClient->assertNotSent(AccessTokenRequest::class);
    expect(sentWithBearer($mockClient, GetStationRequest::class, 'cached-token'))->toBeTrue();
});

it('logs in again when the cached access token has expired or is about to', function (string $expiresAt) {
    $mockClient = MockClient::global([
        AccessTokenRequest::class => fixtureResponse('token'),
        GetStationRequest::class => fixtureResponse('station'),
    ]);

    $sigen = new SigenCloud('owner@example.com', 'secret', Region::Australia, new AccessToken(
        accessToken: 'expiring-token',
        expiresAt: new DateTimeImmutable($expiresAt),
    ));

    $sigen->stations()->current();

    $mockClient->assertSent(AccessTokenRequest::class);
    expect(sentWithBearer($mockClient, GetStationRequest::class, 'access-token-from-login'))->toBeTrue();
})->with([
    'expired a minute ago' => '-1 minute',
    'expires in 30 seconds' => '+30 seconds',
]);

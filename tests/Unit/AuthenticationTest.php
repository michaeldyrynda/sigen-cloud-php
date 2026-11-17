<?php

use Dyrynda\SigenCloud\Data\AccessToken;
use Dyrynda\SigenCloud\Enums\Region;
use Dyrynda\SigenCloud\Requests\AccessTokenRequest;
use Dyrynda\SigenCloud\Requests\GetStationRequest;
use Dyrynda\SigenCloud\Requests\RefreshAccessTokenRequest;
use Dyrynda\SigenCloud\SigenCloud;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

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
        ->refreshToken->toBe('refresh-token-from-login')
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

it('refreshes an expired access token with its refresh token instead of the password', function () {
    $mockClient = MockClient::global([
        RefreshAccessTokenRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => [
            'access_token' => 'access-token-from-refresh',
            'refresh_token' => 'rotated-refresh-token',
            'expires_in' => 43199,
        ]]),
        GetStationRequest::class => fixtureResponse('station'),
    ]);

    $sigen = new SigenCloud('owner@example.com', 'secret', Region::Australia, new AccessToken(
        accessToken: 'expired-token',
        expiresAt: new DateTimeImmutable('-1 minute'),
        refreshToken: 'cached-refresh-token',
    ));

    $sigen->stations()->current();

    $mockClient->assertNotSent(AccessTokenRequest::class);
    $mockClient->assertSent(fn (Request $request, Response $response) => $request instanceof RefreshAccessTokenRequest
        && $response->getPendingRequest()->getUrl() === 'https://api-aus.sigencloud.com/auth/oauth/token'
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Basic '.base64_encode('sigen:sigen')
        && $response->getPendingRequest()->body()?->all() === [
            'grant_type' => 'refresh_token',
            'refresh_token' => 'cached-refresh-token',
        ]);
    expect(sentWithBearer($mockClient, GetStationRequest::class, 'access-token-from-refresh'))->toBeTrue()
        ->and($sigen->accessToken()->refreshToken)->toBe('rotated-refresh-token');
});

it('logs in with the password when Sigen rejects the refresh token', function () {
    $mockClient = MockClient::global([
        RefreshAccessTokenRequest::class => MockResponse::make(['code' => 401, 'msg' => 'Invalid refresh token'], 401),
        AccessTokenRequest::class => fixtureResponse('token'),
        GetStationRequest::class => fixtureResponse('station'),
    ]);

    $sigen = new SigenCloud('owner@example.com', 'secret', Region::Australia, new AccessToken(
        accessToken: 'expired-token',
        expiresAt: new DateTimeImmutable('-1 minute'),
        refreshToken: 'revoked-refresh-token',
    ));

    $sigen->stations()->current();

    $mockClient->assertSent(RefreshAccessTokenRequest::class);
    expect(sentWithBearer($mockClient, GetStationRequest::class, 'access-token-from-login'))->toBeTrue();
});

it('does not retry with a password when the refresh request is rate limited', function () {
    $mockClient = MockClient::global([
        RefreshAccessTokenRequest::class => MockResponse::make(['msg' => 'Too many requests'], 429),
        AccessTokenRequest::class => fixtureResponse('token'),
    ]);

    $sigen = new SigenCloud('owner@example.com', 'secret', Region::Australia, new AccessToken(
        accessToken: 'expired-token',
        expiresAt: new DateTimeImmutable('-1 minute'),
        refreshToken: 'refresh-token',
    ));

    expect(fn () => $sigen->accessToken())
        ->toThrow(Dyrynda\SigenCloud\Exceptions\SigenCloudRateLimitException::class);

    $mockClient->assertNotSent(AccessTokenRequest::class);
});

it('logs in again when the refresh grant is rejected as invalid', function () {
    $mockClient = MockClient::global([
        RefreshAccessTokenRequest::class => MockResponse::make(['error' => 'invalid_grant'], 400),
        AccessTokenRequest::class => fixtureResponse('token'),
    ]);

    $sigen = new SigenCloud('owner@example.com', 'secret', Region::Australia, new AccessToken(
        accessToken: 'expired-token',
        expiresAt: new DateTimeImmutable('-1 minute'),
        refreshToken: 'revoked-token',
    ));

    expect($sigen->accessToken()->accessToken)->toBe('access-token-from-login');
    $mockClient->assertSent(AccessTokenRequest::class);
});

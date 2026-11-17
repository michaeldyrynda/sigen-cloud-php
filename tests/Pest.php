<?php

use Dyrynda\SigenCloud\Data\AccessToken;
use Dyrynda\SigenCloud\Enums\Region;
use Dyrynda\SigenCloud\SigenCloud;
use Saloon\Config;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

Config::preventStrayRequests();

pest()->afterEach(function () {
    MockClient::destroyGlobal();
})->in(__DIR__);

function fixtureResponse(string $name): MockResponse
{
    return MockResponse::make((string) file_get_contents(fixture("{$name}.json")));
}

function authenticatedSigen(): SigenCloud
{
    return new SigenCloud('owner@example.com', 'secret', Region::Australia, new AccessToken(
        accessToken: 'cached-token',
        expiresAt: new DateTimeImmutable('+1 hour'),
    ));
}

/**
 * The request a test sent for the given request class, to assert its method, URL, query and body.
 *
 * @param  class-string<Saloon\Http\Request>  $requestClass
 */
function sentRequest(MockClient $mockClient, string $requestClass): PendingRequest
{
    foreach ($mockClient->getRecordedResponses() as $response) {
        if ($response->getRequest() instanceof $requestClass) {
            return $response->getPendingRequest();
        }
    }

    throw new RuntimeException("No [{$requestClass}] request was sent.");
}

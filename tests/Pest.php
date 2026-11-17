<?php

use Saloon\Config;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

Config::preventStrayRequests();

pest()->afterEach(function () {
    MockClient::destroyGlobal();
})->in(__DIR__);

function fixtureResponse(string $name): MockResponse
{
    return MockResponse::make((string) file_get_contents(fixture("{$name}.json")));
}

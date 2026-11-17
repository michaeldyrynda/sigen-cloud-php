<?php

use Dyrynda\SigenCloud\Requests\GetElectricityPriceCostRequest;
use Dyrynda\SigenCloud\Requests\GetElectricityTaxAndFeeRequest;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('reads electricity tax and fee settings', function () {
    $data = ['buy' => ['fixedValue' => 12.5], 'sell' => ['fixedValue' => 3]];
    $mockClient = MockClient::global([
        GetElectricityTaxAndFeeRequest::class => MockResponse::make(['code' => 0, 'data' => $data]),
    ]);

    expect(authenticatedSigen()->tariffs()->taxAndFee(123))->toBe($data);
    expect(sentRequest($mockClient, GetElectricityTaxAndFeeRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/electricity-price/api/tax-and-fee/123');
});

it('reads the derived electricity price cost', function () {
    $mockClient = MockClient::global([
        GetElectricityPriceCostRequest::class => MockResponse::make(['code' => 0, 'data' => ['coefficient' => 1.25]]),
    ]);

    expect(authenticatedSigen()->tariffs()->priceCost(123))->toBe(['coefficient' => 1.25]);
    expect(sentRequest($mockClient, GetElectricityPriceCostRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/prediction/aipv/elecPrice/get/priceCost')
        ->query()->all()->toBe(['stationId' => '123']);
});

it('replaces electricity tax and fee settings for a direction', function () {
    $mockClient = MockClient::global([
        Dyrynda\SigenCloud\Requests\UpdateElectricityTaxAndFeeRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
    ]);

    authenticatedSigen()->tariffs()->setTaxAndFee(123, 2, ['fixedValue' => 4.5]);

    expect(sentRequest($mockClient, Dyrynda\SigenCloud\Requests\UpdateElectricityTaxAndFeeRequest::class))
        ->getMethod()->toBe(Method::POST)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/electricity-price/api/tax-and-fee')
        ->body()->all()->toBe(['stationId' => 123, 'directionType' => 2, 'taxAndFee' => ['fixedValue' => 4.5]]);
});

it('rejects invalid tax and fee directions before sending', function () {
    $mockClient = MockClient::global([]);

    expect(fn () => authenticatedSigen()->tariffs()->setTaxAndFee(123, 3, []))
        ->toThrow(InvalidArgumentException::class);

    $mockClient->assertNothingSent();
});

it('rejects malformed tariff response data', function (string $requestClass, Closure $read) {
    MockClient::global([
        $requestClass => MockResponse::make(['code' => 0, 'data' => null]),
    ]);

    expect(fn () => $read(authenticatedSigen()->tariffs()))->toThrow(UnexpectedValueException::class);
})->with([
    'price cost' => [GetElectricityPriceCostRequest::class, fn ($tariffs) => $tariffs->priceCost(123)],
    'tax and fee' => [GetElectricityTaxAndFeeRequest::class, fn ($tariffs) => $tariffs->taxAndFee(123)],
]);

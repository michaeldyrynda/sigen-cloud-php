<?php

use Dyrynda\SigenCloud\Requests\GetGridConnectionLimitRequest;
use Dyrynda\SigenCloud\Requests\GetGridExportLimitRequest;
use Dyrynda\SigenCloud\Requests\GetGridImportLimitRequest;
use Dyrynda\SigenCloud\Requests\UpdateGridConnectionLimitRequest;
use Dyrynda\SigenCloud\Requests\UpdateGridExportLimitRequest;
use Dyrynda\SigenCloud\Requests\UpdateGridImportLimitRequest;
use Dyrynda\SigenCloud\Resources\GridResource;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('reads the grid limit settings for each direction', function (string $requestClass, Closure $read, string $url, array $limit) {
    $mockClient = MockClient::global([
        $requestClass => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => $limit]),
    ]);

    expect($read(authenticatedSigen()->grid()))->toBe($limit);

    expect(sentRequest($mockClient, $requestClass))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe($url);
})->with([
    'export, limited by the installer' => [
        GetGridExportLimitRequest::class,
        fn (GridResource $grid) => $grid->exportLimit(120250610002197),
        'https://api-aus.sigencloud.com/device/energy-profile/grid/limitation/export/120250610002197',
        ['enable' => true, 'maxLimitation' => '30.000', 'maxLimitationOwner' => '', 'maxLimitationInstaller' => '30.000', 'isUltra' => false],
    ],
    'import, disabled with blank limits as an Australian station reports it' => [
        GetGridImportLimitRequest::class,
        fn (GridResource $grid) => $grid->importLimit(120250610002197),
        'https://api-aus.sigencloud.com/device/energy-profile/grid/limitation/import/120250610002197',
        ['enable' => false, 'maxLimitation' => '', 'maxLimitationOwner' => '', 'maxLimitationInstaller' => '', 'isUltra' => false],
    ],
]);

it('sets the owner grid limit in kilowatts for each direction', function (string $requestClass, Closure $set, string $url) {
    $mockClient = MockClient::global([
        $requestClass => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    $set(authenticatedSigen()->grid());

    expect(sentRequest($mockClient, $requestClass))
        ->getMethod()->toBe(Method::PUT)
        ->getUrl()->toBe($url)
        ->body()->all()->toBe([
            'stationId' => 120250610002197,
            'enable' => true,
            'maxLimitationOwner' => '7.250',
            'maxLimitationInstaller' => null,
        ]);
})->with([
    'export' => [
        UpdateGridExportLimitRequest::class,
        fn (GridResource $grid) => $grid->setExportLimit(120250610002197, 7.25),
        'https://api-aus.sigencloud.com/device/energy-profile/grid/limitation/export',
    ],
    'import' => [
        UpdateGridImportLimitRequest::class,
        fn (GridResource $grid) => $grid->setImportLimit(120250610002197, 7.25),
        'https://api-aus.sigencloud.com/device/energy-profile/grid/limitation/import',
    ],
]);

it('disables the grid limit for each direction', function (string $requestClass, Closure $disable) {
    $mockClient = MockClient::global([
        $requestClass => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    $disable(authenticatedSigen()->grid());

    expect(sentRequest($mockClient, $requestClass)->body()?->all())->toBe([
        'stationId' => 120250610002197,
        'enable' => false,
        'maxLimitationOwner' => '5.000',
        'maxLimitationInstaller' => null,
    ]);
})->with([
    'export' => [
        UpdateGridExportLimitRequest::class,
        fn (GridResource $grid) => $grid->setExportLimit(120250610002197, 5, enabled: false),
    ],
    'import' => [
        UpdateGridImportLimitRequest::class,
        fn (GridResource $grid) => $grid->setImportLimit(120250610002197, 5, enabled: false),
    ],
]);

it('reads the max grid connection current settings', function (array $limit) {
    $mockClient = MockClient::global([
        GetGridConnectionLimitRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => $limit]),
    ]);

    expect(authenticatedSigen()->grid()->connectionLimit(120250610002197))->toBe($limit);

    expect(sentRequest($mockClient, GetGridConnectionLimitRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/energy-profile/parallel/off/grid/120250610002197');
})->with([
    'lowered by the owner' => [['enable' => true, 'currentLimitation' => '13.7', 'ownerSetLimitation' => '13.7', 'installerSetLimitation' => '13.8']],
    'disabled with blank limits as an Australian station reports it' => [['enable' => false, 'currentLimitation' => '', 'ownerSetLimitation' => '', 'installerSetLimitation' => '']],
]);

it('sets the owner max grid connection current in amps to one decimal place', function () {
    $mockClient = MockClient::global([
        UpdateGridConnectionLimitRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    authenticatedSigen()->grid()->setConnectionLimit(120250610002197, 16);

    expect(sentRequest($mockClient, UpdateGridConnectionLimitRequest::class))
        ->getMethod()->toBe(Method::PUT)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/energy-profile/parallel/off/grid')
        ->body()->all()->toBe([
            'stationId' => 120250610002197,
            'enable' => true,
            'ownerSetLimitation' => '16.0',
            'installerSetLimitation' => null,
        ]);
});

it('disables the max grid connection current limit', function () {
    $mockClient = MockClient::global([
        UpdateGridConnectionLimitRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    authenticatedSigen()->grid()->setConnectionLimit(120250610002197, 13.7, enabled: false);

    expect(sentRequest($mockClient, UpdateGridConnectionLimitRequest::class)->body()?->all())->toBe([
        'stationId' => 120250610002197,
        'enable' => false,
        'ownerSetLimitation' => '13.7',
        'installerSetLimitation' => null,
    ]);
});

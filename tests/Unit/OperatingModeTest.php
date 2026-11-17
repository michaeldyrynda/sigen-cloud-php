<?php

use Dyrynda\SigenCloud\Requests\GetOperatingModeRequest;
use Dyrynda\SigenCloud\Requests\GetOperatingModesRequest;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('reads the operating modes available to the station', function () {
    $modes = [
        'defaultWorkingModes' => [
            ['label' => 'Maximum Self-Powered', 'sortOrder' => 0, 'remarks' => '', 'value' => '0'],
            ['label' => 'VPP Mode', 'sortOrder' => 0, 'remarks' => 'Amber', 'value' => '6'],
        ],
        'energyProfileItems' => [],
    ];

    $mockClient = MockClient::global([
        GetOperatingModesRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => $modes]),
    ]);

    expect(authenticatedSigen()->operatingModes()->available(120250610002197))->toBe($modes);

    expect(sentRequest($mockClient, GetOperatingModesRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/energy-profile/mode/all/120250610002197');
});

it('labels the current operating mode', function (int $currentMode, int $currentProfileId, string $label) {
    $mockClient = MockClient::global([
        GetOperatingModesRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => [
            'defaultWorkingModes' => [
                ['label' => 'Maximum Self-Powered', 'sortOrder' => 0, 'remarks' => '', 'value' => '0'],
                ['label' => 'Sigen AI Mode', 'sortOrder' => 0, 'remarks' => '', 'value' => '1'],
                ['label' => 'VPP Mode', 'sortOrder' => 0, 'remarks' => 'Amber', 'value' => '6'],
                ['label' => 'Custom Operation Mode', 'sortOrder' => 0, 'remarks' => '', 'value' => '9'],
            ],
            'energyProfileItems' => [
                ['profileId' => 7, 'name' => 'Summer'],
                ['profileId' => 42, 'name' => 'Winter peak'],
            ],
        ]]),
        GetOperatingModeRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => [
            'currentMode' => $currentMode,
            'currentProfileId' => $currentProfileId,
            'remarks' => '',
            'subWorkingMode' => null,
            'activeSelfConsumptionPlus' => null,
            'showSelfConsumptionPlus' => null,
            'followPpcMode' => false,
        ]]),
    ]);

    expect(authenticatedSigen()->operatingModes()->current(120250610002197))->toBe($label);

    expect(sentRequest($mockClient, GetOperatingModeRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/energy-profile/mode/current/120250610002197');
})->with([
    'a built-in mode, as an Amber VPP station reports it' => [6, -1, 'VPP Mode'],
    'a custom energy profile, by its name' => [9, 42, 'Winter peak'],
]);

it('sets an operating mode and profile', function () {
    $mockClient = MockClient::global([
        Dyrynda\SigenCloud\Requests\UpdateOperatingModeRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    authenticatedSigen()->operatingModes()->set(123, 9, 42);

    expect(sentRequest($mockClient, Dyrynda\SigenCloud\Requests\UpdateOperatingModeRequest::class))
        ->getMethod()->toBe(Method::PUT)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/energy-profile/mode')
        ->body()->all()->toBe(['stationId' => 123, 'operationMode' => 9, 'profileId' => 42]);
});

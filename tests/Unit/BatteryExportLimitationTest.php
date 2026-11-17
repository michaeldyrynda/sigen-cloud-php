<?php

use Dyrynda\SigenCloud\Requests\GetBatteryExportLimitationRequest;
use Dyrynda\SigenCloud\Requests\UpdateBatteryExportLimitationRequest;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('reads whether the battery may export to the grid', function () {
    $mockClient = MockClient::global([
        GetBatteryExportLimitationRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => [
            'currentEnable' => false,
            'ownerSetEnable' => null,
            'installerSetEnable' => null,
            'nearModify' => null,
        ]]),
    ]);

    expect(authenticatedSigen()->battery()->exportLimitation(120250610002197))->toBe([
        'currentEnable' => false,
        'ownerSetEnable' => null,
        'installerSetEnable' => null,
        'nearModify' => null,
    ]);
    expect(sentRequest($mockClient, GetBatteryExportLimitationRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/energy-profile/battery/export/limitation/120250610002197');
});

it('sets whether the battery may export to the grid as the owner', function (bool $enabled) {
    $mockClient = MockClient::global([
        UpdateBatteryExportLimitationRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    authenticatedSigen()->battery()->setExportLimitation(120250610002197, $enabled);

    expect(sentRequest($mockClient, UpdateBatteryExportLimitationRequest::class))
        ->getMethod()->toBe(Method::PUT)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/energy-profile/battery/export/limitation')
        ->body()->all()->toBe([
            'stationId' => 120250610002197,
            'installerSetEnable' => null,
            'ownerSetEnable' => $enabled,
        ]);
})->with([
    'enabled' => true,
    'disabled' => false,
]);

<?php

use Dyrynda\SigenCloud\Requests\GetV2xDischargeInfoRequest;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('reads V2X discharge information for the selected charger', function () {
    $data = ['duration' => 37, 'power' => 4.2];
    $mockClient = MockClient::global([
        GetV2xDischargeInfoRequest::class => MockResponse::make(['code' => 0, 'data' => $data]),
    ]);

    expect(authenticatedSigen()->v2x()->dischargeInfo(12345, 'DC123'))->toBe($data)
        ->and(sentRequest($mockClient, GetV2xDischargeInfoRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/station-v2x/discharge/info')
        ->query()->all()->toBe(['stationId' => 12345, 'dcSnCode' => 'DC123']);
});

it('reads V2X discharge settings for the charger', function () {
    $mock = MockClient::global(['*' => MockResponse::make(['code' => 0, 'data' => ['dischargeEnable' => 1]])]);
    expect(authenticatedSigen()->v2x()->dischargeSettings(12345, 'DC123'))->toBe(['dischargeEnable' => 1]);
    expect(sentRequest($mock, Dyrynda\SigenCloud\Requests\GetV2xDischargeSettingsRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/station-v2x/select')
        ->query()->all()->toBe(['stationId' => 12345, 'snCode' => 'DC123']);
});

it('sets V2X discharge enablement', function (bool $enabled, int $value) {
    $mock = MockClient::global(['*' => MockResponse::make(['code' => 0, 'data' => true])]);
    authenticatedSigen()->v2x()->setDischargeEnabled(12345, 'DC123', $enabled);
    expect(sentRequest($mock, Dyrynda\SigenCloud\Requests\UpdateV2xDischargeEnabledRequest::class))
        ->getMethod()->toBe(Method::POST)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/station-v2x/open-close')
        ->body()->all()->toBe(['stationId' => 12345, 'snCode' => 'DC123', 'dischargeEnable' => $value]);
})->with([[true, 1], [false, 0]]);

it('starts V2X discharge with duration and optional power cap', function () {
    $mock = MockClient::global(['*' => MockResponse::make(['code' => 0, 'data' => true])]);
    authenticatedSigen()->v2x()->startDischarge(12345, 'DC123', 75, 3.5);
    expect(sentRequest($mock, Dyrynda\SigenCloud\Requests\StartV2xDischargeRequest::class))
        ->getMethod()->toBe(Method::POST)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/station-v2x/start/discharge')
        ->body()->all()->toBe(['stationId' => 12345, 'snCode' => 'DC123', 'duration' => 75, 'powerCap' => 3.5]);
});

it('stops V2X discharge for the charger', function () {
    $mock = MockClient::global(['*' => MockResponse::make(['code' => 0, 'data' => true])]);
    authenticatedSigen()->v2x()->stopDischarge(12345, 'DC123');
    expect(sentRequest($mock, Dyrynda\SigenCloud\Requests\StopV2xDischargeRequest::class))
        ->getMethod()->toBe(Method::POST)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/station-v2x/stop/discharge')
        ->body()->all()->toBe(['stationId' => 12345, 'snCode' => 'DC123']);
});

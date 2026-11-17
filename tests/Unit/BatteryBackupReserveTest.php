<?php

use Dyrynda\SigenCloud\Requests\GetBatteryBackupReserveRequest;
use Dyrynda\SigenCloud\Requests\UpdateBatteryBackupReserveRequest;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('reads the backup reserve settings', function () {
    $mockClient = MockClient::global([
        GetBatteryBackupReserveRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => [
            'stationId' => 120250610002197,
            'ja12Soc' => '0.0',
            'backupReserve' => 4,
            'operationMode' => 6,
        ]]),
    ]);

    expect(authenticatedSigen()->battery()->backupReserve(120250610002197))->toBe([
        'stationId' => 120250610002197,
        'ja12Soc' => '0.0',
        'backupReserve' => 4,
        'operationMode' => 6,
    ]);
    expect(sentRequest($mockClient, GetBatteryBackupReserveRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/setting/backup/reserve/120250610002197');
});

it('sets the backup reserve percentage', function (int $percent) {
    $mockClient = MockClient::global([
        UpdateBatteryBackupReserveRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    authenticatedSigen()->battery()->setBackupReserve(120250610002197, $percent);

    expect(sentRequest($mockClient, UpdateBatteryBackupReserveRequest::class))
        ->getMethod()->toBe(Method::PUT)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/setting/backup/reserve')
        ->body()->all()->toBe([
            'backupReserve' => $percent,
            'stationId' => 120250610002197,
        ]);
})->with([
    'no reserve' => 0,
    'a typical reserve' => 15,
    'a full reserve' => 100,
]);

it('rejects a backup reserve outside 0 to 100 percent without sending it', function (int $percent) {
    $mockClient = MockClient::global([
        UpdateBatteryBackupReserveRequest::class => MockResponse::make(['code' => 0, 'msg' => 'success', 'data' => true]),
    ]);

    expect(fn () => authenticatedSigen()->battery()->setBackupReserve(120250610002197, $percent))
        ->toThrow(InvalidArgumentException::class);

    $mockClient->assertNothingSent();
})->with([
    'below 0 percent' => -1,
    'above 100 percent' => 101,
]);

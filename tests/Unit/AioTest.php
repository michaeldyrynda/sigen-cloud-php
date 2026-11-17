<?php

use Dyrynda\SigenCloud\Exceptions\SigenCloudException;
use Dyrynda\SigenCloud\Requests\GetAioOnOffStateRequest;
use Dyrynda\SigenCloud\Requests\GetAioPowerStatusRequest;
use Dyrynda\SigenCloud\Requests\GetStationAioSerialsRequest;
use Dyrynda\SigenCloud\Requests\GetStationRequest;
use Dyrynda\SigenCloud\Requests\GetStationTopologyRequest;
use Dyrynda\SigenCloud\Requests\UpdateAioPowerRequest;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('reads the AIO power status for the specified station and serial', function () {
    $mockClient = MockClient::global([
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => ['powerOn' => 1]]),
    ]);

    expect(authenticatedSigen()->aio()->powerOn(123, 'AIO-42'))->toBeTrue();
    expect(sentRequest($mockClient, GetAioPowerStatusRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/sigenMate/getPowerOnOffStatus')
        ->query()->all()->toBe(['stationId' => 123, 'deviceSnCode' => 'AIO-42']);
});

it('reads the raw on-off state with the AIO serial', function () {
    $mock = MockClient::global([
        GetAioOnOffStateRequest::class => MockResponse::make(['code' => 0, 'data' => false]),
    ]);

    expect(authenticatedSigen()->aio()->onOffState(123, 'AIO-42'))->toBeFalse();
    expect(sentRequest($mock, GetAioOnOffStateRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/aio/on-off-state')
        ->query()->all()->toBe(['stationId' => 123, 'snCode' => 'AIO-42']);
});

it('toggles power with the exact device control payload', function () {
    $mock = MockClient::global([
        UpdateAioPowerRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
    ]);

    expect(authenticatedSigen()->aio()->togglePower(123, 'AIO-42'))->toBe(['code' => 0, 'data' => true]);
    expect(sentRequest($mock, UpdateAioPowerRequest::class))
        ->getMethod()->toBe(Method::POST)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/aio/on-off')
        ->body()->all()->toBe(['snCode' => 'AIO-42', 'stationId' => 123]);
});

it('sets power off using station home status and confirms the transition', function () {
    $mock = MockClient::global([
        aioHome(1),
        MockResponse::make(['code' => 0, 'data' => true]),
        aioHome(5),
    ]);

    expect(authenticatedSigen()->aio()->setPower(123, false, 'AIO-42', timeoutSeconds: 1, pollSeconds: 0))->toBeTrue();
    expect(sentRequest($mock, UpdateAioPowerRequest::class)->body()->all())->toBe(['snCode' => 'AIO-42', 'stationId' => 123]);
});

it('sets power on explicitly rather than toggling', function () {
    $mock = MockClient::global([
        aioHome(5),
        MockResponse::make(['code' => 0, 'data' => true]),
        aioHome(1),
    ]);

    expect(authenticatedSigen()->aio()->setPower(123, true, 'AIO-42', timeoutSeconds: 1, pollSeconds: 0))->toBeTrue();
    expect(sentRequest($mock, UpdateAioPowerRequest::class)->body()->all())->toBe(['snCode' => 'AIO-42', 'stationId' => 123, 'powerOn' => true]);
});

it('attempts explicit recovery when the off request fails', function () {
    $requests = [];
    MockClient::global([
        GetStationRequest::class => aioHome(1),
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => function ($request) use (&$requests) {
            $requests[] = $request->body()->all();

            return count($requests) === 1
                ? MockResponse::make(['code' => 500, 'msg' => 'off failed'], 500)
                : MockResponse::make(['code' => 0, 'data' => true]);
        },
    ]);

    expect(fn () => authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false))
        ->toThrow(SigenCloudException::class);
    expect($requests)->toBe([
        ['snCode' => 'AIO-42', 'stationId' => 123],
        ['snCode' => 'AIO-42', 'stationId' => 123, 'powerOn' => true],
    ]);
});

it('recovers when a status read after the off command fails', function () {
    $homeReads = 0;
    $commands = [];
    MockClient::global([
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        GetStationRequest::class => function () use (&$homeReads) {
            return ++$homeReads === 2 ? MockResponse::make(['code' => 500, 'msg' => 'read failed'], 500) : aioHome(1);
        },
        UpdateAioPowerRequest::class => function ($request) use (&$commands) {
            $commands[] = $request->body()->all();

            return MockResponse::make(['code' => 0, 'data' => true]);
        },
    ]);

    expect(fn () => authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 1, pollSeconds: 0, waitEvdcOffline: false))
        ->toThrow(SigenCloudException::class);
    expect($commands)->toBe([
        ['snCode' => 'AIO-42', 'stationId' => 123],
        ['snCode' => 'AIO-42', 'stationId' => 123, 'powerOn' => true],
    ]);
});

it('confirms a successful restart without waiting', function () {
    $homeReads = 0;
    $commands = [];
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(match (++$homeReads) {
                1 => 1, 2 => 5, default => 1
            });
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => function ($request) use (&$commands) {
            $commands[] = $request->body()->all();

            return MockResponse::make(['code' => 0, 'data' => true]);
        },
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 1, pollSeconds: 0, waitEvdcOffline: false);

    expect($result)->toMatchArray(['sn_code' => 'AIO-42', 'powered_off' => true, 'powered_on' => true, 'home_status_after' => 1, 'power_on_after' => true]);
    expect($commands)->toBe([
        ['snCode' => 'AIO-42', 'stationId' => 123],
        ['snCode' => 'AIO-42', 'stationId' => 123, 'powerOn' => true],
    ]);
});

it('stops the topology wait on rate limiting and restores power', function () {
    $homeReads = 0;
    $topologyReads = 0;
    $commands = [];
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(match (++$homeReads) {
                1 => 1, 2 => 5, default => 1
            });
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        GetStationTopologyRequest::class => function () use (&$topologyReads) {
            $topologyReads++;

            return MockResponse::make(['code' => 429, 'msg' => 'rate limited'], 429);
        },
        UpdateAioPowerRequest::class => function ($request) use (&$commands) {
            $commands[] = $request->body()->all();

            return MockResponse::make(['code' => 0, 'data' => true]);
        },
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 1, pollSeconds: 0, evdcOfflineWaitSeconds: 1);

    expect($result)->toMatchArray(['evdc_wait_aborted' => 'topology_rate_limited', 'powered_on' => true]);
    expect($topologyReads)->toBe(3);
    expect($commands[1])->toBe(['snCode' => 'AIO-42', 'stationId' => 123, 'powerOn' => true]);
});

it('refuses to restart a station that is not known to be running', function () {
    $mock = MockClient::global([
        GetStationRequest::class => aioHome(5),
    ]);

    expect(fn () => authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0))->toThrow(RuntimeException::class);
    $mock->assertNotSent(UpdateAioPowerRequest::class);
});

it('reports the DC charger becoming offline before restoring power', function () {
    $homeReads = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(match (++$homeReads) {
                1 => 1, 2 => 5, default => 1
            });
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        GetStationTopologyRequest::class => MockResponse::make(['code' => 0, 'data' => ['nodeList' => [
            ['deviceType' => 5, 'snCode' => 'EVDC', 'deviceStatus' => 5],
        ]]]),
        UpdateAioPowerRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 1, pollSeconds: 0, evdcOfflineWaitSeconds: 1);

    expect($result['evdc_offline_seen'])->toBeTrue()
        ->and($result['evdc_topology_after_off'])->toMatchArray(['sn_code' => 'EVDC', 'offline' => true]);
});

it('does not toggle off when the station home status is unknown', function () {
    $mock = MockClient::global([GetStationRequest::class => aioHome(99)]);

    expect(fn () => authenticatedSigen()->aio()->setPower(123, false, 'AIO-42', 0, 0))->toThrow(RuntimeException::class);
    $mock->assertNotSent(UpdateAioPowerRequest::class);
});

it('selects the first AIO serial when none is supplied', function () {
    $mock = MockClient::global([
        GetStationAioSerialsRequest::class => MockResponse::make(['code' => 0, 'data' => ['snList' => ['FIRST', 'SECOND']]]),
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => false]),
    ]);

    expect(authenticatedSigen()->aio()->powerOn(123))->toBeFalse();
    expect(sentRequest($mock, GetAioPowerStatusRequest::class)->query()->all())->toBe(['stationId' => 123, 'deviceSnCode' => 'FIRST']);
});

it('uses Python truthiness for a string power status', function () {
    MockClient::global([
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => ['status' => '0']]),
    ]);

    expect(authenticatedSigen()->aio()->powerOn(123, 'AIO-42'))->toBeTrue();
});

it('selects the station serial for a restart when omitted', function () {
    $homeReads = 0;
    $mock = MockClient::global([
        GetStationAioSerialsRequest::class => MockResponse::make(['code' => 0, 'data' => ['FIRST']]),
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 2 ? 5 : 1);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
    ]);

    $result = authenticatedSigen()->aio()->restart(123, timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false);

    expect($result['sn_code'])->toBe('FIRST');
    expect(sentRequest($mock, UpdateAioPowerRequest::class)->body()->all())->toBe(['snCode' => 'FIRST', 'stationId' => 123]);
});

it('retries an unsuccessful recovery command and verifies power is restored', function () {
    $homeReads = 0;
    $commands = [];
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 2 ? 5 : 1);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => function ($request) use (&$commands) {
            $commands[] = $request->body()->all();

            return count($commands) === 2 ? MockResponse::make(['code' => 500, 'msg' => 'restore failed'], 500) : MockResponse::make(['code' => 0, 'data' => true]);
        },
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false);

    expect($result['powered_on'])->toBeTrue();
    expect($commands)->toBe([
        ['snCode' => 'AIO-42', 'stationId' => 123],
        ['snCode' => 'AIO-42', 'stationId' => 123, 'powerOn' => true],
        ['snCode' => 'AIO-42', 'stationId' => 123, 'powerOn' => true],
    ]);
});

it('retries power-on and reports failure when the station remains off at timeout', function () {
    $homeReads = 0;
    $commands = [];
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 1 ? 1 : 5);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => false]),
        UpdateAioPowerRequest::class => function ($request) use (&$commands) {
            $commands[] = $request->body()->all();

            return MockResponse::make(['code' => 0, 'data' => true]);
        },
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false);

    expect($result)->toMatchArray(['powered_off' => true, 'powered_on' => false, 'power_on_forced' => true]);
    expect($commands)->toHaveCount(3)->and($commands[2])->toBe(['snCode' => 'AIO-42', 'stationId' => 123, 'powerOn' => true]);
});

it('retries power-on when the post-recovery status read fails', function () {
    $homeReads = 0;
    $commands = [];
    MockClient::global([
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        GetStationRequest::class => function () use (&$homeReads) {
            return match (++$homeReads) {
                1 => aioHome(1),
                2 => aioHome(5),
                default => MockResponse::make(['code' => 500, 'msg' => 'status failed'], 500),
            };
        },
        UpdateAioPowerRequest::class => function ($request) use (&$commands) {
            $commands[] = $request->body()->all();

            return MockResponse::make(['code' => 0, 'data' => true]);
        },
    ]);

    expect(fn () => authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false))->toThrow(SigenCloudException::class);
    expect($commands)->toHaveCount(3)->and($commands[2])->toBe(['snCode' => 'AIO-42', 'stationId' => 123, 'powerOn' => true]);
});

it('captures charger topology before and after a restart', function () {
    $homeReads = 0;
    $topologyReads = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 2 ? 5 : 1);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        GetStationTopologyRequest::class => function () use (&$topologyReads) {
            $status = ++$topologyReads === 2 ? 5 : 1;

            return MockResponse::make(['code' => 0, 'data' => ['nodeList' => [['deviceType' => 5, 'snCode' => 'EVDC', 'deviceStatus' => $status]]]]);
        },
        UpdateAioPowerRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 1, pollSeconds: 0, evdcOfflineWaitSeconds: 1);

    expect($result['evdc_topology_before']['offline'])->toBeFalse()
        ->and($result['evdc_topology_after_off']['offline'])->toBeTrue()
        ->and($result['evdc_topology_after']['offline'])->toBeFalse();
});

it('reports the pre-cycle power and station status', function () {
    $homeReads = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 2 ? 5 : 1);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false);

    expect($result)->toMatchArray(['power_on_before' => true, 'home_status_before' => 1, 'home_status_desc_before' => 'status', 'home_status_desc_after' => 'status']);
});

it('reports restart timing and effective wait options', function () {
    $homeReads = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 2 ? 5 : 1);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false, evdcOfflineWaitSeconds: 3, offDwellSeconds: 0);

    expect($result)->toMatchArray(['off_dwell_s' => 0.0, 'poll_s' => 0.0, 'wait_evdc_offline' => false, 'evdc_offline_wait_s' => 3.0, 't_evdc_offline_after_plant_off_s' => null]);
    expect($result['t_offline_s'])->toBeFloat()->toBeGreaterThanOrEqual(0.0)
        ->and($result['t_online_after_on_s'])->toBeFloat()->toBeGreaterThanOrEqual(0.0)
        ->and($result['full_cycle_s'])->toBeFloat()->toBeGreaterThanOrEqual(0.0)
        ->and($result['elapsed_s'])->toBeFloat()->toBeGreaterThanOrEqual(0.0);
});

it('honours legacy EVDC wait aliases', function () {
    $homeReads = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 2 ? 5 : 1);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: true, evdcOfflineWaitSeconds: 12, waitEvdcStatusChange: false, evdcStatusWaitSeconds: 0);

    expect($result)->toMatchArray(['wait_evdc_offline' => false, 'evdc_offline_wait_s' => 0.0]);
});

it('reports recovery failure after both power-on commands fail', function () {
    $homeReads = 0;
    $commands = [];
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 1 ? 1 : 5);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => function ($request) use (&$commands) {
            $commands[] = $request->body()->all();

            return count($commands) === 1 ? MockResponse::make(['code' => 0, 'data' => true]) : MockResponse::make(['code' => 500, 'msg' => 'device unavailable'], 500);
        },
    ]);

    expect(fn () => authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false))
        ->toThrow(RuntimeException::class, 'recovery failed');
    expect($commands)->toHaveCount(3)->and($commands[2]['powerOn'])->toBeTrue();
});

it('selects a station serial for other AIO operations', function (string $operation) {
    $mock = MockClient::global([
        GetStationAioSerialsRequest::class => MockResponse::make(['code' => 0, 'data' => ['FIRST']]),
        GetStationRequest::class => aioHome(5),
        GetAioOnOffStateRequest::class => MockResponse::make(['code' => 0, 'data' => false]),
        UpdateAioPowerRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
    ]);
    $aio = authenticatedSigen()->aio();

    match ($operation) {
        'read' => $aio->onOffState(123),
        'toggle' => $aio->togglePower(123),
        'set' => $aio->setPower(123, false),
    };

    $mock->assertSent(GetStationAioSerialsRequest::class);
})->with(['read', 'toggle', 'set']);

it('continues to power on when the EVDC topology read fails', function () {
    $homeReads = 0;
    $topologyReads = 0;
    $commands = [];
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 2 ? 5 : 1);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        GetStationTopologyRequest::class => function () use (&$topologyReads) {
            $topologyReads++;

            return MockResponse::make(['code' => 500, 'msg' => 'unavailable'], 500);
        },
        UpdateAioPowerRequest::class => function ($request) use (&$commands) {
            $commands[] = $request->body()->all();

            return MockResponse::make(['code' => 0, 'data' => true]);
        },
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, evdcOfflineWaitSeconds: 0.000001);

    expect($result['powered_on'])->toBeTrue();
    expect($commands[1]['powerOn'])->toBeTrue();
});

it('verifies power after recovery even if the off request fails', function () {
    $homeReads = 0;
    $powerReads = 0;
    $commands = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            $homeReads++;

            return aioHome(1);
        },
        GetAioPowerStatusRequest::class => function () use (&$powerReads) {
            $powerReads++;

            return MockResponse::make(['code' => 0, 'data' => true]);
        },
        UpdateAioPowerRequest::class => function () use (&$commands) {
            return ++$commands === 1 ? MockResponse::make(['code' => 500, 'msg' => 'off failed'], 500) : MockResponse::make(['code' => 0, 'data' => true]);
        },
    ]);

    expect(fn () => authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false))->toThrow(SigenCloudException::class, 'off failed');
    expect($homeReads)->toBe(2)->and($powerReads)->toBe(2);
});

it('preserves the off failure when recovery commands also fail', function () {
    $commands = 0;
    MockClient::global([
        GetStationRequest::class => aioHome(1),
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => function () use (&$commands) {
            return ++$commands === 1 ? MockResponse::make(['code' => 500, 'msg' => 'off failed'], 500) : MockResponse::make(['code' => 500, 'msg' => 'recovery unavailable'], 500);
        },
    ]);

    try {
        authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false);
        test()->fail('Expected restart to fail.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('off failed')->toContain('recovery unavailable');
        expect($exception->getPrevious()?->getMessage())->toBe('off failed');
    }
});

it('checks the plant state even if both recovery commands fail', function () {
    $homeReads = 0;
    $commands = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            $homeReads++;

            return aioHome(1);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => function () use (&$commands) {
            return ++$commands === 1 ? MockResponse::make(['code' => 0, 'data' => true]) : MockResponse::make(['code' => 500, 'msg' => 'restore failed'], 500);
        },
    ]);

    expect(fn () => authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false))->toThrow(RuntimeException::class, 'recovery failed');
    expect($homeReads)->toBeGreaterThanOrEqual(3);
});

it('continues verification after a transient post-recovery read failure', function () {
    $homeReads = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return match (++$homeReads) {
                1 => aioHome(1),
                2 => aioHome(5),
                3 => MockResponse::make(['code' => 500, 'msg' => 'status failed'], 500),
                default => aioHome(1),
            };
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 1, pollSeconds: 0, waitEvdcOffline: false);

    expect($result['powered_on'])->toBeTrue()->and($homeReads)->toBe(4);
});

it('polls again after a forced power-on retry', function () {
    $commands = 0;
    $homeReads = 0;
    $retryReads = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads, &$commands, &$retryReads) {
            if (++$homeReads === 1) {
                return aioHome(1);
            }
            if ($commands >= 3 && ++$retryReads >= 2) {
                return aioHome(1);
            }

            return aioHome(5);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => function () use (&$commands) {
            $commands++;

            return MockResponse::make(['code' => 0, 'data' => true]);
        },
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0.001, pollSeconds: 0, waitEvdcOffline: false);

    expect($result['powered_on'])->toBeTrue()->and($retryReads)->toBeGreaterThanOrEqual(2);
});

it('reads final topology after an EVDC wait is rate limited', function () {
    $homeReads = 0;
    $topologyReads = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 2 ? 5 : 1);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        GetStationTopologyRequest::class => function () use (&$topologyReads) {
            if (++$topologyReads === 2) {
                return MockResponse::make(['code' => 429, 'msg' => 'rate limited'], 429);
            }

            return MockResponse::make(['code' => 0, 'data' => ['nodeList' => [['deviceType' => 5, 'snCode' => 'EVDC', 'deviceStatus' => 1]]]]);
        },
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, evdcOfflineWaitSeconds: 1);

    expect($result['evdc_wait_aborted'])->toBe('topology_rate_limited')
        ->and($result['evdc_topology_after']['offline'])->toBeFalse()
        ->and($topologyReads)->toBe(3);
});

it('reports cycle, recovery, and verification failures together', function () {
    $homeReads = 0;
    $commands = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return ++$homeReads === 1 ? aioHome(1) : MockResponse::make(['code' => 500, 'msg' => 'verification failed'], 500);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => function () use (&$commands) {
            return ++$commands === 1 ? MockResponse::make(['code' => 500, 'msg' => 'off failed'], 500) : MockResponse::make(['code' => 500, 'msg' => 'recovery failed'], 500);
        },
    ]);

    try {
        authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false);
        test()->fail('Expected restart to fail.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('off failed')->toContain('recovery failed')->toContain('verification failed');
        expect($exception->getPrevious()?->getMessage())->toBe('off failed');
    }
});

it('waits for EVDC offline even when the initial topology read was rate limited', function () {
    $homeReads = 0;
    $topologyReads = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 2 ? 5 : 1);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        GetStationTopologyRequest::class => function () use (&$topologyReads) {
            if (++$topologyReads === 1) {
                return MockResponse::make(['code' => 429, 'msg' => 'initial rate limit'], 429);
            }

            return MockResponse::make(['code' => 0, 'data' => ['nodeList' => [['deviceType' => 5, 'deviceStatus' => 5]]]]);
        },
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, evdcOfflineWaitSeconds: 1);

    expect($result['evdc_topology_before'])->toBe(['offline' => null, 'error' => 'topology_read_failed'])
        ->and($result['evdc_offline_seen'])->toBeTrue()
        ->and($result['evdc_wait_aborted'])->toBeNull();
});

it('reports the EVDC rate-limit error and detail after the plant is off', function () {
    $homeReads = 0;
    $topologyReads = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 2 ? 5 : 1);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        GetStationTopologyRequest::class => function () use (&$topologyReads) {
            return ++$topologyReads === 2
                ? MockResponse::make(['code' => 429, 'msg' => 'too many requests'], 429)
                : MockResponse::make(['code' => 0, 'data' => ['nodeList' => []]]);
        },
    ]);

    $result = authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, evdcOfflineWaitSeconds: 1);

    expect($result['evdc_topology_after_off'])->toBe(['offline' => null, 'error' => 'rate_limited', 'detail' => 'too many requests']);
});

it('verifies again after retrying power-on when initial verification fails', function () {
    $homeReads = 0;
    $commands = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return match (++$homeReads) {
                1 => aioHome(1),
                2 => aioHome(5),
                3 => MockResponse::make(['code' => 500, 'msg' => 'verification failed'], 500),
                default => aioHome(1),
            };
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => function () use (&$commands) {
            $commands++;

            return MockResponse::make(['code' => 0, 'data' => true]);
        },
    ]);

    expect(fn () => authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false))->toThrow(SigenCloudException::class, 'verification failed');
    expect($homeReads)->toBe(4)->and($commands)->toBe(3);
});

it('preserves the off error and verifies after a failed forced power-on retry', function () {
    $homeReads = 0;
    $commands = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 1 ? 1 : 5);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => false]),
        UpdateAioPowerRequest::class => function () use (&$commands) {
            return match (++$commands) {
                1 => MockResponse::make(['code' => 500, 'msg' => 'off failed'], 500),
                3 => MockResponse::make(['code' => 500, 'msg' => 'retry failed'], 500),
                default => MockResponse::make(['code' => 0, 'data' => true]),
            };
        },
    ]);

    try {
        authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false);
        test()->fail('Expected restart to fail.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('off failed')->toContain('retry failed');
        expect($exception->getPrevious()?->getMessage())->toBe('off failed');
    }
    expect($homeReads)->toBeGreaterThanOrEqual(3);
});

it('retains prior failures when forced-retry verification also fails', function () {
    $homeReads = 0;
    $commands = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return match (++$homeReads) {
                1 => aioHome(1),
                2 => aioHome(5),
                default => MockResponse::make(['code' => 500, 'msg' => 'retry status failed'], 500),
            };
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => false]),
        UpdateAioPowerRequest::class => function () use (&$commands) {
            return match (++$commands) {
                1 => MockResponse::make(['code' => 500, 'msg' => 'off failed'], 500),
                3 => MockResponse::make(['code' => 500, 'msg' => 'retry failed'], 500),
                default => MockResponse::make(['code' => 0, 'data' => true]),
            };
        },
    ]);

    try {
        authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false);
        test()->fail('Expected restart to fail.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('off failed')->toContain('retry failed')->toContain('retry status failed');
        expect($exception->getPrevious()?->getMessage())->toBe('off failed');
    }
});

it('retains both power-on failures when recovery requests fail twice', function () {
    $homeReads = 0;
    $commands = 0;
    MockClient::global([
        GetStationRequest::class => function () use (&$homeReads) {
            return aioHome(++$homeReads === 2 ? 5 : 1);
        },
        GetAioPowerStatusRequest::class => MockResponse::make(['code' => 0, 'data' => true]),
        UpdateAioPowerRequest::class => function () use (&$commands) {
            return match (++$commands) {
                1 => MockResponse::make(['code' => 0, 'data' => true]),
                2 => MockResponse::make(['code' => 500, 'msg' => 'first power-on failed'], 500),
                default => MockResponse::make(['code' => 500, 'msg' => 'second power-on failed'], 500),
            };
        },
    ]);

    try {
        authenticatedSigen()->aio()->restart(123, 'AIO-42', timeoutSeconds: 0, pollSeconds: 0, waitEvdcOffline: false);
        test()->fail('Expected restart to fail.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('first power-on failed')->toContain('second power-on failed');
    }
});

function aioHome(int $status): MockResponse
{
    return MockResponse::make(['code' => 0, 'data' => [
        'stationId' => 123, 'onGrid' => true, 'pvCapacity' => 4, 'batteryCapacity' => 8,
        'status' => $status, 'statusDesc' => 'status',
    ]]);
}

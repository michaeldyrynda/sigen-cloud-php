<?php

use Dyrynda\SigenCloud\Data\TopologyNode;
use Dyrynda\SigenCloud\Requests\GetStationTopologyRequest;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('reads the device tree of the station depth first', function () {
    $mockClient = MockClient::global([
        GetStationTopologyRequest::class => MockResponse::make([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'stationId' => 120250610002197,
                'stationStatus' => 1,
                'nodeList' => [
                    [
                        'snCode' => 'CMU110A335X0463',
                        'deviceType' => 2,
                        'deviceTypeDesc' => 'Aio',
                        'deviceStatus' => 1,
                        'communicateStatus' => 2,
                        'nodeList' => [
                            [
                                'snCode' => '110A335X0463',
                                'deviceType' => 3,
                                'nodeList' => [['snCode' => 'EVDC1', 'deviceType' => 5, 'nodeList' => []]],
                            ],
                            ['snCode' => '130B639Y5459', 'deviceType' => 4, 'nodeList' => []],
                        ],
                    ],
                    ['snCode' => '110G138D1898', 'deviceType' => 8, 'nodeList' => null],
                ],
            ],
        ]),
    ]);

    $topology = authenticatedSigen()->stations()->topology(120250610002197);

    expect($topology->stationStatus)->toBe(1)
        ->and(array_map(fn (TopologyNode $node) => $node->snCode, $topology->nodes()))
        ->toBe(['CMU110A335X0463', '110A335X0463', 'EVDC1', '130B639Y5459', '110G138D1898'])
        ->and($topology->nodes()[0])
        ->deviceType->toBe(TopologyNode::AIO)
        ->deviceTypeDesc->toBe('Aio')
        ->deviceStatus->toBe(1)
        ->communicateStatus->toBe(2)
        ->and(sentRequest($mockClient, GetStationTopologyRequest::class))
        ->getMethod()->toBe(Method::GET)
        ->getUrl()->toBe('https://api-aus.sigencloud.com/device/devicetreepanel/topology')
        ->query()->all()->toBe(['stationId' => 120250610002197]);
});

it('finds a nested device by type and trimmed display serial', function () {
    MockClient::global([
        GetStationTopologyRequest::class => MockResponse::make(['code' => 0, 'data' => ['nodeList' => [
            ['deviceType' => 2, 'nodeList' => [
                ['deviceType' => 5, 'showSnCode' => ' EVDC2 '],
                ['deviceType' => 5, 'snCode' => 'EVDC1'],
            ]],
        ]]]),
    ]);

    $topology = authenticatedSigen()->stations()->topology(123);

    expect($topology->findNode(5, ' EVDC2 ')?->showSnCode)->toBe(' EVDC2 ')
        ->and($topology->findNode(4, 'EVDC2'))->toBeNull();
});

it('interprets topology communication and device status for offline detection', function () {
    MockClient::global([
        GetStationTopologyRequest::class => MockResponse::make(['code' => 0, 'data' => ['nodeList' => [
            ['deviceStatus' => 1, 'communicateStatus' => 1],
            ['deviceStatus' => '5', 'communicateStatus' => 2],
            ['deviceStatus' => 1, 'communicateStatus' => 2],
            ['deviceStatus' => 1],
            ['deviceStatus' => 'bad'],
            ['deviceStatus' => 7, 'communicateStatus' => 2],
        ]]]),
    ]);

    $nodes = authenticatedSigen()->stations()->topology(123)->nodes();

    expect(array_map(fn (TopologyNode $node) => $node->isOffline(), $nodes))
        ->toBe([true, true, false, false, null, true]);
});

it('reports charger status and falls back to the first charger when the serial is absent', function () {
    MockClient::global([
        GetStationTopologyRequest::class => MockResponse::make(['code' => 0, 'data' => [
            'stationStatus' => 3,
            'nodeList' => [['deviceType' => 5, 'showSnCode' => 'CHARGER', 'deviceStatus' => 5]],
        ]]),
    ]);

    $status = authenticatedSigen()->stations()->topologyEvdcStatus(123, 'MISSING');

    expect($status)->toBe([
        'station_status' => 3,
        'sn_code' => 'CHARGER',
        'device_type' => 5,
        'device_type_desc' => null,
        'device_status' => 5,
        'communicate_status' => null,
        'offline' => true,
        'node_found' => true,
    ]);
});

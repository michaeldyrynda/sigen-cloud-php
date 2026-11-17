<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class GetDcChargerStationChargingRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(private readonly int $stationId) {}

    public function resolveEndpoint(): string
    {
        return '/device/charge/check/charge';
    }

    /** @return array{stationId: int} */
    protected function defaultQuery(): array
    {
        return ['stationId' => $this->stationId];
    }
}

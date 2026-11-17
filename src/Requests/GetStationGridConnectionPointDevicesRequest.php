<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class GetStationGridConnectionPointDevicesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly int $stationId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/device/station/grid-connection-point/support-devices';
    }

    /**
     * @return array{stationId: int}
     */
    protected function defaultQuery(): array
    {
        return ['stationId' => $this->stationId];
    }
}

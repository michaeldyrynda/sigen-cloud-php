<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class GetStationEnergyFlowRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly int $stationId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/device/sigen/station/energyflow';
    }

    /**
     * @return array{id: int}
     */
    protected function defaultQuery(): array
    {
        return ['id' => $this->stationId];
    }
}

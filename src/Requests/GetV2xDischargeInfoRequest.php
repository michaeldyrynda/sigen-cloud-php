<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class GetV2xDischargeInfoRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly int $stationId, private readonly string $serial) {}

    public function resolveEndpoint(): string
    {
        return '/device/station-v2x/discharge/info';
    }

    /** @return array{stationId: int, dcSnCode: string} */
    protected function defaultQuery(): array
    {
        return ['stationId' => $this->stationId, 'dcSnCode' => $this->serial];
    }
}

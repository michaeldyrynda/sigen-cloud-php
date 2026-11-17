<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class GetDcChargerPlugStatusRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly int $stationId, private readonly string $serial) {}

    public function resolveEndpoint(): string
    {
        return '/device/dcevse/plug/status';
    }

    /** @return array{stationId: int, snCode: string} */
    protected function defaultQuery(): array
    {
        return ['stationId' => $this->stationId, 'snCode' => $this->serial];
    }
}

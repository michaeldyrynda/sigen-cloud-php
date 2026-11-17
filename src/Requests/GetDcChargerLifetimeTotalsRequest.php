<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class GetDcChargerLifetimeTotalsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly int $stationId,
        private readonly string $serial,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/data-process/dcevse/total/{$this->stationId}/{$this->serial}";
    }
}

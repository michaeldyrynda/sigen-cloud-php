<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class StopV2xDischargeRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly int $stationId, private readonly string $serial) {}

    public function resolveEndpoint(): string
    {
        return '/device/station-v2x/stop/discharge';
    }

    /** @return array{stationId: int, snCode: string} */
    protected function defaultBody(): array
    {
        return ['stationId' => $this->stationId, 'snCode' => $this->serial];
    }
}

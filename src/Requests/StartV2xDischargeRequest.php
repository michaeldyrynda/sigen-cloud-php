<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class StartV2xDischargeRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly int $stationId, private readonly string $serial, private readonly int $durationMinutes, private readonly ?float $powerCapKw) {}

    public function resolveEndpoint(): string
    {
        return '/device/station-v2x/start/discharge';
    }

    /** @return array{stationId: int, snCode: string, duration: int, powerCap: ?float} */
    protected function defaultBody(): array
    {
        return ['stationId' => $this->stationId, 'snCode' => $this->serial, 'duration' => $this->durationMinutes, 'powerCap' => $this->powerCapKw];
    }
}

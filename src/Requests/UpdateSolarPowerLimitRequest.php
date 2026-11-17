<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateSolarPowerLimitRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly int $stationId,
        private readonly float $kilowatts,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/device/energy-profile/solar/limit';
    }

    /**
     * @return array{powerLimit: string, stationId: int}
     */
    protected function defaultBody(): array
    {
        return [
            'powerLimit' => number_format($this->kilowatts, 3, '.', ''),
            'stationId' => $this->stationId,
        ];
    }
}

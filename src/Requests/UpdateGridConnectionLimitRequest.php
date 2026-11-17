<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateGridConnectionLimitRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly int $stationId,
        private readonly float $amps,
        private readonly bool $enabled,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/device/energy-profile/parallel/off/grid';
    }

    /**
     * @return array{stationId: int, enable: bool, ownerSetLimitation: string, installerSetLimitation: null}
     */
    protected function defaultBody(): array
    {
        return [
            'stationId' => $this->stationId,
            'enable' => $this->enabled,
            'ownerSetLimitation' => number_format($this->amps, 1, '.', ''),
            'installerSetLimitation' => null,
        ];
    }
}

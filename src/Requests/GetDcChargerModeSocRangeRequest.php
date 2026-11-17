<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class GetDcChargerModeSocRangeRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly int $stationId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/device/charge/mode/soc/range';
    }

    /**
     * @return array{stationId: int}
     */
    protected function defaultQuery(): array
    {
        return [
            'stationId' => $this->stationId,
        ];
    }
}

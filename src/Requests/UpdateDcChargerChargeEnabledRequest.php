<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class UpdateDcChargerChargeEnabledRequest extends Request
{
    protected Method $method = Method::PUT;

    public function __construct(private readonly int $stationId, private readonly string $serial, private readonly bool $enabled) {}

    public function resolveEndpoint(): string
    {
        return '/device/dcevse/charge/start';
    }

    /** @return array{stationId: int, snCode: string, enable: int} */
    protected function defaultQuery(): array
    {
        return ['stationId' => $this->stationId, 'snCode' => $this->serial, 'enable' => $this->enabled ? 0 : 1];
    }
}

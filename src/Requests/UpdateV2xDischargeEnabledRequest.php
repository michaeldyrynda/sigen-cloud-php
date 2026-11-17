<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateV2xDischargeEnabledRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly int $stationId, private readonly string $serial, private readonly bool $enabled) {}

    public function resolveEndpoint(): string
    {
        return '/device/station-v2x/open-close';
    }

    /** @return array{stationId: int, snCode: string, dischargeEnable: int} */
    protected function defaultBody(): array
    {
        return ['stationId' => $this->stationId, 'snCode' => $this->serial, 'dischargeEnable' => $this->enabled ? 1 : 0];
    }
}

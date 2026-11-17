<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateGridExportLimitRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly int $stationId,
        private readonly float $kilowatts,
        private readonly bool $enabled,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/device/energy-profile/grid/limitation/export';
    }

    /**
     * @return array{stationId: int, enable: bool, maxLimitationOwner: string, maxLimitationInstaller: null}
     */
    protected function defaultBody(): array
    {
        return [
            'stationId' => $this->stationId,
            'enable' => $this->enabled,
            'maxLimitationOwner' => number_format($this->kilowatts, 3, '.', ''),
            'maxLimitationInstaller' => null,
        ];
    }
}

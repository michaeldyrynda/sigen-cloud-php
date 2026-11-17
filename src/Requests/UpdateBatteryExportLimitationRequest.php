<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateBatteryExportLimitationRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly int $stationId,
        private readonly bool $enabled,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/device/energy-profile/battery/export/limitation';
    }

    /**
     * @return array{stationId: int, installerSetEnable: null, ownerSetEnable: bool}
     */
    protected function defaultBody(): array
    {
        return [
            'stationId' => $this->stationId,
            'installerSetEnable' => null,
            'ownerSetEnable' => $this->enabled,
        ];
    }
}

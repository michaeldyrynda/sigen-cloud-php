<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateOperatingModeRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(private readonly int $stationId, private readonly int $mode, private readonly int $profileId) {}

    public function resolveEndpoint(): string
    {
        return '/device/energy-profile/mode';
    }

    /** @return array{stationId: int, operationMode: int, profileId: int} */
    protected function defaultBody(): array
    {
        return ['stationId' => $this->stationId, 'operationMode' => $this->mode, 'profileId' => $this->profileId];
    }
}

<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateBatteryBackupReserveRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly int $stationId,
        private readonly int $percent,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/device/setting/backup/reserve';
    }

    /**
     * @return array{backupReserve: int, stationId: int}
     */
    protected function defaultBody(): array
    {
        return [
            'backupReserve' => $this->percent,
            'stationId' => $this->stationId,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class GetStationAlarmsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly int $stationId,
        private readonly int $page,
        private readonly int $pageSize,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/device/alarm/page';
    }

    /**
     * @return array{stationId: int, current: int, size: int}
     */
    protected function defaultQuery(): array
    {
        return [
            'stationId' => $this->stationId,
            'current' => $this->page,
            'size' => $this->pageSize,
        ];
    }
}

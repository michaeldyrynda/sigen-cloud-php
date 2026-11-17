<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use DateTimeInterface;
use Saloon\Enums\Method;
use Saloon\Http\Request;

final class GetDcChargerSessionRecordsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly int $stationId,
        private readonly string $serial,
        private readonly DateTimeInterface $from,
        private readonly DateTimeInterface $to,
        private readonly int $page,
        private readonly int $perPage,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/data-process/dcevse/record/page';
    }

    /**
     * @return array{stationId: int, snCode: string, current: int, size: int, startTime: string, endTime: string}
     */
    protected function defaultQuery(): array
    {
        return [
            'stationId' => $this->stationId,
            'snCode' => $this->serial,
            'current' => $this->page,
            'size' => $this->perPage,
            'startTime' => $this->from->format('Ymd'),
            'endTime' => $this->to->format('Ymd'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateAioPowerRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly int $stationId, private readonly string $serial, private readonly bool $powerOn = false) {}

    public function resolveEndpoint(): string
    {
        return '/device/aio/on-off';
    }

    /** @return array{snCode: string, stationId: int, powerOn?: true} */
    protected function defaultBody(): array
    {
        $body = ['snCode' => $this->serial, 'stationId' => $this->stationId];

        if ($this->powerOn) {
            $body['powerOn'] = true;
        }

        return $body;
    }
}

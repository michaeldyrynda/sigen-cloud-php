<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class GetStationEnergyFlowAsyncRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/device/sigen/station/energyflow/async';
    }

    /**
     * @return array{refreshFlag: string}
     */
    protected function defaultQuery(): array
    {
        return ['refreshFlag' => 'false'];
    }
}

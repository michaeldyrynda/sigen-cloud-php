<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Resources;

use Dyrynda\SigenCloud\Data\Station;
use Dyrynda\SigenCloud\Requests\GetStationRequest;
use Saloon\Http\BaseResource;

final class StationResource extends BaseResource
{
    public function current(): Station
    {
        return Station::fromResponse($this->connector->send(
            new GetStationRequest
        ));
    }
}

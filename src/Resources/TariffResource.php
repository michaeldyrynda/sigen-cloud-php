<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Resources;

use Dyrynda\SigenCloud\Requests\GetElectricityPriceCostRequest;
use Dyrynda\SigenCloud\Requests\GetElectricityTaxAndFeeRequest;
use Dyrynda\SigenCloud\Requests\UpdateElectricityTaxAndFeeRequest;
use InvalidArgumentException;
use Saloon\Http\BaseResource;
use UnexpectedValueException;

final class TariffResource extends BaseResource
{
    /** @return array<array-key, mixed> */
    public function priceCost(int $stationId): array
    {
        $data = $this->connector->send(new GetElectricityPriceCostRequest($stationId))->json('data');

        if (! is_array($data)) {
            throw new UnexpectedValueException('Invalid electricity price cost response data.');
        }

        return $data;
    }

    /** @param array<string, mixed> $taxAndFee */
    public function setTaxAndFee(int $stationId, int $direction, array $taxAndFee): void
    {
        if (! in_array($direction, [1, 2], true)) {
            throw new InvalidArgumentException('Direction must be 1 (buy) or 2 (sell).');
        }

        $this->connector->send(new UpdateElectricityTaxAndFeeRequest($stationId, $direction, $taxAndFee));
    }

    /** @return array<array-key, mixed> */
    public function taxAndFee(int $stationId): array
    {
        $data = $this->connector->send(new GetElectricityTaxAndFeeRequest($stationId))->json('data');

        if (! is_array($data)) {
            throw new UnexpectedValueException('Invalid electricity tax and fee response data.');
        }

        return $data;
    }
}

<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class GetElectricityTaxAndFeeRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly int $stationId) {}

    public function resolveEndpoint(): string
    {
        return "/electricity-price/api/tax-and-fee/{$this->stationId}";
    }
}

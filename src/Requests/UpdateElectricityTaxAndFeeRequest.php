<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateElectricityTaxAndFeeRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /** @param array<string, mixed> $taxAndFee */
    public function __construct(private readonly int $stationId, private readonly int $direction, private readonly array $taxAndFee) {}

    public function resolveEndpoint(): string
    {
        return '/electricity-price/api/tax-and-fee';
    }

    /** @return array{stationId: int, directionType: int, taxAndFee: array<string, mixed>} */
    protected function defaultBody(): array
    {
        return ['stationId' => $this->stationId, 'directionType' => $this->direction, 'taxAndFee' => $this->taxAndFee];
    }
}

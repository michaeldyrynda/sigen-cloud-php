<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Resources;

use Dyrynda\SigenCloud\Requests\GetGridConnectionLimitRequest;
use Dyrynda\SigenCloud\Requests\GetGridExportLimitRequest;
use Dyrynda\SigenCloud\Requests\GetGridImportLimitRequest;
use Dyrynda\SigenCloud\Requests\UpdateGridConnectionLimitRequest;
use Dyrynda\SigenCloud\Requests\UpdateGridExportLimitRequest;
use Dyrynda\SigenCloud\Requests\UpdateGridImportLimitRequest;
use Saloon\Http\BaseResource;

final class GridResource extends BaseResource
{
    /**
     * The max grid connection current settings, in amps rather than kilowatts.
     * The owner limit is an empty string when the owner has not lowered the installer's limit.
     *
     * @return array{enable: bool, currentLimitation: string, ownerSetLimitation: string, installerSetLimitation: string}
     */
    public function connectionLimit(int $stationId): array
    {
        /** @var array{enable: bool, currentLimitation: string, ownerSetLimitation: string, installerSetLimitation: string} */
        return $this->connector->send(new GetGridConnectionLimitRequest($stationId))->json('data');
    }

    /**
     * The grid export limit settings, in kilowatts. A limit is an empty string when it has not been set.
     *
     * @return array{enable: bool, maxLimitation: string, maxLimitationOwner: string, maxLimitationInstaller: string, isUltra: bool}
     */
    public function exportLimit(int $stationId): array
    {
        /** @var array{enable: bool, maxLimitation: string, maxLimitationOwner: string, maxLimitationInstaller: string, isUltra: bool} */
        return $this->connector->send(new GetGridExportLimitRequest($stationId))->json('data');
    }

    /**
     * The grid import limit settings, in kilowatts. A limit is an empty string when it has not been set.
     *
     * @return array{enable: bool, maxLimitation: string, maxLimitationOwner: string, maxLimitationInstaller: string, isUltra: bool}
     */
    public function importLimit(int $stationId): array
    {
        /** @var array{enable: bool, maxLimitation: string, maxLimitationOwner: string, maxLimitationInstaller: string, isUltra: bool} */
        return $this->connector->send(new GetGridImportLimitRequest($stationId))->json('data');
    }

    /**
     * Lower the max grid connection current below the installer's limit. Setting it to the installer's limit clears the owner limit.
     */
    public function setConnectionLimit(int $stationId, float $amps, bool $enabled = true): void
    {
        $this->connector->send(new UpdateGridConnectionLimitRequest($stationId, $amps, $enabled));
    }

    public function setExportLimit(int $stationId, float $kilowatts, bool $enabled = true): void
    {
        $this->connector->send(new UpdateGridExportLimitRequest($stationId, $kilowatts, $enabled));
    }

    public function setImportLimit(int $stationId, float $kilowatts, bool $enabled = true): void
    {
        $this->connector->send(new UpdateGridImportLimitRequest($stationId, $kilowatts, $enabled));
    }
}

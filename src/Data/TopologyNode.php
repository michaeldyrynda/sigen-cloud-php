<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Data;

final readonly class TopologyNode
{
    public const AIO = 2;

    public const BATTERY = 4;

    public const DC_CHARGER = 5;

    public const GATEWAY = 8;

    public const INVERTER = 3;

    /** @param list<TopologyNode> $children */
    public function __construct(
        public ?string $snCode,
        public ?int $deviceType,
        public ?string $deviceTypeDesc,
        public ?int $deviceStatus,
        public ?int $communicateStatus,
        public array $children,
        public ?string $showSnCode,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $children = [];
        foreach (is_array($data['nodeList'] ?? null) ? $data['nodeList'] : [] as $child) {
            if (is_array($child)) {
                $children[] = self::fromArray($child);
            }
        }

        return new self(
            snCode: is_string($data['snCode'] ?? null) ? $data['snCode'] : null,
            deviceType: is_numeric($data['deviceType'] ?? null) ? (int) $data['deviceType'] : null,
            deviceTypeDesc: is_string($data['deviceTypeDesc'] ?? null) ? $data['deviceTypeDesc'] : null,
            deviceStatus: is_numeric($data['deviceStatus'] ?? null) ? (int) $data['deviceStatus'] : null,
            communicateStatus: is_numeric($data['communicateStatus'] ?? null) ? (int) $data['communicateStatus'] : null,
            children: $children,
            showSnCode: is_string($data['showSnCode'] ?? null) ? $data['showSnCode'] : null,
        );
    }

    public function isOffline(): ?bool
    {
        if ($this->communicateStatus === 1 || in_array($this->deviceStatus, [0, 3, 4, 5, 44], true)) {
            return true;
        }

        if ($this->deviceStatus === null && $this->communicateStatus === null) {
            return null;
        }

        return $this->deviceStatus !== null && $this->deviceStatus !== 1;
    }
}

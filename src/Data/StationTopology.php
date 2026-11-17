<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Data;

use Saloon\Http\Response;

final readonly class StationTopology
{
    /** @param list<TopologyNode> $children */
    public function __construct(public ?int $stationStatus, public array $children) {}

    public static function fromResponse(Response $response): self
    {
        $data = $response->json('data');
        $data = is_array($data) ? $data : [];
        $children = [];
        foreach (is_array($data['nodeList'] ?? null) ? $data['nodeList'] : [] as $node) {
            if (is_array($node)) {
                $children[] = TopologyNode::fromArray($node);
            }
        }

        return new self(is_numeric($data['stationStatus'] ?? null) ? (int) $data['stationStatus'] : null, $children);
    }

    public function findNode(?int $deviceType = null, ?string $snCode = null): ?TopologyNode
    {
        $serial = $snCode !== null && $snCode !== '' ? trim($snCode) : null;
        foreach ($this->nodes() as $node) {
            if ($deviceType !== null && $node->deviceType !== $deviceType) {
                continue;
            }
            if ($serial !== null && trim($node->snCode ?: ($node->showSnCode ?? '')) !== $serial) {
                continue;
            }

            return $node;
        }

        return null;
    }

    /** @return list<TopologyNode> */
    public function nodes(): array
    {
        $nodes = [];
        $pending = array_reverse($this->children);
        while ($pending !== []) {
            $node = array_pop($pending);
            $nodes[] = $node;
            array_push($pending, ...array_reverse($node->children));
        }

        return $nodes;
    }
}

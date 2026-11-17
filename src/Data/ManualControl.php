<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Data;

use DateTimeImmutable;
use Dyrynda\SigenCloud\Enums\ManualControlMode;
use Saloon\Http\Response;

final readonly class ManualControl
{
    public function __construct(
        public bool $enabled,
        public ?ManualControlMode $mode,
        public ?DateTimeImmutable $endsAt,
    ) {}

    /**
     * Sigen leaves a stale mode and a zero or blank end time behind when manual control is off.
     */
    public static function fromResponse(Response $response): self
    {
        /** @var array{enable: bool, mode: ?string, endTime: int|string|null} $control */
        $control = $response->json('data');

        if (! $control['enable']) {
            return new self(enabled: false, mode: null, endsAt: null);
        }

        return new self(
            enabled: true,
            mode: ManualControlMode::from($control['mode'] ?? ''),
            endsAt: new DateTimeImmutable("@{$control['endTime']}"),
        );
    }
}

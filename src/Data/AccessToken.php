<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Data;

use DateTimeImmutable;
use Saloon\Http\Response;
use SensitiveParameter;

final readonly class AccessToken
{
    private const int EXPIRY_MARGIN_SECONDS = 60;

    public function __construct(
        #[SensitiveParameter] public string $accessToken,
        public DateTimeImmutable $expiresAt,
        #[SensitiveParameter] public ?string $refreshToken = null,
    ) {}

    public static function fromResponse(Response $response): self
    {
        /** @var array{access_token: string, refresh_token?: string, expires_in: int} $token */
        $token = $response->json('data');

        return new self(
            accessToken: $token['access_token'],
            expiresAt: new DateTimeImmutable("+{$token['expires_in']} seconds"),
            refreshToken: $token['refresh_token'] ?? null,
        );
    }

    public function hasExpired(): bool
    {
        return new DateTimeImmutable('+'.self::EXPIRY_MARGIN_SECONDS.' seconds') >= $this->expiresAt;
    }
}

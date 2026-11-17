<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud;

use Dyrynda\SigenCloud\Data\AccessToken;
use Dyrynda\SigenCloud\Enums\Region;
use Dyrynda\SigenCloud\Exceptions\SigenCloudException;
use Dyrynda\SigenCloud\Requests\AccessTokenRequest;
use Dyrynda\SigenCloud\Resources\BatteryResource;
use Dyrynda\SigenCloud\Resources\SolarResource;
use Dyrynda\SigenCloud\Resources\StationResource;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use SensitiveParameter;
use Throwable;

final class SigenCloud extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;

    /**
     * The value Sigenergy uses to mean "no limit" (0xFFFFFFFF watts).
     */
    public const float UNLIMITED_KILOWATTS = 4294967.295;

    public function __construct(
        #[SensitiveParameter] private readonly string $username,
        #[SensitiveParameter] private readonly string $password,
        private readonly Region $region,
        #[SensitiveParameter] private ?AccessToken $accessToken = null,
    ) {}

    public function accessToken(): AccessToken
    {
        if ($this->accessToken === null || $this->accessToken->hasExpired()) {
            $this->accessToken = AccessToken::fromResponse(
                $this->send(new AccessTokenRequest($this->username, $this->password))
            );
        }

        return $this->accessToken;
    }

    public function battery(): BatteryResource
    {
        return new BatteryResource($this);
    }

    public function getRequestException(Response $response, ?Throwable $senderException): SigenCloudException
    {
        $message = $this->envelope($response)['msg'] ?? null;

        return new SigenCloudException($response, is_string($message) ? $message : null, previous: $senderException);
    }

    /**
     * Sigen reports most failures with a successful HTTP status and a non-zero code.
     */
    public function hasRequestFailed(Response $response): ?bool
    {
        return in_array($this->envelope($response)['code'] ?? null, [0, '0', null], true) ? null : true;
    }

    public function resolveBaseUrl(): string
    {
        return $this->region->value;
    }

    public function solar(): SolarResource
    {
        return new SolarResource($this);
    }

    public function stations(): StationResource
    {
        return new StationResource($this);
    }

    protected function defaultAuth(): TokenAuthenticator
    {
        return new TokenAuthenticator($this->accessToken()->accessToken);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function envelope(Response $response): array
    {
        $body = json_decode($response->body(), true);

        return is_array($body) ? $body : [];
    }
}

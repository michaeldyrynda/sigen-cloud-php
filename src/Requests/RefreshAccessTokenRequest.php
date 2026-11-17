<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;
use SensitiveParameter;

final class RefreshAccessTokenRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(
        #[SensitiveParameter] private readonly string $refreshToken,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/auth/oauth/token';
    }

    protected function defaultAuth(): BasicAuthenticator
    {
        return new BasicAuthenticator('sigen', 'sigen');
    }

    /**
     * @return array{grant_type: string, refresh_token: string}
     */
    protected function defaultBody(): array
    {
        return [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
        ];
    }
}

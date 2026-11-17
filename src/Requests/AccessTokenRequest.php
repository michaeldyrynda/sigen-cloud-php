<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Requests;

use Dyrynda\SigenCloud\Support\PasswordEncrypter;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;
use SensitiveParameter;

final class AccessTokenRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(
        #[SensitiveParameter] private readonly string $username,
        #[SensitiveParameter] private readonly string $password,
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
     * @return array{username: string, password: string, grant_type: string}
     */
    protected function defaultBody(): array
    {
        return [
            'username' => $this->username,
            'password' => PasswordEncrypter::encrypt($this->password),
            'grant_type' => 'password',
        ];
    }
}

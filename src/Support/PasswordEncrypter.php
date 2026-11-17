<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Support;

use SensitiveParameter;

/**
 * Encrypts the account password the way the mySigen app does before login.
 */
final class PasswordEncrypter
{
    private const string KEY = 'sigensigensigenp';

    public static function encrypt(#[SensitiveParameter] string $password): string
    {
        return base64_encode(
            (string) openssl_encrypt($password, 'aes-128-cbc', self::KEY, OPENSSL_RAW_DATA, self::KEY)
        );
    }
}

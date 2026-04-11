<?php

declare(strict_types=1);

namespace App\Core\Security\Credentials;

use Illuminate\Contracts\Encryption\Encrypter;

/**
 * Encryption service for sensitive data.
 *
 * Provides encryption/decryption for secrets using Laravel's native encryption.
 * All secrets are encrypted at rest and only exist decrypted in-memory.
 */
final class SecretEncryptionService
{
    /**
     * Create a new secret encryption service.
     *
     * @param  Encrypter  $encrypter  Laravel's encryption service
     */
    public function __construct(
        private readonly Encrypter $encrypter
    ) {}

    /**
     * Encrypt a secret value.
     *
     * @param  string  $plaintext  The secret to encrypt
     * @return string Encrypted value (base64 encoded)
     *
     * @throws \Illuminate\Contracts\Encryption\EncryptException
     */
    public function encrypt(string $plaintext): string
    {
        if (empty($plaintext)) {
            throw new \InvalidArgumentException('Cannot encrypt empty value');
        }

        return $this->encrypter->encrypt($plaintext);
    }

    /**
     * Decrypt a secret value.
     *
     * @param  string  $encrypted  The encrypted secret
     * @return string Decrypted plaintext value
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    public function decrypt(string $encrypted): string
    {
        if (empty($encrypted)) {
            throw new \InvalidArgumentException('Cannot decrypt empty value');
        }

        return $this->encrypter->decrypt($encrypted);
    }

    /**
     * Decrypt using a specific encryption key.
     *
     * Used for key rotation scenarios.
     *
     * @param  string  $encrypted  The encrypted value
     * @param  string  $key  The encryption key to use
     * @return string Decrypted plaintext value
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    public function decryptWithKey(string $encrypted, string $key): string
    {
        // Create a temporary encrypter with the specified key
        $cipher = config('app.cipher', 'AES-256-CBC');
        $encrypter = new \Illuminate\Encryption\Encrypter($key, $cipher);

        return $encrypter->decrypt($encrypted);
    }

    /**
     * Hash a value for comparison purposes.
     *
     * Useful for checking if a secret has changed without decrypting.
     *
     * @param  string  $value  Value to hash
     * @return string SHA-256 hash
     */
    public function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    /**
     * Verify that encrypted data can be decrypted.
     *
     * @param  string  $encrypted  Encrypted value to test
     * @return bool True if decryption is successful
     */
    public function canDecrypt(string $encrypted): bool
    {
        try {
            $this->decrypt($encrypted);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

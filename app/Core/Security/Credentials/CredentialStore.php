<?php

declare(strict_types=1);

namespace App\Core\Security\Credentials;

/**
 * Secure storage for sensitive credentials.
 *
 * Manages encrypted storage of SSH keys, API tokens, and other secrets.
 * All secrets are encrypted at rest and only decrypted in-memory when needed.
 */
final class CredentialStore
{
    /**
     * Create a new credential store instance.
     *
     * @param  SecretEncryptionService  $encryptionService  Service for encrypting/decrypting secrets
     */
    public function __construct(
        private readonly SecretEncryptionService $encryptionService
    ) {}

    /**
     * Store an SSH private key securely.
     *
     * @param  string  $privateKey  Raw private key content
     * @return string Encrypted private key for storage
     */
    public function storePrivateKey(string $privateKey): string
    {
        // Validate private key format
        if (! $this->isValidPrivateKey($privateKey)) {
            throw new \InvalidArgumentException('Invalid SSH private key format');
        }

        return $this->encryptionService->encrypt($privateKey);
    }

    /**
     * Retrieve and decrypt an SSH private key.
     *
     * @param  string  $encryptedPrivateKey  Encrypted private key from storage
     * @return string Decrypted private key (in-memory only)
     */
    public function retrievePrivateKey(string $encryptedPrivateKey): string
    {
        return $this->encryptionService->decrypt($encryptedPrivateKey);
    }

    /**
     * Store an API token securely.
     *
     * @param  string  $token  Raw API token
     * @return string Encrypted token for storage
     */
    public function storeApiToken(string $token): string
    {
        if (empty(trim($token))) {
            throw new \InvalidArgumentException('API token cannot be empty');
        }

        return $this->encryptionService->encrypt($token);
    }

    /**
     * Retrieve and decrypt an API token.
     *
     * @param  string  $encryptedToken  Encrypted token from storage
     * @return string Decrypted token (in-memory only)
     */
    public function retrieveApiToken(string $encryptedToken): string
    {
        return $this->encryptionService->decrypt($encryptedToken);
    }

    /**
     * Store a generic secret securely.
     *
     * @param  string  $secret  Raw secret value
     * @return string Encrypted secret for storage
     */
    public function storeSecret(string $secret): string
    {
        if (empty($secret)) {
            throw new \InvalidArgumentException('Secret cannot be empty');
        }

        return $this->encryptionService->encrypt($secret);
    }

    /**
     * Retrieve and decrypt a generic secret.
     *
     * @param  string  $encryptedSecret  Encrypted secret from storage
     * @return string Decrypted secret (in-memory only)
     */
    public function retrieveSecret(string $encryptedSecret): string
    {
        return $this->encryptionService->decrypt($encryptedSecret);
    }

    /**
     * Validate SSH private key format.
     */
    private function isValidPrivateKey(string $privateKey): bool
    {
        // Check for common private key headers
        $validHeaders = [
            '-----BEGIN RSA PRIVATE KEY-----',
            '-----BEGIN OPENSSH PRIVATE KEY-----',
            '-----BEGIN EC PRIVATE KEY-----',
            '-----BEGIN PRIVATE KEY-----',
        ];

        foreach ($validHeaders as $header) {
            if (str_starts_with(trim($privateKey), $header)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rotate encryption for stored credentials.
     *
     * Used when the application encryption key changes.
     *
     * @param  string  $encryptedValue  Old encrypted value
     * @param  string  $oldKey  Old encryption key
     * @return string New encrypted value
     */
    public function rotateEncryption(string $encryptedValue, string $oldKey): string
    {
        // Decrypt with old key
        $plaintext = $this->encryptionService->decryptWithKey($encryptedValue, $oldKey);

        // Re-encrypt with current key
        return $this->encryptionService->encrypt($plaintext);
    }
}

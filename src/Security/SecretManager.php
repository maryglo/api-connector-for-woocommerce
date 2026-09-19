<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Security;

defined('ABSPATH') || exit;

/**
 * Encrypts and decrypts sensitive plugin credentials.
 */
final class SecretManager
{
    private const PREFIX = 'enc:v1:';
    private const ENCRYPTION_KEY_CONSTANT = 'WCAC_ENCRYPTION_KEY';

    /**
     * Encrypt a secret for storage.
     */
    public static function encrypt(string $secret): string
    {
        if ('' === $secret) {
            return '';
        }

        if (! function_exists('sodium_crypto_secretbox')) {
            throw new \RuntimeException(
                'Sodium is required to encrypt API credentials.'
            );
        }

        $key = self::key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $ciphertext = sodium_crypto_secretbox(
            $secret,
            $nonce,
            $key
        );

        return self::PREFIX . base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypt a stored secret.
     */
    public static function decrypt(string $encrypted): string
    {
        if ('' === $encrypted) {
            return '';
        }

        if (! str_starts_with($encrypted, self::PREFIX)) {
            throw new \RuntimeException(
                'Invalid encrypted API credential.'
            );
        }

        if (! function_exists('sodium_crypto_secretbox_open')) {
            throw new \RuntimeException(
                'Sodium is required to decrypt API credentials.'
            );
        }

        $encoded = substr($encrypted, strlen(self::PREFIX));
        $decoded = base64_decode($encoded, true);

        if (false === $decoded) {
            throw new \RuntimeException(
                'Invalid encrypted API credential.'
            );
        }

        $nonceLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

        if (strlen($decoded) <= $nonceLength) {
            throw new \RuntimeException(
                'Invalid encrypted API credential.'
            );
        }

        $nonce = substr($decoded, 0, $nonceLength);
        $ciphertext = substr($decoded, $nonceLength);

        $secret = sodium_crypto_secretbox_open(
            $ciphertext,
            $nonce,
            self::key()
        );

        if (false === $secret) {
            throw new \RuntimeException(
                'Unable to decrypt API credential.'
            );
        }

        return $secret;
    }

    /**
     * Return the encryption key.
     *
     * Prefer a site-specific WordPress secret when available.
     */
    private static function key(): string
    {
        if (
            defined(self::ENCRYPTION_KEY_CONSTANT)
            && is_string(constant(self::ENCRYPTION_KEY_CONSTANT))
            && '' !== constant(self::ENCRYPTION_KEY_CONSTANT)
        ) {
            $key = base64_decode(
                constant(self::ENCRYPTION_KEY_CONSTANT),
                true
            );

            if (false !== $key && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen($key)) {
                return $key;
            }

            throw new \RuntimeException(
                'WCAC_ENCRYPTION_KEY must be a valid base64-encoded 32-byte key.'
            );
        }

        throw new \RuntimeException(
            'WCAC_ENCRYPTION_KEY is not configured.'
        );
    }
}

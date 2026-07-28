<?php

class shopIndexnowruSecret
{
    const PREFIX = 'enc:v1:';
    const CIPHER = 'aes-256-gcm';
    const IV_BYTES = 12;
    const TAG_BYTES = 16;

    public static function encrypt($plaintext)
    {
        $plaintext = (string)$plaintext;
        if ($plaintext === '') {
            return '';
        }
        if (!function_exists('openssl_encrypt') || !function_exists('random_bytes')) {
            throw new waException('OpenSSL and a cryptographically secure random source are required.');
        }

        $key = self::loadKey(true);
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_BYTES
        );
        if ($ciphertext === false || strlen($tag) !== self::TAG_BYTES) {
            throw new waException('Failed to encrypt the API key.');
        }

        return self::PREFIX.base64_encode($iv.$tag.$ciphertext);
    }

    public static function decrypt($stored)
    {
        $stored = (string)$stored;
        if ($stored === '') {
            return '';
        }
        if (strpos($stored, self::PREFIX) !== 0 || !function_exists('openssl_decrypt')) {
            throw new waException('The stored API key has an unsupported format.');
        }

        $payload = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($payload === false || strlen($payload) <= self::IV_BYTES + self::TAG_BYTES) {
            throw new waException('The stored API key is damaged.');
        }

        $iv = substr($payload, 0, self::IV_BYTES);
        $tag = substr($payload, self::IV_BYTES, self::TAG_BYTES);
        $ciphertext = substr($payload, self::IV_BYTES + self::TAG_BYTES);
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::loadKey(false),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        if ($plaintext === false) {
            throw new waException('The stored API key could not be decrypted.');
        }

        return $plaintext;
    }

    public static function getKeyFilePath()
    {
        return rtrim(waConfig::get('wa_path_config'), '/\\').'/apps/shop/indexnowru.secret.php';
    }

    public static function deleteKeyFile()
    {
        $path = self::getKeyFilePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function loadKey($create)
    {
        $path = self::getKeyFilePath();
        if (is_file($path)) {
            $encoded = include($path);
            $key = is_string($encoded) ? base64_decode($encoded, true) : false;
            if ($key !== false && strlen($key) === 32) {
                return $key;
            }
            throw new waException('The Index-Now.ru encryption key file is invalid.');
        }

        if (!$create) {
            throw new waException('The Index-Now.ru encryption key file is missing.');
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            waFiles::create($directory);
        }

        $key = random_bytes(32);
        if (!waUtils::varExportToFile(base64_encode($key), $path)) {
            throw new waException('Could not create the Index-Now.ru encryption key file.');
        }
        @chmod($path, 0600);

        return $key;
    }
}


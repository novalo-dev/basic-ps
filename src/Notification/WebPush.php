<?php
/**
 * Copyright since 2026 Novalo
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to ouanis.m@novalo.fr so we can send you a copy immediately.
 *
 * @author    Novalo <ouanis.m@novalo.fr>
 * @copyright Since 2026 Novalo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\PsEditionBasic\Notification;

/**
 * The Web Push protocol with PHP's own OpenSSL, so the module needs no library:
 * the shop's VAPID keys (RFC 8292), which tell the push services the
 * notifications come from this shop, and the encryption of each message for one
 * browser (RFC 8291, aes128gcm of RFC 8188), which only that browser can read.
 */
class WebPush
{
    public const PUBLIC_KEY = 'PS_EDITION_BASIC_VAPID_PUBLIC';
    public const PRIVATE_KEY = 'PS_EDITION_BASIC_VAPID_PRIVATE';

    /** SubjectPublicKeyInfo of a P-256 key, up to the 65 bytes of the point. */
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** Longer than any push service waits, short enough not to hold a request. */
    private const TIMEOUT = 2;

    public static function available(): bool
    {
        return function_exists('openssl_pkey_derive') && function_exists('curl_multi_init')
            && in_array('aes-128-gcm', openssl_get_cipher_methods(), true);
    }

    /**
     * The shop's key pair, created once: every browser subscribes with the
     * public key, so a new pair would silence the browsers already subscribed.
     */
    public static function installKeys(): bool
    {
        if (\Configuration::get(self::PUBLIC_KEY) && \Configuration::get(self::PRIVATE_KEY)) {
            return true;
        }
        if (!self::available()) {
            // The rest of the module works without push; the settings page says so.
            return true;
        }
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false || !openssl_pkey_export($key, $pem)) {
            return true;
        }
        $public = self::rawPublicKey($key);
        if ($public === null) {
            return true;
        }

        return \Configuration::updateValue(self::PUBLIC_KEY, self::base64Url($public))
            && \Configuration::updateValue(self::PRIVATE_KEY, (string) $pem);
    }

    public static function uninstallKeys(): void
    {
        \Configuration::deleteByName(self::PUBLIC_KEY);
        \Configuration::deleteByName(self::PRIVATE_KEY);
    }

    /**
     * The key a browser subscribes with, empty when the shop cannot send.
     */
    public static function publicKey(): string
    {
        return self::available() ? (string) \Configuration::get(self::PUBLIC_KEY) : '';
    }

    /**
     * Sends messages, all at once, and waits at most TIMEOUT seconds for them.
     *
     * @param array<int, array{endpoint: string, p256dh: string, auth: string, payload: string}> $messages
     *
     * @return array<int, int> the HTTP status of each message, 0 when it got no answer
     */
    public static function send(array $messages, int $ttl): array
    {
        $private = openssl_pkey_get_private((string) \Configuration::get(self::PRIVATE_KEY));
        $public = (string) \Configuration::get(self::PUBLIC_KEY);
        if (!$messages || $private === false || $public === '' || !self::available()) {
            return [];
        }

        $multi = curl_multi_init();
        $handles = [];
        foreach ($messages as $index => $message) {
            $body = self::encrypt($message['payload'], $message['p256dh'], $message['auth']);
            $jwt = self::vapidToken($message['endpoint'], $private);
            if ($body === null || $jwt === null) {
                continue;
            }
            $handle = curl_init($message['endpoint']);
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/octet-stream',
                    'Content-Encoding: aes128gcm',
                    'TTL: ' . $ttl,
                    'Urgency: high',
                    'Authorization: vapid t=' . $jwt . ', k=' . $public,
                ],
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[$index] = $handle;
        }

        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 0.2);
            }
        } while ($running && $status === CURLM_OK);

        $codes = [];
        foreach ($handles as $index => $handle) {
            $codes[$index] = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            // Freed with the handle itself: curl_close() does nothing since PHP 8.
            curl_multi_remove_handle($multi, $handle);
        }
        curl_multi_close($multi);

        return $codes;
    }

    /**
     * One aes128gcm record for the browser's key: a key pair made for this
     * message alone, shared with the browser's through ECDH, then the content
     * key and nonce drawn from it and the browser's secret by HKDF.
     */
    public static function encrypt(string $payload, string $browserKey, string $authSecret): ?string
    {
        $browserPublic = self::base64UrlDecode($browserKey);
        $auth = self::base64UrlDecode($authSecret);
        if (strlen($browserPublic) !== 65 || strlen($auth) !== 16) {
            return null;
        }
        $browser = openssl_pkey_get_public(self::pem($browserPublic));
        $local = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($browser === false || $local === false) {
            return null;
        }
        $localPublic = self::rawPublicKey($local);
        $shared = openssl_pkey_derive($browser, $local);
        if ($localPublic === null || $shared === false) {
            return null;
        }

        $ikm = self::hkdf($auth, $shared, "WebPush: info\0" . $browserPublic . $localPublic, 32);
        $salt = random_bytes(16);
        $contentKey = self::hkdf($salt, $ikm, "Content-Encoding: aes128gcm\0", 16);
        $nonce = self::hkdf($salt, $ikm, "Content-Encoding: nonce\0", 12);

        // A single record, so its padding delimiter is 2, the last record's.
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $contentKey, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cipher === false) {
            return null;
        }

        return $salt . pack('N', 4096) . chr(65) . $localPublic . $cipher . $tag;
    }

    /**
     * The signed token a push service asks for: for its own origin, valid one
     * hour, which every service accepts, with the shop's address as contact.
     *
     * @param resource|object $private a key resource on PHP 7.4, an OpenSSLAsymmetricKey from PHP 8
     */
    private static function vapidToken(string $endpoint, $private): ?string
    {
        $parts = parse_url($endpoint);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $audience = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $email = (string) \Configuration::get('PS_SHOP_EMAIL');
        $subject = \Validate::isEmail($email) ? 'mailto:' . $email : \Tools::getShopDomainSsl(true);

        $input = self::base64Url((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
            . '.' . self::base64Url((string) json_encode(['aud' => $audience, 'exp' => time() + 3600, 'sub' => $subject]));
        if (!openssl_sign($input, $der, $private, OPENSSL_ALGO_SHA256)) {
            return null;
        }
        $signature = self::joseSignature((string) $der);

        return $signature === null ? null : $input . '.' . self::base64Url($signature);
    }

    /**
     * OpenSSL signs in DER, JOSE wants r and s side by side, 32 bytes each.
     */
    private static function joseSignature(string $der): ?string
    {
        if (strlen($der) < 8 || $der[0] !== "\x30") {
            return null;
        }
        $offset = 2;
        $parts = [];
        for ($i = 0; $i < 2; ++$i) {
            if (!isset($der[$offset + 1]) || $der[$offset] !== "\x02") {
                return null;
            }
            $length = ord($der[$offset + 1]);
            $value = ltrim(substr($der, $offset + 2, $length), "\x00");
            if (strlen($value) > 32) {
                return null;
            }
            $parts[] = str_pad($value, 32, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $parts[0] . $parts[1];
    }

    /**
     * @param resource|object $key a key resource on PHP 7.4, an OpenSSLAsymmetricKey from PHP 8
     */
    private static function rawPublicKey($key): ?string
    {
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || !isset($details['ec']['x'], $details['ec']['y'])) {
            return null;
        }

        return "\x04" . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    }

    private static function pem(string $rawPublicKey): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode((string) hex2bin(self::P256_SPKI_PREFIX) . $rawPublicKey), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    /** HKDF-SHA-256 with one block of output, all these keys need. */
    private static function hkdf(string $salt, string $ikm, string $info, int $length): string
    {
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        return substr(hash_hmac('sha256', $info . "\x01", $prk, true), 0, $length);
    }

    public static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4), true);
    }
}

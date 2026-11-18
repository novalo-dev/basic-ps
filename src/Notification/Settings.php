<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\PsEditionBasic\Notification;

/**
 * What the merchant chose on the Notifications view of the settings page. The
 * three kinds are those of the bell at the top of the back office, so the badge
 * on the browser tab and the notifications tell the same news as the bell.
 */
class Settings
{
    public const ORDER = 'order';
    public const CUSTOMER = 'customer';
    public const MESSAGE = 'customer_message';

    /** The page each kind opens on, whose View permission it needs. */
    public const PAGES = [
        self::ORDER => 'AdminOrders',
        self::CUSTOMER => 'AdminCustomers',
        self::MESSAGE => 'AdminCustomerThreads',
    ];

    private const KEYS = [
        self::ORDER => 'PS_EDITION_BASIC_NOTIFY_ORDER',
        self::CUSTOMER => 'PS_EDITION_BASIC_NOTIFY_CUSTOMER',
        self::MESSAGE => 'PS_EDITION_BASIC_NOTIFY_MESSAGE',
    ];

    public const BACKGROUND_KEY = 'PS_EDITION_BASIC_NOTIFY_BACKGROUND';
    public const TEXT_KEY = 'PS_EDITION_BASIC_NOTIFY_TEXT';
    public const VALIDITY_KEY = 'PS_EDITION_BASIC_NOTIFY_VALIDITY';

    public const DEFAULT_BACKGROUND = '#DF0067';
    public const DEFAULT_TEXT = '#FFFFFF';
    public const DEFAULT_VALIDITY = 86400;

    /** How long a push service keeps a notification for a browser that is offline, in seconds. */
    public const VALIDITIES = [3600, 21600, 43200, 86400, 259200, 604800];

    public static function enabled(string $kind): bool
    {
        return isset(self::KEYS[$kind]) && (string) \Configuration::get(self::KEYS[$kind]) === '1';
    }

    /**
     * @return array<string, bool>
     */
    public static function kinds(): array
    {
        $kinds = [];
        foreach (array_keys(self::KEYS) as $kind) {
            $kinds[$kind] = self::enabled($kind);
        }

        return $kinds;
    }

    public static function background(): string
    {
        return self::color((string) \Configuration::get(self::BACKGROUND_KEY), self::DEFAULT_BACKGROUND);
    }

    public static function text(): string
    {
        return self::color((string) \Configuration::get(self::TEXT_KEY), self::DEFAULT_TEXT);
    }

    public static function validity(): int
    {
        $validity = (int) \Configuration::get(self::VALIDITY_KEY);

        return in_array($validity, self::VALIDITIES, true) ? $validity : self::DEFAULT_VALIDITY;
    }

    /**
     * @param array<string, bool> $kinds
     */
    public static function save(array $kinds, string $background, string $text, int $validity): void
    {
        foreach (self::KEYS as $kind => $key) {
            \Configuration::updateValue($key, !empty($kinds[$kind]) ? '1' : '0');
        }
        \Configuration::updateValue(self::BACKGROUND_KEY, self::color($background, self::DEFAULT_BACKGROUND));
        \Configuration::updateValue(self::TEXT_KEY, self::color($text, self::DEFAULT_TEXT));
        \Configuration::updateValue(self::VALIDITY_KEY, in_array($validity, self::VALIDITIES, true) ? $validity : self::DEFAULT_VALIDITY);
    }

    /**
     * Every kind on and the colors of the bell's badge, as a fresh install sets
     * them; a value the merchant already saved is kept.
     */
    public static function installDefaults(): bool
    {
        $result = true;
        $defaults = [
            self::BACKGROUND_KEY => self::DEFAULT_BACKGROUND,
            self::TEXT_KEY => self::DEFAULT_TEXT,
            self::VALIDITY_KEY => (string) self::DEFAULT_VALIDITY,
        ];
        foreach (self::KEYS as $key) {
            $defaults[$key] = '1';
        }
        foreach ($defaults as $key => $value) {
            if (\Configuration::get($key) === false) {
                $result = \Configuration::updateValue($key, $value) && $result;
            }
        }

        return $result;
    }

    public static function uninstall(): void
    {
        foreach (array_merge(array_values(self::KEYS), [self::BACKGROUND_KEY, self::TEXT_KEY, self::VALIDITY_KEY]) as $key) {
            \Configuration::deleteByName($key);
        }
    }

    private static function color(string $value, string $default): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? strtoupper($value) : $default;
    }
}

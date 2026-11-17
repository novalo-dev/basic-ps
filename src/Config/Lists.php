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

namespace PrestaShop\Module\PsEditionBasic\Config;

/**
 * The menu entries the simplified menu keeps and the sections the settings page
 * shows as cards, kept in JSON files at the module root.
 */
class Lists
{
    /** @var array<string, string[]> */
    private static $loaded = [];

    /**
     * @return string[]
     */
    public static function menu(): array
    {
        return self::load('menuWhiteList.json');
    }

    /**
     * @return string[]
     */
    public static function settings(): array
    {
        return self::load('settingsWhiteList.json');
    }

    /**
     * Module pages pinned to the very end of the menu, below the settings entry,
     * whatever the merchant chose on the settings page: the Wall of Fame.
     *
     * @return string[]
     */
    public static function menuBottom(): array
    {
        return self::load('menuBottomList.json');
    }

    /**
     * Every page the menu or the settings page already places: what is left are
     * the module pages the merchant chooses to show in the menu or not.
     *
     * @return string[]
     */
    public static function placed(): array
    {
        return array_merge(self::settings(), self::menu(), self::menuBottom());
    }

    /**
     * Sub-pages of the advanced parameters card left out by name.
     *
     * @return string[]
     */
    public static function settingsExcluded(): array
    {
        return self::load('settingsBlackList.json');
    }

    /**
     * @return string[]
     */
    private static function load(string $file): array
    {
        if (!isset(self::$loaded[$file])) {
            $list = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/' . $file), true);
            self::$loaded[$file] = is_array($list) ? $list : [];
        }

        return self::$loaded[$file];
    }
}

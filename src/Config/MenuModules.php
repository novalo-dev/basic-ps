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
 * The module pages the merchant switched on in the menu, by tab class name.
 * Nothing is stored at first: a module page stays on the settings page until
 * the merchant asks for it in the menu.
 */
class MenuModules
{
    public const CONFIGURATION_KEY = 'PS_EDITION_BASIC_MENU_MODULES';

    /**
     * @return string[]
     */
    public static function shown(): array
    {
        $list = json_decode((string) \Configuration::get(self::CONFIGURATION_KEY), true);

        return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }

    public static function set(string $className, bool $shown): void
    {
        $list = array_values(array_diff(self::shown(), [$className]));
        if ($shown) {
            $list[] = $className;
        }

        \Configuration::updateValue(self::CONFIGURATION_KEY, (string) json_encode($list));
    }
}

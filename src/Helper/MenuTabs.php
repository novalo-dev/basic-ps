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

namespace PrestaShop\Module\PsEditionBasic\Helper;

/**
 * Menu data shared by the simplified menu and the settings page, so both
 * show the same entries.
 */
class MenuTabs
{
    /**
     * The entries that modules add to the back office menu, as opposed to the core
     * sections the simplified menu and the settings page already list.
     *
     * @param array<int, array<string, mixed>> $navItems top level of the back office menu (SELL, IMPROVE...)
     * @param string[] $coreClassNames sections already shown elsewhere
     *
     * @return array<int, array<string, mixed>>
     */
    public static function moduleEntries(array $navItems, array $coreClassNames): array
    {
        $modules = [];

        foreach ($navItems as $category) {
            // A few modules add a single page at the very top of the menu, beside the
            // dashboard. Core categories with nothing visible in them look the same,
            // so only entries a module owns are taken.
            if (empty($category['sub_tabs'])) {
                if (!empty($category['module']) && self::isModuleEntry($category, $coreClassNames)) {
                    $modules[] = $category;
                }
                continue;
            }

            foreach ($category['sub_tabs'] as $tab) {
                if (self::isModuleEntry($tab, $coreClassNames)) {
                    $modules[] = $tab;
                }
            }
        }

        return $modules;
    }

    /**
     * @param array<string, mixed> $tab
     * @param string[] $coreClassNames
     */
    private static function isModuleEntry(array $tab, array $coreClassNames): bool
    {
        return !in_array($tab['class_name'], $coreClassNames, true)
            && (int) ($tab['active'] ?? 1) !== 0
            && $tab['class_name'] !== 'AdminPsEditionBasicSettingsController';
    }

    /**
     * Pages a module or PrestaShop hides (inactive tab) are left out, as the native
     * menu does: the Wishlist page under Modules, or Multistore on a single shop.
     *
     * @param array<int, array<string, mixed>> $tabs
     *
     * @return array<int, array<string, mixed>>
     */
    public static function withoutHiddenPages(array $tabs): array
    {
        foreach ($tabs as $key => $tab) {
            $tabs[$key]['sub_tabs'] = array_values(array_filter($tab['sub_tabs'] ?? [], function (array $page): bool {
                return (int) ($page['active'] ?? 1) !== 0;
            }));
        }

        return $tabs;
    }
}

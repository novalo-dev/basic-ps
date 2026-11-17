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
     * The back office menu as AdminController::getTabs() builds it on PrestaShop 8.
     * PrestaShop 9 builds its menu in a Twig component instead: its Symfony pages
     * have no getTabs() and assign no menu to Smarty, so the module reads it here,
     * with the same rules (access rights, enabled tabs, links, current page).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fromDatabase(\Context $context, int $parentId = 0, int $level = 0): array
    {
        $currentId = (int) \Tab::getCurrentParentId();
        $controllerName = (string) \Tools::getValue('controller');
        $result = [];

        foreach (\Tab::getTabs((int) $context->language->id, $parentId) as $tab) {
            // PrestaShop 8 also hides the legacy stock page while advanced stock
            // management is off; PrestaShop 9 no longer has that page.
            if (!\Tab::checkTabRights((int) $tab['id_tab'])
                || !$tab['enabled']
                || ($tab['class_name'] === 'AdminStock' && !\Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT'))
                || $tab['class_name'] === 'AdminCarrierWizard') {
                continue;
            }

            $tab['current'] = $currentId === (int) $tab['id_tab'] || ($controllerName !== '' && $tab['class_name'] === $controllerName);
            if ($tab['current']) {
                $tab['current_level'] = $level;
            }
            $tab['img'] = null;
            try {
                $tab['href'] = $context->link->getTabLink($tab);
            } catch (\Exception $e) {
                // A module tab whose route is gone: PrestaShop shows it without a link too.
                $tab['href'] = '';
            }
            $tab['sub_tabs'] = self::fromDatabase($context, (int) $tab['id_tab'], $level + 1);

            $subTabHref = '';
            foreach ($tab['sub_tabs'] as $subTab) {
                if ($subTab['active'] && $subTab['enabled']) {
                    $subTabHref = $subTab['href'];
                    break;
                }
            }
            if ($subTabHref !== '') {
                $tab['href'] = $subTabHref;
            } elseif ((int) $tab['id_parent'] === 0 && empty($tab['icon'])) {
                continue;
            } elseif (empty($tab['icon'])) {
                $tab['icon'] = 'extension';
            }

            foreach ($tab['sub_tabs'] as $subTab) {
                if ($subTab['current']) {
                    $tab['current'] = true;
                    $tab['current_level'] = $subTab['current_level'];
                    break;
                }
            }

            $result[] = $tab;
        }

        return $result;
    }

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
            && ($tab['module'] ?? '') !== 'ps_edition_basic';
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

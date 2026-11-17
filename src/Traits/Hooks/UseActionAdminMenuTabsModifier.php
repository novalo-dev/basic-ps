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

namespace PrestaShop\Module\PsEditionBasic\Traits\Hooks;

trait UseActionAdminMenuTabsModifier
{
    /**
     * Hook actionAdminMenuTabsModifier.
     * Modify tabs in BackOffice
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionAdminMenuTabsModifier(array &$params): void
    {
        if (!\Configuration::get('SMB_IS_NEW_MENU_ENABLED')) {
            return;
        }

        $this->context->smarty->assign('tabs_simplify', $this->simplifyTabs($params['tabs']));
    }

    /**
     * Shared with the navigation bar hook, which builds the menu itself on
     * PrestaShop versions that never call actionAdminMenuTabsModifier.
     */
    private function simplifyTabs(array $navItems): array
    {
        $modules = [];
        if (in_array('SELL', array_column($navItems, 'class_name'))) {
            $modules = \PrestaShop\Module\PsEditionBasic\Helper\MenuTabs::moduleEntries(
                $navItems,
                array_merge(\PrestaShop\Module\PsEditionBasic\Config\Lists::settings(), \PrestaShop\Module\PsEditionBasic\Config\Lists::menu())
            );
            $navItems = $this->flatSecondLevelRemovingFirstLevel($navItems);
        }

        // The settings entry closes the menu; the entries modules add sit just above
        // it, as their own group under a separator, instead of on the settings page.
        $main = [];
        $settings = [];
        foreach ($this->pickTabs($navItems, \PrestaShop\Module\PsEditionBasic\Config\Lists::menu()) as $tab) {
            if ($tab['class_name'] === 'AdminPsEditionBasicSettingsController') {
                $settings[] = $tab;
            } else {
                $main[] = $tab;
            }
        }

        foreach ($modules as $index => $tab) {
            $modules[$index]['divider_before'] = $index === 0;
            if (empty($tab['icon'])) {
                $modules[$index]['icon'] = 'extension';
            }
        }

        return \PrestaShop\Module\PsEditionBasic\Helper\MenuTabs::withoutHiddenPages(array_merge($main, $modules, $settings));
    }

    private function flatSecondLevelRemovingFirstLevel(array $tabs): array
    {
        $result = [];

        foreach ($tabs as $tab) {
            // A top-level entry without children, such as the dashboard, is a page in
            // its own right, not a category to unwrap: it stays where it is.
            if (empty($tab['sub_tabs'])) {
                $result[] = $tab;
                continue;
            }

            if (is_array($tab['sub_tabs'])) {
                foreach ($tab['sub_tabs'] as $subTab) {
                    array_push($result, $subTab);
                }
            }
        }

        return $result;
    }

    private function pickTabs(array $tabs, array $whiteListTabs): array
    {
        $result = [];
        foreach ($tabs as $tab) {
            if (in_array($tab['class_name'], $whiteListTabs)) {
                $result[] = $tab;
            }
        }

        return $result;
    }
}

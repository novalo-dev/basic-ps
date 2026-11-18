<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

declare(strict_types=1);

namespace PrestaShop\Module\PsEditionBasic\Install\Tabs;

class TabsInstaller
{
    /**
     * @var string
     */
    private $moduleName;

    public function __construct(string $moduleName)
    {
        $this->moduleName = $moduleName;
    }

    public function installTabs(): bool
    {
        $result = true;

        $tabs = Tabs::getTabs();

        foreach ($tabs as $tabItem) {
            $tabId = \Tab::getIdFromClassName($tabItem['class_name']);

            if (!$tabId) {
                $tabId = null;
            }

            $tab = new \Tab($tabId);
            // No parent: a page reached by its link only, outside the menu, as
            // PrestaShop files its own hidden pages.
            $tab->id_parent = $tabItem['parent'] ? \Tab::getIdFromClassName($tabItem['parent']) : -1;
            $tab->class_name = $tabItem['class_name'];
            $tab->route_name = $tabItem['route_name'];
            $tab->icon = $tabItem['icon'];
            // The module's own pages belong to the simplified menu: hidden with it.
            $tab->active = !empty($tabItem['follows_menu'])
                ? (bool) \Configuration::get('SMB_IS_NEW_MENU_ENABLED')
                : $tabItem['active'];
            $tab->enabled = $tabItem['enabled'];
            $tab->module = $this->moduleName;
            $tab->name = [];

            $names = $tabItem['name'];

            $languages = \Language::getLanguages(false);
            foreach ($languages as $language) {
                $tab->name[(int) $language['id_lang']] = $names[$language['iso_code']] ?? $names['en'];
            }

            $result = $result && $tab->save();

            $tab->updatePosition(false, $tabItem['position']);
        }

        // This build has no homepage: shops that ran an earlier one get their
        // dashboard and landing page back, and its homepage tabs are removed.
        $uninstall = new \PrestaShop\Module\PsEditionBasic\Actions\Uninstall($this->moduleName);
        $uninstall->restoreDashboard();
        $uninstall->removeHomepageTabs();

        return $result;
    }
}

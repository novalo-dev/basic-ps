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

namespace PrestaShop\Module\PsEditionBasic\Actions;

class Uninstall
{
    /**
     * @var string
     */
    private $moduleName;

    public function __construct(string $moduleName)
    {
        $this->moduleName = $moduleName;
    }

    public function run(): bool
    {
        $this->restoreDashboard();

        return $this->uninstallTabs();
    }

    /**
     * Earlier builds moved the native dashboard under the module's HOME tab and
     * made the module homepage every employee's landing page. Once that homepage
     * is gone or disabled, both point to a missing tab and the back office answers
     * with an error, so the dashboard goes back to the top of the menu and becomes
     * the landing page again.
     */
    public function restoreDashboard(): void
    {
        $dashboardId = (int) \Tab::getIdFromClassName('AdminDashboard');
        if (!$dashboardId) {
            return;
        }

        $dashboard = new \Tab($dashboardId);
        if ((int) $dashboard->id_parent !== 0) {
            $dashboard->id_parent = 0;
            $dashboard->save();
            $dashboard->updatePosition(false, 0);
        }

        $moduleHomeIds = array_filter([
            (int) \Tab::getIdFromClassName('HOME'),
            (int) \Tab::getIdFromClassName('AdminPsEditionBasicHomepageController'),
        ]);
        if ($moduleHomeIds) {
            \Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'employee` SET `default_tab` = ' . $dashboardId
                . ' WHERE `default_tab` IN (' . implode(',', $moduleHomeIds) . ')'
            );
        }
    }

    public function uninstallTabs(): bool
    {
        return $this->removeTabs(['HOME', 'AdminPsEditionBasicHomepageController', 'AdminPsEditionBasicSettingsController', 'AdminPsEditionBasicHelpController', 'AdminPsEditionBasicNotification']);
    }

    /**
     * Tabs left by earlier builds, which had a homepage, a call back, a PS Academy
     * and a cache endpoint registered as tabs.
     */
    public function removeHomepageTabs(): bool
    {
        return $this->removeTabs([
            'HOME',
            'AdminPsEditionBasicHomepageController',
            'AdminPsEditionBasicCallBackController',
            'AdminPsEditionBasicPsAcademyController',
        ]);
    }

    private function removeTabs(array $classNames): bool
    {
        $result = true;

        foreach ($classNames as $tabItemClassName) {
            $id_tab = (int) \Tab::getIdFromClassName($tabItemClassName);

            $tab = new \Tab($id_tab);
            if (\Validate::isLoadedObject($tab) && $tab->module === $this->moduleName) {
                $result = $result && $tab->delete();
            }
        }

        return $result;
    }
}

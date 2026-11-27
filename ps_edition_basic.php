<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * Modifications copyright since 2026 Novalo
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
 * @author    Novalo <ouanis.m@novalo.fr>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @copyright Since 2026 Novalo, for its modifications
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

use PrestaShop\Module\PsEditionBasic\Actions\Uninstall;
use PrestaShop\Module\PsEditionBasic\Install\Tabs\TabsInstaller;
use PrestaShop\Module\PsEditionBasic\Notification\Installer as NotificationsInstaller;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShop\PrestaShop\Core\Cache\Clearer\CacheClearerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class ps_edition_basic extends Module
{
    use PrestaShop\Module\PsEditionBasic\Traits\UseHooks;
    use PrestaShop\Module\PsEditionBasic\Traits\HaveConfigurationPage;

    private const PS_EDITION_BASIC_MODULE_TABS_LANG_UPDATE_REQUIRED = 'PS_EDITION_BASIC_MODULE_TABS_LANG_UPDATE_REQUIRED';

    public function __construct()
    {
        $this->name = 'ps_edition_basic';
        $this->version = '1.4.6';
        $this->tab = 'administration';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->trans('PrestaShop Edition Basic', [], 'Modules.Editionbasic.Admin');
        $this->description = $this->trans('PrestaShop Edition Basic.', [], 'Modules.Editionbasic.Admin');
        $this->bootstrap = true;
    }

    /**
     * A service from the full Symfony container. Named apart from PrestaShop's own
     * get(): its signature changed in PrestaShop 9 and, on legacy pages, it reads a
     * lighter container that lacks the services used here.
     *
     * @return object|null
     */
    public function getService(string $serviceName)
    {
        return SymfonyContainer::getInstance()->get($serviceName);
    }

    /**
     * This function is required in order to make module compatible with new translation system.
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    public function install(): bool
    {
        $filePath = $this->getModulePath('smb_edition');
        $moduleIsPresentOnDisk = file_exists($filePath);

        // Activate new menu on edition shop
        if ($moduleIsPresentOnDisk) {
            Configuration::updateValue('SMB_IS_NEW_MENU_ENABLED', true);
        } else {
            // Deactivate on basic shop
            Configuration::updateValue('SMB_IS_NEW_MENU_ENABLED', false);
        }

        return parent::install()
            && (new TabsInstaller($this->name))->installTabs()
            && NotificationsInstaller::install()
            && $this->registerHook($this->getHooksNames());
    }

    public function postInstall(): bool
    {
        // New routes and services only exist once the Symfony cache is rebuilt;
        // a shop that cannot clear it now still rebuilds it on its next clear.
        try {
            $cacheClearer = $this->getContainer()->get('prestashop.adapter.cache.clearer.symfony_cache_clearer');
            if ($cacheClearer instanceof CacheClearerInterface) {
                $cacheClearer->clear();
            }
        } catch (Exception $e) {
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function uninstall(): bool
    {
        Configuration::deleteByName('SMB_IS_NEW_MENU_ENABLED');
        Configuration::deleteByName(PrestaShop\Module\PsEditionBasic\Config\MenuModules::CONFIGURATION_KEY);
        NotificationsInstaller::uninstall();

        return parent::uninstall()
            && (new Uninstall($this->name))->run();
    }

    /**
     * @throws Exception
     */
    public function enable($force_all = false): bool
    {
        (new TabsInstaller($this->name))->installTabs();

        return parent::enable($force_all);
    }

    public function disable($force_all = false): bool
    {
        (new Uninstall($this->name))->restoreDashboard();

        return parent::disable($force_all);
    }

    /**
     * PrestaShop 8 differs on two points. It gets the look PrestaShop 9 has of
     * its own, from one stylesheet laid over its theme; and it draws its
     * breadcrumb from the menu, so the simplified menu brings its own there.
     */
    public function isPrestaShop8(): bool
    {
        return version_compare(_PS_VERSION_, '9.0.0', '<');
    }

    /**
     * The PrestaShop 9 zip leaves the stylesheet of the look out. Uploaded by
     * mistake over the module of a PrestaShop 8 shop, it then draws the menu
     * without the look, instead of asking for a file it does not have.
     */
    public function hasPrestaShop8Look(): bool
    {
        return $this->isPrestaShop8() && is_file(__DIR__ . '/views/css/back.css');
    }

    /**
     * URL of a file shipped in the module, versioned by its modification time:
     * the URLs never change otherwise, so browsers kept serving the previous
     * stylesheet after the module was updated.
     */
    public function getAssetUrl(string $relativePath): string
    {
        $mtime = @filemtime($this->getLocalPath() . $relativePath);

        return $this->getPathUri() . $relativePath . ($mtime ? '?v=' . $mtime : '');
    }

    private function getModulePath(string $name): string
    {
        return _PS_MODULE_DIR_ . $name . '/' . $name . '.php';
    }
}

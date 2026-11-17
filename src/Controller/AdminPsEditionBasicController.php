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

namespace PrestaShop\Module\PsEditionBasic\Controller;

use PrestaShop\Module\PsEditionBasic\Config\Lists;
use PrestaShop\Module\PsEditionBasic\Config\MenuModules;
use PrestaShop\Module\PsEditionBasic\Helper\MenuTabs;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminPsEditionBasicController extends FrameworkBundleAdminController
{
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
     * The legacy context and the translator, read without PrestaShop's getContext()
     * and trans(): both look in the controller's container, which on PrestaShop 8.2
     * is a service locator without them once the controller is a service.
     */
    protected function legacyContext(): \Context
    {
        return \Context::getContext();
    }

    protected function translate(string $id): string
    {
        return $this->getService('translator')->trans($id, [], 'Modules.Editionbasic.Admin');
    }

    protected function layoutTitle(): string
    {
        return $this->translate('Home');
    }

    public function filter_settings_tabs_recursive(array $tabs, array $whitelist, array $blacklist = []): array
    {
        $filtered = [];

        foreach ($tabs as $tab) {
            if (in_array($tab['class_name'], $whitelist)) {
                if ($tab['class_name'] === 'AdminAdvancedParameters') {
                    $tmp = [];
                    for ($i = 0; $i < count($tab['sub_tabs']); ++$i) {
                        if (!in_array($tab['sub_tabs'][$i]['wording'], $blacklist)) {
                            array_push($tmp, $tab['sub_tabs'][$i]);
                        }
                    }
                    $tab['sub_tabs'] = $tmp;
                }
                $filtered[] = $tab;
                continue;
            }

            $filtered = array_merge($filtered, $this->filter_settings_tabs_recursive($tab['sub_tabs'], $whitelist, $blacklist));
        }

        return array_values($this->reorganize_tabs($filtered, $whitelist));
    }

    public function reorganize_tabs(array $tabs, array $whitelist): array
    {
        $reorganized = [];

        foreach ($whitelist as $tabClassName) {
            foreach ($tabs as $item) {
                if ($item['class_name'] === $tabClassName) {
                    $reorganized[] = $item;
                    break;
                }
            }
        }

        return $reorganized;
    }

    private function buildAdminUrl(string $routeName): string
    {
        $router = $this->getService('router');
        $scheme = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'https://') === 0 ? 'https://' : 'http://';

        return $scheme . $_SERVER['HTTP_HOST'] . $router->generate($routeName);
    }

    /**
     * The module pages of the back office, as cards for the settings page, each
     * with whether the merchant switched it on in the menu.
     *
     * @param array<int, array<string, mixed>> $tabs the whole back office menu
     *
     * @return array<int, array<string, mixed>>
     */
    private function menuModuleCards(array $tabs): array
    {
        $shown = MenuModules::shown();
        $cards = [];
        foreach (MenuTabs::moduleEntries($tabs, Lists::placed()) as $tab) {
            // The module's own description says what the page is for, as the
            // configuration cards do; its name stands in when it has none.
            $description = '';
            $module = empty($tab['module']) ? null : \Module::getInstanceByName((string) $tab['module']);
            if ($module) {
                $description = (string) ($module->description ?: $module->displayName);
            }

            $cards[] = [
                'class_name' => (string) $tab['class_name'],
                'name' => (string) ($tab['name'] ?: $tab['class_name']),
                'href' => (string) $tab['href'],
                'icon' => (string) ($tab['icon'] ?: 'extension'),
                'description' => $description,
                'shown' => in_array($tab['class_name'], $shown, true),
            ];
        }

        return $cards;
    }

    /**
     * Saves the switch of one module card: the page shows in the menu or not.
     */
    public function toggleMenuModuleAction(Request $request): JsonResponse
    {
        if ((int) $this->legacyContext()->employee->id_profile !== 1) {
            return new JsonResponse(['saved' => false], Response::HTTP_FORBIDDEN);
        }

        $className = (string) $request->request->get('class_name', '');
        $shown = (bool) $request->request->get('shown', false);

        // Only a module page the settings page lists can be stored.
        $known = array_column($this->menuModuleCards(MenuTabs::fromDatabase($this->legacyContext())), 'class_name');
        if (!in_array($className, $known, true)) {
            return new JsonResponse(['saved' => false], Response::HTTP_BAD_REQUEST);
        }

        MenuModules::set($className, $shown);

        return new JsonResponse(['saved' => true, 'shown' => $shown]);
    }

    public function indexAction(): Response
    {
        if (intval($this->legacyContext()->employee->id_profile) !== 1) {
            \Tools::redirectAdmin($this->legacyContext()->link->getAdminLink('AdminDashboard'));
        }
        $modulePsEditionBasic = $this->getService('ps_edition_basic.module');

        $jsLink = $modulePsEditionBasic->getAssetUrl((string) $modulePsEditionBasic->getParameter('ps_edition_basic.edition_basic_homepage_js'));

        $tabs = MenuTabs::fromDatabase($this->legacyContext());

        /**
         * @var string|string[]
         */
        $shopCountry = $this->legacyContext()->country->iso_code;
        if (is_array($shopCountry)) { // Country might be an array
            $shopCountry = $shopCountry[array_key_first($shopCountry)] ?? '';
        }
        $shopCountry = strtolower($shopCountry);

        $cacheClearApiUrl = $this->buildAdminUrl('ps_edition_basic_clean_mbo_cache');

        return $this->render('@Modules/ps_edition_basic/views/templates/admin/homepage.html.twig', [
            'layoutTitle' => $this->layoutTitle(),
            'enableSidebar' => true,
            'jsLink' => $jsLink,
            'menuModules' => $this->menuModuleCards($tabs),
            'menuModulesTexts' => [
                'title' => $this->translate('Modules'),
                'description' => $this->translate('Choose the module pages shown in the menu.'),
                'switch' => $this->translate('Show in the menu'),
                'error' => $this->translate('The change could not be saved. Reload the page and try again.'),
            ],
            'menuModulesUrl' => $this->getService('router')->generate('ps_edition_basic_menu_module'),
            'menuModulesAssets' => [
                'css' => $modulePsEditionBasic->getAssetUrl('views/css/menu-modules.css'),
                'js' => $modulePsEditionBasic->getAssetUrl('views/js/menu-modules.js'),
            ],
            'jsContext' => json_encode([
                'CACHE_CLEAR_API_URL' => $cacheClearApiUrl,
                'moduleName' => $modulePsEditionBasic->displayName,
                'moduleSlug' => $modulePsEditionBasic->name,
                'moduleVersion' => $modulePsEditionBasic->version,
                // Updates never come from PrestaShop Addons: they would replace this build.
                'moduleIsUpdatable' => false,
                'moduleUpdateLink' => '',
                'shopName' => (string) \Configuration::get('PS_SHOP_NAME'),
                'isShopEnabled' => (bool) \Configuration::get('PS_SHOP_ENABLE'),
                'tabs' => MenuTabs::withoutHiddenPages(
                    $this->filter_settings_tabs_recursive($tabs, Lists::settings(), Lists::settingsExcluded())
                ),
                'locale' => $this->legacyContext()->language->iso_code,
                'shopCountry' => $shopCountry,
                // Module pages get their own section with a menu switch, drawn by
                // the template rather than by this app, which has no switch.
                'modulesTabs' => [],
            ]),
        ]);
    }
}

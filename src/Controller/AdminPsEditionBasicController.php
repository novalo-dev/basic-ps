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

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
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

    protected function layoutTitle(): string
    {
        return $this->trans('Home', 'Modules.Editionbasic.Admin');
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

    public function indexAction(): Response
    {
        if (intval($this->getContext()->employee->id_profile) !== 1) {
            \Tools::redirectAdmin($this->getContext()->link->getAdminLink('AdminDashboard'));
        }
        $modulePsEditionBasic = $this->getService('ps_edition_basic.module');

        $jsLink = $modulePsEditionBasic->getAssetUrl((string) $modulePsEditionBasic->getParameter('ps_edition_basic.edition_basic_homepage_js'));

        $reflection = new \ReflectionClass($this->getContext()->controller);
        $method = $reflection->getMethod('getTabs');
        $method->setAccessible(true);
        $tabs = $method->invoke($this->getContext()->controller);

        /**
         * @var string|string[]
         */
        $shopCountry = $this->getContext()->country->iso_code;
        if (is_array($shopCountry)) { // Country might be an array
            $shopCountry = $shopCountry[array_key_first($shopCountry)] ?? '';
        }
        $shopCountry = strtolower($shopCountry);

        $cacheClearApiUrl = $this->buildAdminUrl('ps_edition_basic_clean_mbo_cache');

        return $this->render('@Modules/ps_edition_basic/views/templates/admin/homepage.html.twig', [
            'layoutTitle' => $this->layoutTitle(),
            'enableSidebar' => true,
            'jsLink' => $jsLink,
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
                'tabs' => \PrestaShop\Module\PsEditionBasic\Helper\MenuTabs::withoutHiddenPages(
                    $this->filter_settings_tabs_recursive($tabs, \PrestaShop\Module\PsEditionBasic\Config\Lists::settings(), \PrestaShop\Module\PsEditionBasic\Config\Lists::settingsExcluded())
                ),
                'locale' => $this->getContext()->language->iso_code,
                'shopCountry' => $shopCountry,
                // Module entries are listed in the simplified menu instead.
                'modulesTabs' => [],
            ]),
        ]);
    }
}

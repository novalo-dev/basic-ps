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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminPsEditionBasicSettingsController extends AdminPsEditionBasicController
{
    protected function layoutTitle(): string
    {
        return $this->translate('Settings');
    }

    protected function template(): string
    {
        return 'settings.html.twig';
    }

    protected function pageVariables(): array
    {
        $tabs = MenuTabs::fromDatabase($this->legacyContext());

        return [
            'texts' => [
                'title' => $this->translate('Configuration'),
                'description' => $this->translate('Configure your shop in a few clicks'),
                'modulesTitle' => $this->translate('Modules'),
                'modulesDescription' => $this->translate('Configure your modules in a few clicks'),
                'switch' => $this->translate('Show in the menu'),
                'switchHelp' => $this->translate('Adds the pages of this module to the menu on the left. Its own pages stay reachable here either way.'),
                'error' => $this->translate('The change could not be saved. Reload the page and try again.'),
                // PrestaShop's own words for its switches, in every language it ships.
                'yes' => $this->getService('translator')->trans('Yes', [], 'Admin.Global'),
                'no' => $this->getService('translator')->trans('No', [], 'Admin.Global'),
            ],
            'cards' => $this->settingsCards($tabs),
            'menuModules' => $this->menuModuleCards($tabs),
            'menuModulesUrl' => $this->getService('router')->generate('ps_edition_basic_menu_module'),
        ];
    }

    /**
     * What each configuration card says and shows, as on the original page.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function cardTexts(): array
    {
        return [
            'AdminParentModulesSf' => [$this->translate('Install, configure and update your modules.'), 'extension'],
            'AdminParentThemes' => [$this->translate('Customize the theme, logo, pages and positions of your store.'), 'desktop_mac'],
            'AdminParentShipping' => [$this->translate('Choose or combine the shipping methods that suit you best.'), 'local_shipping'],
            'AdminParentPayment' => [$this->translate('Choose the payment methods available with one of the payment providers approved by PrestaShop.'), 'credit_card'],
            'AdminInternational' => [$this->translate('Display the different countries, languages, and currencies you want to activate on your store.'), 'language'],
            'ShopParameters' => [$this->translate('Configure all the technical settings to make your shop work.'), 'stars'],
            'AdminAdvancedParameters' => [$this->translate('Configure the operation of your shop.'), 'settings_ethernet'],
        ];
    }

    /**
     * The configuration sections, each a card opening on its pages.
     *
     * @param array<int, array<string, mixed>> $tabs the whole back office menu
     *
     * @return array<int, array<string, mixed>>
     */
    private function settingsCards(array $tabs): array
    {
        $texts = $this->cardTexts();
        $cards = [];
        $sections = MenuTabs::withoutHiddenPages($this->filterSettingsTabs($tabs, Lists::settings(), Lists::settingsExcluded()));
        foreach ($sections as $tab) {
            $className = (string) $tab['class_name'];
            $cards[] = [
                'class_name' => $className,
                'name' => (string) $tab['name'],
                'href' => (string) $tab['href'],
                'icon' => $texts[$className][1] ?? 'extension',
                'description' => $texts[$className][0] ?? '',
                'links' => self::links($tab['sub_tabs'] ?? []),
            ];
        }

        // Two sections span two columns, in the list's own order: the second card,
        // at the right of the first row, and the one before last, at the left of
        // the last row. With the seven sections of a shop, every row is full.
        $count = count($cards);
        foreach ($cards as $index => $card) {
            $cards[$index]['wide'] = $count >= 4 && ($index === 1 || $index === $count - 2);
        }

        return $cards;
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     *
     * @return array<int, array{name: string, href: string}>
     */
    private static function links(array $pages): array
    {
        $links = [];
        foreach ($pages as $page) {
            if ((int) ($page['active'] ?? 1) !== 0 && !empty($page['href'])) {
                $links[] = ['name' => (string) $page['name'], 'href' => (string) $page['href']];
            }
        }

        return $links;
    }

    /**
     * The sections the settings page lists, found at any depth of the menu, in the
     * order of the list.
     *
     * @param array<int, array<string, mixed>> $tabs
     * @param string[] $whitelist
     * @param string[] $blacklist pages left out of the advanced parameters
     *
     * @return array<int, array<string, mixed>>
     */
    private function filterSettingsTabs(array $tabs, array $whitelist, array $blacklist = []): array
    {
        $filtered = [];

        foreach ($tabs as $tab) {
            if (in_array($tab['class_name'], $whitelist, true)) {
                if ($tab['class_name'] === 'AdminAdvancedParameters') {
                    $tab['sub_tabs'] = array_values(array_filter($tab['sub_tabs'], function (array $page) use ($blacklist): bool {
                        return !in_array($page['wording'], $blacklist, true);
                    }));
                }
                $filtered[] = $tab;
                continue;
            }

            $filtered = array_merge($filtered, $this->filterSettingsTabs($tab['sub_tabs'], $whitelist, $blacklist));
        }

        $ordered = [];
        foreach ($whitelist as $className) {
            foreach ($filtered as $tab) {
                if ($tab['class_name'] === $className) {
                    $ordered[] = $tab;
                    break;
                }
            }
        }

        return $ordered;
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
                'links' => self::links($tab['sub_tabs'] ?? []),
            ];
        }

        return $cards;
    }

    /**
     * Saves the switch of one module card: the page shows in the menu or not.
     */
    public function toggleMenuModuleAction(Request $request): JsonResponse
    {
        // Changing the menu is editing the settings page: its Edit permission.
        if (!$this->granted('edit')) {
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
}

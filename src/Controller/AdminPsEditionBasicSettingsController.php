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
use PrestaShop\Module\PsEditionBasic\Notification\Notifier;
use PrestaShop\Module\PsEditionBasic\Notification\Settings;
use PrestaShop\Module\PsEditionBasic\Notification\Subscriptions;
use PrestaShop\Module\PsEditionBasic\Notification\WebPush;
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
            'notifications' => $this->notificationsVariables(),
        ];
    }

    /**
     * The Notifications section: this browser's switch, and the settings the
     * badge and the notifications follow.
     *
     * @return array<string, mixed>
     */
    private function notificationsVariables(): array
    {
        $router = $this->getService('router');
        $validities = [
            3600 => $this->translate('1 hour'),
            21600 => $this->translate('6 hours'),
            43200 => $this->translate('12 hours'),
            86400 => $this->translate('24 hours'),
            259200 => $this->translate('3 days'),
            604800 => $this->translate('7 days'),
        ];

        return [
            'kinds' => Settings::kinds(),
            'background' => Settings::background(),
            'text' => Settings::text(),
            'validity' => Settings::validity(),
            'validities' => array_intersect_key($validities, array_flip(Settings::VALIDITIES)),
            'canEdit' => $this->granted('edit'),
            'publicKey' => WebPush::publicKey(),
            'worker' => $this->assetUrl('views/js/notifications-sw.js'),
            'urls' => [
                'save' => $router->generate('ps_edition_basic_notifications_save'),
                'subscribe' => $router->generate('ps_edition_basic_push_subscribe'),
                'unsubscribe' => $router->generate('ps_edition_basic_push_unsubscribe'),
                'test' => $router->generate('ps_edition_basic_push_test'),
            ],
            'texts' => [
                'title' => $this->translate('Notifications'),
                'description' => $this->translate('Be told about new orders, customers and messages'),
                'browser' => $this->translate('Notifications on this browser'),
                'on' => $this->translate('This browser receives the notifications, even with the back office closed.'),
                'off' => $this->translate('Turn them on to be told about new orders, customers and messages, even with the back office closed.'),
                'denied' => $this->translate('Notifications are blocked for this site. Allow them in the browser settings, then reload the page.'),
                'unsupported' => $this->translate('This browser cannot receive notifications. On iPhone and iPad, add the back office to the home screen first.'),
                'insecure' => $this->translate('Notifications need the back office to open on a secure address (https).'),
                'noServer' => $this->translate('This server cannot send notifications: PHP needs its OpenSSL and cURL extensions.'),
                'turnOn' => $this->translate('Turn on'),
                'turnOff' => $this->translate('Turn off'),
                'test' => $this->translate('Send a test'),
                'testSent' => $this->translate('Test sent: it shows in a few seconds.'),
                'testFailed' => $this->translate('The test could not be sent. Turn the notifications off and on again.'),
                'settings' => $this->translate('Notification settings'),
                'settingsDescription' => $this->translate('What you are told about, the badge colors and how long a notification waits'),
                'order' => $this->translate('New orders'),
                'customer' => $this->translate('New customers'),
                'customer_message' => $this->translate('New messages'),
                'kindsHelp' => $this->translate('Counted on the icon of the browser tab, as the bell counts them, and sent as notifications.'),
                'background' => $this->translate('Notification background color'),
                'textColor' => $this->translate('Notification text color'),
                'validity' => $this->translate('Validity'),
                'validityHelp' => $this->translate('How long a notification waits for a browser that is offline. After that, it is dropped.'),
                'save' => $this->getService('translator')->trans('Save', [], 'Admin.Actions'),
                'saved' => $this->translate('The settings are saved.'),
            ],
        ];
    }

    public function saveNotificationsAction(Request $request): JsonResponse
    {
        if (!$this->granted('edit')) {
            return new JsonResponse(['saved' => false], Response::HTTP_FORBIDDEN);
        }
        $kinds = [];
        foreach (array_keys(Settings::kinds()) as $kind) {
            $kinds[$kind] = (string) $request->request->get($kind, '0') === '1';
        }
        Settings::save(
            $kinds,
            (string) $request->request->get('background', ''),
            (string) $request->request->get('text', ''),
            (int) $request->request->get('validity', 0)
        );

        return new JsonResponse(['saved' => true]);
    }

    /**
     * Keeps this browser for the employee, with the address of the back office
     * as the browser reaches it: the notifications are sent from requests of
     * the shop, which do not know where the back office is.
     */
    public function subscribeAction(Request $request): JsonResponse
    {
        if (!$this->granted('view')) {
            return new JsonResponse(['saved' => false], Response::HTTP_FORBIDDEN);
        }
        $subscription = $this->subscription($request);
        $keys = $subscription['keys'] ?? null;
        if (!isset($subscription['endpoint'], $keys['p256dh'], $keys['auth']) || !is_string($subscription['endpoint'])
            || !preg_match('#^https://#', $subscription['endpoint'])) {
            return new JsonResponse(['saved' => false], Response::HTTP_BAD_REQUEST);
        }
        $adminUrl = $request->getSchemeAndHttpHost() . $request->getBasePath() . '/index.php?controller=' . Notifier::CONTROLLER;
        $saved = Subscriptions::save(
            (int) $this->legacyContext()->employee->id,
            $subscription['endpoint'],
            (string) $keys['p256dh'],
            (string) $keys['auth'],
            $adminUrl
        );

        return new JsonResponse(['saved' => $saved]);
    }

    public function unsubscribeAction(Request $request): JsonResponse
    {
        $subscription = $this->subscription($request);
        if (isset($subscription['endpoint']) && is_string($subscription['endpoint'])) {
            Subscriptions::delete($subscription['endpoint'], (int) $this->legacyContext()->employee->id);
        }

        return new JsonResponse(['saved' => true]);
    }

    public function testNotificationAction(Request $request): JsonResponse
    {
        $subscription = $this->subscription($request);
        $employee = $this->legacyContext()->employee;
        $row = isset($subscription['endpoint']) && is_string($subscription['endpoint'])
            ? Subscriptions::find($subscription['endpoint'], (int) $employee->id)
            : null;
        if ($row === null) {
            return new JsonResponse(['sent' => false], Response::HTTP_NOT_FOUND);
        }
        $row['id_lang'] = (int) $employee->id_lang;

        return new JsonResponse(['sent' => Notifier::test($row)]);
    }

    /**
     * The browser's subscription, as PushSubscription.toJSON() writes it.
     *
     * @return array<string, mixed>
     */
    private function subscription(Request $request): array
    {
        $data = json_decode((string) $request->getContent(), true);

        return is_array($data) ? $data : [];
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

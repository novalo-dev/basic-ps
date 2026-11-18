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

namespace PrestaShop\Module\PsEditionBasic\Install\Tabs;

class Tabs
{
    /**
     * Get module tabs information for installation
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getTabs(): array
    {
        return [
            [
                'class_name' => 'AdminPsEditionBasicSettingsController',
                'route_name' => 'ps_edition_basic_settings',
                'name' => [
                    'en' => 'Settings',
                    'fr' => 'Paramètres',
                    'it' => 'Impostazioni',
                    'es' => 'Ajustes',
                ],
                'icon' => 'settings',
                'parent' => 'CONFIGURE',
                'position' => 0,
                'active' => true,
                'enabled' => true,
                'wording' => '',
                'wording_domain' => 'Modules.Editionbasic.Admin',
                'follows_menu' => true,
            ],
            [
                'class_name' => 'AdminPsEditionBasicHelpController',
                'route_name' => 'ps_edition_basic_help',
                'name' => [
                    'en' => 'Help Center',
                    'fr' => 'Centre d\'aide',
                    'it' => 'Centro assistenza',
                    'es' => 'Centro de ayuda',
                ],
                'icon' => 'support_agent',
                'parent' => 'CONFIGURE',
                'position' => 1,
                'active' => true,
                'enabled' => true,
                'wording' => '',
                'wording_domain' => 'Modules.Editionbasic.Admin',
                'follows_menu' => true,
            ],
            [
                // Where a browser notification leads; never in the menu.
                'class_name' => 'AdminPsEditionBasicNotification',
                'route_name' => '',
                'name' => [
                    'en' => 'Notifications',
                    'fr' => 'Notifications',
                    'it' => 'Notifiche',
                    'es' => 'Notificaciones',
                ],
                'icon' => '',
                'parent' => '',
                'position' => 0,
                'active' => false,
                'enabled' => true,
                'wording' => '',
                'wording_domain' => 'Modules.Editionbasic.Admin',
                'follows_menu' => false,
            ],
        ];
    }
}

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

use PrestaShop\Module\PsEditionBasic\Notification\Settings;

trait UseDisplayBackOfficeHeader
{
    public function hookDisplayBackOfficeHeader(): string
    {
        $output = $this->faviconBadge();

        // The simplified menu and breadcrumb are drawn by the server; this script
        // only puts them in place and opens their sections.
        if (!\Configuration::get('SMB_IS_NEW_MENU_ENABLED')) {
            return $output;
        }

        $this->context->controller->addJS($this->getAssetUrl('views/js/menu.js'));
        $this->context->controller->addCSS($this->getAssetUrl('views/css/menu.css'));

        return $output;
    }

    /**
     * The count of the bell, drawn on the browser tab's icon for the kinds the
     * merchant chose: the script reads the bell's own answers and sends no
     * request of its own.
     */
    private function faviconBadge(): string
    {
        $kinds = array_keys(array_filter(Settings::kinds()));
        if (!$kinds || !$this->context->controller) {
            return '';
        }
        $this->context->controller->addJS($this->getAssetUrl('views/js/notifications.js'));

        return '<script type="application/json" id="peb-favicon-badge">' . json_encode([
            'kinds' => $kinds,
            'background' => Settings::background(),
            'text' => Settings::text(),
        ], JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
}

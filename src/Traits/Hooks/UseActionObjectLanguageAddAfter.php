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

namespace PrestaShop\Module\PsEditionBasic\Traits\Hooks;

use PrestaShop\PrestaShop\Adapter\Configuration;

trait UseActionObjectLanguageAddAfter
{
    /**
     * @return void
     */
    public function hookActionObjectLanguageAddAfter(): void
    {
        // If new country is added we want to update module tabs.
        /** @var Configuration $configuration */
        $configuration = $this->getService('prestashop.adapter.legacy.configuration');
        $configuration->set(self::PS_EDITION_BASIC_MODULE_TABS_LANG_UPDATE_REQUIRED, '1');
    }
}

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
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 1.0.19 serves the PrestaShop 9 back office stylesheets. Symfony pages are rewritten
 * by a new event subscriber, which only exists once the container is rebuilt, and the
 * settings page tab now follows the simplified menu switch.
 */
function upgrade_module_1_0_19(ps_edition_basic $module): bool
{
    $result = (new PrestaShop\Module\PsEditionBasic\Install\Tabs\TabsInstaller($module->name))->installTabs();
    Tools::clearSf2Cache();

    return $result;
}

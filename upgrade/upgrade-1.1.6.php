<?php
/**
 * Copyright since 2026 Novalo
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to ouanis.m@novalo.fr so we can send you a copy immediately.
 *
 * @author    Novalo <ouanis.m@novalo.fr>
 * @copyright Since 2026 Novalo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The Help Center is a new page of the module: its tab is created, hidden or
 * shown with the simplified menu, and its route needs the Symfony cache rebuilt.
 */
function upgrade_module_1_1_6(ps_edition_basic $module): bool
{
    $result = (new PrestaShop\Module\PsEditionBasic\Install\Tabs\TabsInstaller($module->name))->installTabs();
    Tools::clearSf2Cache();

    return $result;
}

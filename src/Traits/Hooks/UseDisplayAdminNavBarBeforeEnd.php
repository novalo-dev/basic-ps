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

trait UseDisplayAdminNavBarBeforeEnd
{
    public function hookDisplayAdminNavBarBeforeEnd(): string
    {
        if (!\Configuration::get('SMB_IS_NEW_MENU_ENABLED')) {
            return '';
        }

        // PrestaShop 8.0 never calls actionAdminMenuTabsModifier, so the simplified
        // menu is built here from the tabs the back office has already assigned. The
        // PrestaShop 9 Twig layout assigns none, so the menu is read from the database.
        $smarty = $this->context->smarty;
        if (!is_array($smarty->getTemplateVars('tabs_simplify'))) {
            $tabs = $smarty->getTemplateVars('tabs');
            if (!is_array($tabs)) {
                $tabs = \PrestaShop\Module\PsEditionBasic\Helper\MenuTabs::fromDatabase($this->context);
            }
            $smarty->assign('tabs_simplify', $this->simplifyTabs($tabs));
        }

        // Assigned by the PrestaShop 8 header only; PrestaShop 9 keeps the same cookie.
        if ($smarty->getTemplateVars('collapse_menu') === null) {
            $smarty->assign('collapse_menu', (int) ($this->context->cookie->collapse_menu ?? 0));
        }
        if ($smarty->getTemplateVars('toggle_navigation_url') === null) {
            $smarty->assign('toggle_navigation_url', $this->context->link->getAdminLink('AdminEmployees', true, [], ['action' => 'toggleMenu']));
        }

        return $this->display($this->_path, 'views/templates/admin/side-bar.tpl');
    }
}

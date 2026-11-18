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

namespace PrestaShop\Module\PsEditionBasic\Helper;

/**
 * Whether a profile holds a right on a back office page, as set in
 * Team > Permissions. Shared by the module's pages and by the notifications,
 * which only reach an employee allowed to open what they announce.
 */
class EmployeeAccess
{
    /**
     * @param string $className the tab of the page
     * @param string $right view, add, edit or delete
     */
    public static function granted(int $idProfile, string $className, string $right): bool
    {
        // As Tab::checkTabRights(): the SuperAdmin profile holds every right.
        if ($idProfile === (int) _PS_ADMIN_PROFILE_) {
            return true;
        }
        $idTab = (int) \Tab::getIdFromClassName($className);
        if (!$idTab) {
            return false;
        }
        $accesses = \Profile::getProfileAccesses($idProfile);

        return isset($accesses[$idTab][$right]) && (string) $accesses[$idTab][$right] === '1';
    }
}

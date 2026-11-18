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

use PrestaShop\Module\PsEditionBasic\Helper\EmployeeAccess;
use PrestaShop\Module\PsEditionBasic\Notification\Settings;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Where a notification leads: it opens the order, the customer or the message
 * thread it announces. A notification can be clicked hours after it was sent,
 * when a link to those pages would carry an expired token; this page's token
 * only depends on the employee, and it sends on with fresh ones.
 */
class AdminPsEditionBasicNotificationController extends ModuleAdminController
{
    private const COOKIE = 'peb_notification';

    public function init()
    {
        // PrestaShop 8 sends a logged-out employee to the login page with the
        // name of this page and nothing else, and clears its own cookie on the
        // way: the target waits ten minutes in a cookie of its own.
        $employee = $this->context->employee;
        if (!$employee || !$employee->isLoggedBack()) {
            $target = $this->target();
            if ($target !== null) {
                $this->rememberTarget(implode(':', $target), time() + 600);
            }
        }

        parent::init();
    }

    /**
     * Anyone logged in may come here: the page it sends on to checks its own
     * permission, below, before the employee is sent there.
     */
    public function viewAccess($disable = false)
    {
        return true;
    }

    public function postProcess()
    {
        $target = $this->target();
        if ($target === null && isset($_COOKIE[self::COOKIE])) {
            $target = $this->parse((string) $_COOKIE[self::COOKIE]);
        }
        if (isset($_COOKIE[self::COOKIE])) {
            $this->rememberTarget('', time() - 3600);
        }

        $link = $this->context->link;
        if ($target === null || !EmployeeAccess::granted((int) $this->context->employee->id_profile, Settings::PAGES[$target[0]], 'view')) {
            Tools::redirectAdmin($link->getAdminLink('AdminDashboard'));
        }

        [$kind, $id] = $target;
        switch ($kind) {
            case Settings::ORDER:
                $url = $link->getAdminLink('AdminOrders', true, [], ['id_order' => $id, 'vieworder' => 1]);
                break;
            case Settings::CUSTOMER:
                $url = $link->getAdminLink('AdminCustomers', true, [], ['id_customer' => $id, 'viewcustomer' => 1]);
                break;
            default:
                $url = $link->getAdminLink('AdminCustomerThreads', true, [], ['id_customer_thread' => $id, 'viewcustomer_thread' => 1]);
        }
        Tools::redirectAdmin($url);
    }

    /**
     * Read back by this page only: the cookie holds a kind and a number, both
     * checked again before use.
     */
    private function rememberTarget(string $value, int $expires): void
    {
        $path = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = substr($path, 0, (int) strrpos($path, '/') + 1) ?: '/';
        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => $path,
            'secure' => Tools::usingSecureMode(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function target(): ?array
    {
        return $this->parse(Tools::getValue('kind', '') . ':' . (int) Tools::getValue('id'));
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function parse(string $value): ?array
    {
        $parts = explode(':', $value, 2);
        if (count($parts) !== 2 || !isset(Settings::PAGES[$parts[0]]) || (int) $parts[1] <= 0) {
            return null;
        }

        return [$parts[0], (int) $parts[1]];
    }
}

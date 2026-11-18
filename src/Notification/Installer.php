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

declare(strict_types=1);

namespace PrestaShop\Module\PsEditionBasic\Notification;

/**
 * What the notifications need in a shop, for a fresh install and for the
 * upgrade of a shop already running the module alike.
 */
class Installer
{
    public static function install(): bool
    {
        return Subscriptions::install()
            && Settings::installDefaults()
            && WebPush::installKeys();
    }

    public static function uninstall(): bool
    {
        Settings::uninstall();
        WebPush::uninstallKeys();

        return Subscriptions::uninstall();
    }
}

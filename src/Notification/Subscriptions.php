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
 * The browsers that asked for notifications, one row each, with the employee
 * who switched them on. The address of the back office is kept with them: the
 * notifications are sent from requests of the shop, which do not know it.
 */
class Subscriptions
{
    public const TABLE = 'edition_basic_push';

    public static function install(): bool
    {
        return \Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
                `id_push` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_employee` INT UNSIGNED NOT NULL,
                `endpoint_hash` CHAR(64) NOT NULL,
                `endpoint` TEXT NOT NULL,
                `p256dh` VARCHAR(255) NOT NULL,
                `auth` VARCHAR(64) NOT NULL,
                `admin_url` VARCHAR(1024) NOT NULL,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_push`),
                UNIQUE KEY `endpoint_hash` (`endpoint_hash`),
                KEY `id_employee` (`id_employee`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
    }

    public static function uninstall(): bool
    {
        return \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`');
    }

    /**
     * A browser belongs to the last employee who switched it on: two employees
     * sharing a computer do not both get the notifications of one profile.
     */
    public static function save(int $idEmployee, string $endpoint, string $p256dh, string $auth, string $adminUrl): bool
    {
        $db = \Db::getInstance();
        $hash = hash('sha256', $endpoint);
        $db->delete(self::TABLE, '`endpoint_hash` = \'' . pSQL($hash) . '\'');

        return $db->insert(self::TABLE, [
            'id_employee' => $idEmployee,
            'endpoint_hash' => pSQL($hash),
            'endpoint' => pSQL($endpoint),
            'p256dh' => pSQL($p256dh),
            'auth' => pSQL($auth),
            'admin_url' => pSQL($adminUrl),
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function delete(string $endpoint, ?int $idEmployee = null): bool
    {
        return \Db::getInstance()->delete(
            self::TABLE,
            '`endpoint_hash` = \'' . pSQL(hash('sha256', $endpoint)) . '\''
            . ($idEmployee === null ? '' : ' AND `id_employee` = ' . $idEmployee)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $endpoint, int $idEmployee): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '`'
            . ' WHERE `endpoint_hash` = \'' . pSQL(hash('sha256', $endpoint)) . '\' AND `id_employee` = ' . $idEmployee
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Every browser of an active employee, with what the notifications need of
     * that employee: the profile for the permissions, the language to write in.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function ofActiveEmployees(): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT p.*, e.`id_profile`, e.`id_lang` FROM `' . _DB_PREFIX_ . self::TABLE . '` p'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'employee` e ON e.`id_employee` = p.`id_employee` AND e.`active` = 1'
        );

        return is_array($rows) ? $rows : [];
    }
}

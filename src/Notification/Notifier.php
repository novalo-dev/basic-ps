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

namespace PrestaShop\Module\PsEditionBasic\Notification;

use PrestaShop\Module\PsEditionBasic\Helper\EmployeeAccess;

/**
 * Collects what the hooks report during a request and sends the notifications
 * once the request is over. A hook only adds to a list in memory: the order,
 * the account or the message is saved without waiting for any push service.
 */
class Notifier
{
    /** The page a notification opens, which finds the order, customer or thread. */
    public const CONTROLLER = 'AdminPsEditionBasicNotification';

    /** Keeps a message short on a lock screen; the thread holds the rest. */
    private const EXCERPT = 120;

    /** @var array<string, array<string, mixed>> */
    private static $events = [];

    /** @var bool */
    private static $registered = false;

    /** @var array<int, string> */
    private static $locales = [];

    /** @var array<string, \PrestaShopBundle\Translation\TranslatorComponent> */
    private static $translators = [];

    public static function orderPlaced(\Order $order): void
    {
        if (!Settings::enabled(Settings::ORDER) || !\Validate::isLoadedObject($order)) {
            return;
        }
        // An order split between carriers is one purchase: one notification,
        // with the total of its parts.
        $key = 'order-' . $order->reference;
        if (isset(self::$events[$key])) {
            self::$events[$key]['total'] += (float) $order->total_paid;

            return;
        }
        $customer = new \Customer((int) $order->id_customer);
        self::add($key, [
            'kind' => Settings::ORDER,
            'id' => (int) $order->id,
            'id_shop' => (int) $order->id_shop,
            'reference' => (string) $order->reference,
            'name' => trim($customer->firstname . ' ' . $customer->lastname),
            'total' => (float) $order->total_paid,
            'id_currency' => (int) $order->id_currency,
        ]);
    }

    public static function customerAdded(\Customer $customer): void
    {
        if (!Settings::enabled(Settings::CUSTOMER) || !\Validate::isLoadedObject($customer)) {
            return;
        }
        self::add('customer-' . $customer->id, [
            'kind' => Settings::CUSTOMER,
            'id' => (int) $customer->id,
            'id_shop' => (int) $customer->id_shop,
            'name' => trim($customer->firstname . ' ' . $customer->lastname),
        ]);
    }

    public static function messageAdded(\CustomerMessage $message): void
    {
        // As the bell: only what customers write, not the replies of the team.
        if (!Settings::enabled(Settings::MESSAGE) || (int) $message->id_employee !== 0) {
            return;
        }
        $thread = new \CustomerThread((int) $message->id_customer_thread);
        if (!\Validate::isLoadedObject($thread)) {
            return;
        }
        $name = (string) $thread->email;
        if ((int) $thread->id_customer) {
            $customer = new \Customer((int) $thread->id_customer);
            $name = trim($customer->firstname . ' ' . $customer->lastname) ?: $name;
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $message->message)));
        if (\Tools::strlen($text) > self::EXCERPT) {
            $text = rtrim(\Tools::substr($text, 0, self::EXCERPT - 1)) . "\u{2026}";
        }
        self::add('thread-' . $thread->id, [
            'kind' => Settings::MESSAGE,
            'id' => (int) $thread->id,
            'id_shop' => (int) $thread->id_shop,
            'name' => $name,
            'message' => $text,
        ]);
    }

    /**
     * @param array<string, mixed> $event
     */
    private static function add(string $key, array $event): void
    {
        self::$events[$key] = $event;
        if (!self::$registered) {
            self::$registered = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    /**
     * Run once the request is over. With PHP-FPM or LiteSpeed the visitor's page
     * is handed over first, so the push services are never waited for; on other
     * servers the sending is capped by WebPush's timeout.
     */
    public static function flush(): void
    {
        $events = self::$events;
        self::$events = [];
        if (!$events) {
            return;
        }
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            ignore_user_abort(true);
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }
            self::send(array_values($events));
        } catch (\Throwable $e) {
            // A notification is never worth an error in the shop.
        }
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private static function send(array $events): void
    {
        $subscriptions = Subscriptions::ofActiveEmployees();
        $messages = [];
        foreach ($subscriptions as $subscription) {
            foreach ($events as $event) {
                if (!self::reaches($subscription, $event)) {
                    continue;
                }
                $messages[] = [
                    'endpoint' => (string) $subscription['endpoint'],
                    'p256dh' => (string) $subscription['p256dh'],
                    'auth' => (string) $subscription['auth'],
                    'payload' => (string) json_encode(self::payload($subscription, $event)),
                ];
            }
        }
        self::deliver($messages);
    }

    /**
     * The test notification of the settings page, to one browser.
     *
     * @param array<string, mixed> $subscription a row of Subscriptions, with id_lang
     */
    public static function test(array $subscription): bool
    {
        $locale = self::locale((int) $subscription['id_lang']);
        $translator = self::translator($locale);
        $payload = [
            'title' => $translator->trans('Notifications are on', [], 'Modules.Editionbasic.Admin', $locale),
            'body' => $translator->trans('This browser will tell you about new orders, customers and messages.', [], 'Modules.Editionbasic.Admin', $locale),
            'tag' => 'test',
            'icon' => self::icon(),
            'url' => '',
        ];
        $codes = self::deliver([[
            'endpoint' => (string) $subscription['endpoint'],
            'p256dh' => (string) $subscription['p256dh'],
            'auth' => (string) $subscription['auth'],
            'payload' => (string) json_encode($payload),
        ]]);

        return isset($codes[0]) && $codes[0] >= 200 && $codes[0] < 300;
    }

    /**
     * @param array<int, array{endpoint: string, p256dh: string, auth: string, payload: string}> $messages
     *
     * @return array<int, int>
     */
    private static function deliver(array $messages): array
    {
        $codes = WebPush::send($messages, Settings::validity());
        foreach ($codes as $index => $code) {
            // The push service no longer knows this browser: unsubscribed,
            // or its data cleared.
            if ($code === 404 || $code === 410) {
                Subscriptions::delete($messages[$index]['endpoint']);
            }
        }

        return $codes;
    }

    /**
     * Only an employee who may open the page, and, in a multistore, who works in
     * that shop.
     *
     * @param array<string, mixed> $subscription
     * @param array<string, mixed> $event
     */
    private static function reaches(array $subscription, array $event): bool
    {
        $idProfile = (int) $subscription['id_profile'];
        if (!EmployeeAccess::granted($idProfile, Settings::PAGES[$event['kind']], 'view')) {
            return false;
        }
        if ($idProfile === (int) _PS_ADMIN_PROFILE_ || !\Shop::isFeatureActive()) {
            return true;
        }

        return (bool) \Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'employee_shop`'
            . ' WHERE `id_employee` = ' . (int) $subscription['id_employee'] . ' AND `id_shop` = ' . (int) $event['id_shop']
        );
    }

    /**
     * What the browser shows, in the employee's language.
     *
     * @param array<string, mixed> $subscription
     * @param array<string, mixed> $event
     *
     * @return array<string, string>
     */
    private static function payload(array $subscription, array $event): array
    {
        $locale = self::locale((int) $subscription['id_lang']);
        $translator = self::translator($locale);
        switch ($event['kind']) {
            case Settings::ORDER:
                $title = $translator->trans('New order', [], 'Modules.Editionbasic.Admin', $locale);
                $body = $translator->trans('%reference% - %customer% - %total%', [
                    '%reference%' => $event['reference'],
                    '%customer%' => $event['name'],
                    '%total%' => self::price((float) $event['total'], (int) $event['id_currency']),
                ], 'Modules.Editionbasic.Admin', $locale);
                break;
            case Settings::CUSTOMER:
                $title = $translator->trans('New customer', [], 'Modules.Editionbasic.Admin', $locale);
                $body = (string) $event['name'];
                break;
            default:
                $title = $translator->trans('New message', [], 'Modules.Editionbasic.Admin', $locale);
                $body = $translator->trans('%customer%: %message%', [
                    '%customer%' => $event['name'],
                    '%message%' => $event['message'],
                ], 'Modules.Editionbasic.Admin', $locale);
        }

        $token = \Tools::getAdminToken(self::CONTROLLER . (int) \Tab::getIdFromClassName(self::CONTROLLER) . (int) $subscription['id_employee']);

        return [
            'title' => $title,
            'body' => $body,
            'tag' => $event['kind'] . '-' . $event['id'],
            'icon' => self::icon(),
            'url' => $subscription['admin_url'] . '&' . http_build_query([
                'token' => $token,
                'kind' => $event['kind'],
                'id' => $event['id'],
            ]),
        ];
    }

    private static function locale(int $idLang): string
    {
        if (!isset(self::$locales[$idLang])) {
            $language = new \Language($idLang);
            self::$locales[$idLang] = \Validate::isLoadedObject($language) && $language->locale
                ? (string) $language->locale
                : 'en-US';
        }

        return self::$locales[$idLang];
    }

    /**
     * A translator for the employee's language: the one of the request only
     * holds the visitor's, and in the shop it leaves out the back office texts.
     */
    private static function translator(string $locale): \PrestaShopBundle\Translation\TranslatorComponent
    {
        if (!isset(self::$translators[$locale])) {
            self::$translators[$locale] = \Context::getContext()->getTranslatorFromLocale($locale);
        }

        return self::$translators[$locale];
    }

    private static function price(float $amount, int $idCurrency): string
    {
        $iso = (string) \Currency::getIsoCodeById($idCurrency);
        try {
            return (string) \Tools::getContextLocale(\Context::getContext())->formatPrice($amount, $iso);
        } catch (\Throwable $e) {
            return number_format($amount, 2) . ' ' . $iso;
        }
    }

    /**
     * The shop's favicon, which a browser shows beside the notification: the
     * icon the employee knows the shop by, where a logo is cut down to a square.
     */
    private static function icon(): string
    {
        $favicon = (string) \Configuration::get('PS_FAVICON');

        return $favicon === '' ? '' : \Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . 'img/' . $favicon;
    }
}

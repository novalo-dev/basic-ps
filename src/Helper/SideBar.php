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
 * What the simplified menu and breadcrumb templates draw, worked out on the server
 * with the same rules PrestaShop's own menu component applied in the browser.
 */
class SideBar
{
    /**
     * @param array<int|string, array<string, mixed>> $tabs the simplified menu
     *
     * @return array<int, array<string, mixed>>
     */
    public static function items(array $tabs): array
    {
        $items = [];
        foreach ($tabs as $tab) {
            $links = [];
            foreach ((array) ($tab['sub_tabs'] ?? []) as $subTab) {
                $links[] = [
                    'class_name' => (string) $subTab['class_name'],
                    'label' => (string) $subTab['name'],
                    'href' => (string) ($subTab['href'] ?? ''),
                    'selected' => !empty($subTab['current']),
                ];
            }
            $dividerBefore = !empty($tab['divider_before']);

            $items[] = [
                'class_name' => (string) $tab['class_name'],
                'label' => (string) $tab['name'],
                'href' => (string) ($tab['href'] ?? ''),
                'icon' => (string) ($tab['icon'] ?? ''),
                // A section is highlighted by the page open in it, a single entry by itself.
                'selected' => $links === []
                    ? !empty($tab['current'])
                    : in_array(true, array_column($links, 'selected'), true),
                // The settings entry always stands apart from the pages above it.
                'divider' => $dividerBefore || ($links === [] && ($tab['icon'] ?? '') === 'settings'),
                'links' => $links,
            ];
        }

        return $items;
    }

    /**
     * The breadcrumb without the action (List, Edit...) and without a level repeated
     * twice in a row, as when a section and its first page share their name.
     *
     * @param array<string, mixed> $breadcrumb
     *
     * @return array<int, array{name: string, href: string}>
     */
    public static function breadcrumb(array $breadcrumb): array
    {
        unset($breadcrumb['action']);

        $items = [];
        foreach ($breadcrumb as $level) {
            if (!is_array($level) || empty($level['name'])) {
                continue;
            }
            $item = ['name' => (string) $level['name'], 'href' => (string) ($level['href'] ?? '')];
            $last = count($items) - 1;
            if ($last >= 0 && $items[$last]['name'] === $item['name']) {
                $items[$last] = $item;
                continue;
            }
            $items[] = $item;
        }

        return $items;
    }
}

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

namespace PrestaShop\Module\PsEditionBasic\Theme;

/**
 * Serves the PrestaShop 9.0 back office stylesheets in place of the PrestaShop 8 ones.
 *
 * The module ships the 9.0 build of both admin themes in views/ps9. Only a stylesheet
 * the module actually ships is swapped: anything else, such as a right-to-left variant,
 * stays the shop's own, so a page never ends up without its styles.
 */
class Ps9Theme
{
    private const THEME_CSS = '#/themes/(new-theme|default)/public/([a-z0-9_]+\.css)(?:\?[^"\'\s]*)?$#';

    private const THEME_CSS_IN_HTML = '#(["\'])([^"\'\s]*/themes/(?:new-theme|default)/public/[a-z0-9_]+\.css(?:\?[^"\'\s]*)?)\1#';

    /**
     * @var \ps_edition_basic
     */
    private $module;

    public function __construct(\ps_edition_basic $module)
    {
        $this->module = $module;
    }

    /**
     * Where the PS 9 version of a theme stylesheet is served, or null to keep the original.
     */
    public function replacementFor(string $url): ?string
    {
        if (!preg_match(self::THEME_CSS, $url, $match)) {
            return null;
        }

        $relativePath = 'views/ps9/' . $match[1] . '/' . $match[2];
        if (!is_file($this->module->getLocalPath() . $relativePath)) {
            return null;
        }

        return $this->module->getAssetUrl($relativePath);
    }

    /**
     * The controller's stylesheet list with the theme files swapped, order kept:
     * the theme has to load before module stylesheets for them to keep overriding it.
     *
     * @param array<string, string> $cssFiles URL => media
     *
     * @return array<string, string>
     */
    public function swapCssFiles(array $cssFiles): array
    {
        $swapped = [];
        foreach ($cssFiles as $url => $media) {
            $swapped[$this->replacementFor((string) $url) ?? $url] = $media;
        }

        return $swapped;
    }

    /**
     * Symfony pages also print page stylesheets (orders, product, stock...) straight
     * from their templates, outside the controller's list, so they are swapped in the page.
     */
    public function rewriteHtml(string $html): string
    {
        return (string) preg_replace_callback(self::THEME_CSS_IN_HTML, function (array $match): string {
            $replacement = $this->replacementFor(html_entity_decode($match[2], ENT_QUOTES));

            return $replacement === null
                ? $match[0]
                : $match[1] . htmlspecialchars($replacement, ENT_QUOTES) . $match[1];
        }, $html);
    }
}

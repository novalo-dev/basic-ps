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

namespace PrestaShop\Module\PsEditionBasic\EventSubscriber;

use PrestaShop\Module\PsEditionBasic\Theme\Ps9Theme;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Swaps the theme stylesheets that Symfony back office pages print from their own
 * templates, which the actionAdminControllerSetMedia hook never sees.
 */
class Ps9ThemeSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    /**
     * Typed loosely: the event class is FilterResponseEvent before Symfony 4.3
     * and ResponseEvent after, and the module runs on both PrestaShop 8 and 9.
     *
     * @param object $event
     */
    public function onKernelResponse($event): void
    {
        // PrestaShop 9 already serves these stylesheets itself.
        if (version_compare(_PS_VERSION_, '9.0.0', '>=')) {
            return;
        }

        $isMain = method_exists($event, 'isMainRequest') ? $event->isMainRequest() : $event->isMasterRequest();
        if (!$isMain) {
            return;
        }

        $response = $event->getResponse();
        $contentType = (string) $response->headers->get('Content-Type', 'text/html');
        if (stripos($contentType, 'text/html') === false) {
            return;
        }

        $content = $response->getContent();
        if (!is_string($content) || strpos($content, '/public/') === false) {
            return;
        }

        $module = \Module::getInstanceByName('ps_edition_basic');
        if (!$module instanceof \ps_edition_basic || !\Module::isEnabled('ps_edition_basic') || $module->keepsPrestaShop8Look()) {
            return;
        }

        $response->setContent((new Ps9Theme($module))->rewriteHtml($content));
    }
}

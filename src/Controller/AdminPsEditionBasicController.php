<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * Modifications copyright since 2026 Novalo
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @author    Novalo <ouanis.m@novalo.fr>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @copyright Since 2026 Novalo, for its modifications
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

declare(strict_types=1);

namespace PrestaShop\Module\PsEditionBasic\Controller;

use PrestaShop\Module\PsEditionBasic\Helper\EmployeeAccess;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Response;

/**
 * The pages of the module, drawn by Twig with the class names of PrestaShop's
 * design system; their rules ship in views/css/pages.css.
 */
abstract class AdminPsEditionBasicController extends FrameworkBundleAdminController
{
    /**
     * A service from the full Symfony container. Named apart from PrestaShop's own
     * get(): its signature changed in PrestaShop 9 and, on legacy pages, it reads a
     * lighter container that lacks the services used here.
     *
     * @return object|null
     */
    public function getService(string $serviceName)
    {
        return SymfonyContainer::getInstance()->get($serviceName);
    }

    /**
     * The legacy context and the translator, read without PrestaShop's getContext()
     * and trans(): both look in the controller's container, which on PrestaShop 8.2
     * is a service locator without them once the controller is a service.
     */
    protected function legacyContext(): \Context
    {
        return \Context::getContext();
    }

    protected function translate(string $id): string
    {
        return $this->getService('translator')->trans($id, [], 'Modules.Editionbasic.Admin');
    }

    abstract protected function layoutTitle(): string;

    abstract protected function template(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function pageVariables(): array;

    protected function assetUrl(string $path): string
    {
        return (string) $this->getService('ps_edition_basic.module')->getAssetUrl($path);
    }

    /**
     * Whether the employee's profile holds this right on the page's own tab, as set
     * in Team > Permissions: the page answers to PrestaShop's permissions, like
     * the menu that links to it, and not to a profile named in the code.
     *
     * @param string $right view, add, edit or delete
     */
    protected function granted(string $right): bool
    {
        // Each page is the tab of the same name, installed by Tabs::getTabs().
        return EmployeeAccess::granted(
            (int) $this->legacyContext()->employee->id_profile,
            (new \ReflectionClass($this))->getShortName(),
            $right
        );
    }

    public function indexAction(): Response
    {
        if (!$this->granted('view')) {
            \Tools::redirectAdmin($this->legacyContext()->link->getAdminLink('AdminDashboard'));
        }

        return $this->render('@Modules/ps_edition_basic/views/templates/admin/' . $this->template(), array_merge([
            'layoutTitle' => $this->layoutTitle(),
            'enableSidebar' => true,
            'pageAssets' => [
                'css' => $this->assetUrl('views/css/pages.css'),
                'js' => $this->assetUrl('views/js/pages.js'),
            ],
        ], $this->pageVariables()));
    }
}

<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
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
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

declare(strict_types=1);

namespace PrestaShop\Module\PsEditionBasic\Controller;

/**
 * The help blocks of PrestaShop's edition homepage: the maintenance state of the
 * shop, the help center and the support offers, in the employee's language.
 */
class AdminPsEditionBasicHelpController extends AdminPsEditionBasicController
{
    /**
     * Languages the help center and the support offers exist in, by PrestaShop
     * language code; any other language gets the English pages.
     */
    private const HELP_CENTER = ['fr' => 'fr', 'es' => 'es', 'it' => 'it'];
    private const CARE_CENTER = ['fr' => 'fr', 'es' => 'es', 'it' => 'it'];

    protected function layoutTitle(): string
    {
        return $this->translate('Help Center');
    }

    protected function template(): string
    {
        return 'help.html.twig';
    }

    protected function pageVariables(): array
    {
        $language = (string) $this->legacyContext()->language->iso_code;
        $care = 'https://care-center.prestashop.com/' . (self::CARE_CENTER[$language] ?? 'gb');

        return [
            'shopEnabled' => (bool) \Configuration::get('PS_SHOP_ENABLE'),
            'maintenanceUrl' => $this->legacyContext()->link->getAdminLink('AdminMaintenance'),
            'helpUrl' => 'https://help-center.prestashop.com/' . (self::HELP_CENTER[$language] ?? 'en'),
            'careUrl' => $care . '/54-support-offerings',
            // The Care Center's home: its contact form and support are one click away.
            'supportUrl' => $care,
            'images' => [
                'help' => $this->assetUrl('views/img/help-center.svg'),
                'offer' => $this->assetUrl('views/img/care-offer.png'),
            ],
            'texts' => [
                'onlineTitle' => $this->translate('Your shop is online.'),
                'onlineDescription' => $this->translate('Use maintenance mode when editing your store'),
                'onlineButton' => $this->translate('Enter maintenance mode'),
                'offlineTitle' => $this->translate('Your shop is offline.'),
                'offlineDescription' => $this->translate('Your visitors do not have access to your store. You can put it online from the maintenance page'),
                'offlineLink' => $this->translate('Maintenance.'),
                'offlineButton' => $this->translate('Go to maintenance page'),
                'helpTitle' => $this->translate('Need help ?'),
                'helpDescription' => $this->translate('A question, a doubt about a feature? Consult the documentation of our'),
                'helpCenter' => $this->translate('Help Center.'),
                'helpButton' => $this->translate('Access the documentation'),
                'supportButton' => $this->translate('Contact support'),
                'careTitle' => $this->translate('THE HELP YOU NEED, WHEN YOU NEED IT'),
                'careDescription' => $this->translate('Focus on your business. Let us take care of the technical aspects.'),
                'careButton' => $this->translate('Discover our offers'),
                'offerButton' => $this->translate('Explore'),
            ],
            'offers' => [
                [
                    'title' => $this->translate('PrestaShop Diagnostic Ticket'),
                    'description' => $this->translate('Benefit from a complete diagnosis of your site with the help of PrestaShop experts!'),
                    'url' => $care . '/support-offerings/668-prestashop-diagnostic-ticket.html',
                ],
                [
                    'title' => $this->translate('Quick-start help'),
                    'description' => $this->translate("You've downloaded PrestaShop software and need help to quickly install your online store on your server?"),
                    'url' => $care . '/support-offerings/671-aide-au-demarrage-rapide.html',
                ],
                [
                    'title' => $this->translate('Safety pack'),
                    'description' => $this->translate('To address the problem of security vulnerabilities in PrestaShop, we offer a Security Pack comprising an analysis and any associated security actions.'),
                    'url' => $care . '/support-offerings/661-pack-securite.html',
                ],
                [
                    'title' => $this->translate('SEO Pack'),
                    'description' => $this->translate('Our experts identify and analyze the crawling errors encountered by the Google Bot using the Google Search Console interface. Following the analysis, they provide you with the technical improvements you need to implement for your search engine optimization, and take corrective action.'),
                    'url' => $care . '/support-offerings/666-pack-seo.html',
                ],
            ],
        ];
    }
}

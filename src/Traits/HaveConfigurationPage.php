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

namespace PrestaShop\Module\PsEditionBasic\Traits;

trait HaveConfigurationPage
{
    /**
     * This method handles the module's configuration page
     *
     * @return string The page's HTML content
     */
    public function getContent(): string
    {
        $output = $this->handleSaveForm();

        return $output . $this->displayForm();
    }

    public function displayForm(): string
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans(
                        'Settings',
                        [],
                        'Admin.Global'
                    ),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans(
                            'Activate new menu and breadcrumb',
                            [],
                            'Modules.Editionbasic.Admin'
                        ),
                        'name' => 'SMB_IS_NEW_MENU_ENABLED',
                        'required' => true,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'SMB_IS_NEW_MENU_ENABLED_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'SMB_IS_NEW_MENU_ENABLED_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans(
                        'Save',
                        [],
                        'Admin.Actions'
                    ),
                ],
            ],
        ];

        $helper = new \HelperForm();
        $helper->show_toolbar = false;
        $lang = new \Language((int) \Configuration::get('PS_LANG_DEFAULT'));
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSmbConfiguration';
        $helper->currentIndex = $this->context->link->getAdminLink(
            'AdminModules',
            false
        ) .
            '&configure=' . $this->name .
            '&tab_module=' . $this->tab .
            '&module_name=' . $this->name;
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function getConfigFieldsValues(): array
    {
        return [
            'SMB_IS_NEW_MENU_ENABLED' => \Tools::getValue(
                'SMB_IS_NEW_MENU_ENABLED',
                \Configuration::get('SMB_IS_NEW_MENU_ENABLED')
            ),
        ];
    }

    private function handleSaveForm(): string
    {
        $output = '';

        // this part is executed only when the form is submitted
        if (\Tools::isSubmit('submitSmbConfiguration')) {
            // retrieve the value set by the user
            $configValue = (bool) \Tools::getValue('SMB_IS_NEW_MENU_ENABLED');
            \Configuration::updateValue('SMB_IS_NEW_MENU_ENABLED', $configValue);

            // The module's own pages (settings, help center) belong to the
            // simplified menu: without it, the native menu lists the same sections.
            foreach (\PrestaShop\Module\PsEditionBasic\Install\Tabs\Tabs::getTabs() as $tabItem) {
                $tab = empty($tabItem['follows_menu']) ? null : \Tab::getInstanceFromClassName($tabItem['class_name']);
                if ($tab && $tab->id) {
                    $tab->active = $configValue;
                    $tab->save();
                }
            }

            $output = $this->displayConfirmation($this->trans('Settings updated', [], 'Modules.Editionbasic.Admin'));
        }

        return $output;
    }
}

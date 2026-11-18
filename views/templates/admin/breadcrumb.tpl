{**
 * Copyright since 2007 PrestaShop SA and Contributors
 * Modifications copyright since 2026 Novalo
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
 * @author    Novalo <ouanis.m@novalo.fr>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @copyright Since 2026 Novalo, for its modifications
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *}
{*
 * The simplified breadcrumb, which menu.js puts in place of PrestaShop's own.
 *}
<div id="wrapper_breadcrumb">
    <div id="content_breadcrumb">
        <div class="puik-style peb-breadcrumb">
            <div class="flex items-center">
                {foreach $peb_breadcrumb as $item name=crumbs}
                    <div class="flex items-center">
                        <a {if $item.href}href="{$item.href|escape:'html':'UTF-8'}" {/if}target="_self" class="puik-link puik-link--md flex items-center mx-0">{$item.name|escape:'html':'UTF-8'}</a>
                        {if !$smarty.foreach.crumbs.last}<span class="mx-1"> &gt; </span>{/if}
                    </div>
                {/foreach}
            </div>
        </div>
    </div>

    <script>
        displayBreadcrumb();
    </script>
</div>

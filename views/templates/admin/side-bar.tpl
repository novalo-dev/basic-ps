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
 * The simplified menu. The class names are those of PrestaShop's design system,
 * whose rules for them ship in views/css/menu.css; menu.js moves the menu into
 * the navigation bar and opens its sections.
 *}
<nav id="wrapper_side_bar" class="{if $collapse_menu != 0}collapsed {/if}">
  <span class="menu-collapse" data-toggle-url="{$toggle_navigation_url|escape:'html':'UTF-8'}" style="display: none">
    <i class="material-icons rtl-flip">chevron_left</i>
    <i class="material-icons rtl-flip">chevron_left</i>
  </span>

  <div class="nav-bar-overflow">
    <div id="peb-side-bar" class="side-bar puik-style{if $collapse_menu != 0} peb-collapsed{/if}">
      <div class="text-[#363a41] pb-4 sm:pb-16">
        <div class="bg-[#F7F7F7] p-1.5 hidden lg:flex justify-end peb-side-bar__header">
          <button type="button" class="puik-button puik-button--text puik-button--sm puik-button--no-wrap transition-none p-1 h-7 flex items-center peb-side-bar__collapse" style="box-shadow: none;">
            <span class="puik-icon text-xl" style="font-size: 1rem;">{if $collapse_menu != 0}last_page{else}first_page{/if}</span>
          </button>
        </div>
        <div class="px-2 pt-4">
          {foreach $peb_menu as $item}
            {if $item.divider}<div class="h-[1px] rounded w-full my-2 bg-[#E4EBF2]"></div>{/if}
            {if $item.links}
              <div class="peb-side-bar__group">
                <section class="cursor-pointer {if $item.selected}text-[#2942CC] font-bold bg-[#F7F7F7]{else}text-[#1D1D1B]{/if}">
                  <div class="flex items-center p-2 hover:bg-[#EEEEEE] justify-between peb-side-bar__row" data-test="tab-{$item.class_name|escape:'html':'UTF-8'}" role="button" tabindex="0" aria-expanded="false">
                    <span class="puik-icon text-xl" style="font-size: 1rem;">{$item.icon|escape:'html':'UTF-8'}</span>
                    <span class="flex-1 ml-2 text-[14px] peb-side-bar__label">{$item.label|escape:'html':'UTF-8'}</span>
                    <span class="puik-icon text-xl font-normal peb-side-bar__arrow" style="font-size: 1rem;">expand_more</span>
                  </div>
                  <div class="overflow-hidden max-h-0 flex flex-col bg-[#F7F7F7] peb-side-bar__links">
                    {foreach $item.links as $link}
                      <a href="{$link.href|escape:'html':'UTF-8'}" data-test="subtab-{$link.class_name|escape:'html':'UTF-8'}" class="p-2 hover:no-underline hover:bg-[#EEEEEE] text-[14px] {if $link.selected}text-[#2942CC]{else}text-[#1D1D1B] font-normal{/if}">{$link.label|escape:'html':'UTF-8'}</a>
                    {/foreach}
                  </div>
                </section>
              </div>
            {else}
              <section class="cursor-pointer {if $item.selected}text-[#2942CC] font-bold bg-[#F7F7F7]{else}text-[#1D1D1B]{/if}">
                <a href="{$item.href|escape:'html':'UTF-8'}">
                  <div class="flex items-center p-2 hover:bg-[#EEEEEE] justify-between peb-side-bar__row" data-test="tab-{$item.class_name|escape:'html':'UTF-8'}">
                    <span class="puik-icon text-xl" style="font-size: 1rem;">{$item.icon|escape:'html':'UTF-8'}</span>
                    <span class="flex-1 ml-2 text-[14px] peb-side-bar__label">{$item.label|escape:'html':'UTF-8'}</span>
                  </div>
                </a>
              </section>
            {/if}
          {/foreach}
        </div>
      </div>
    </div>
  </div>
</nav>

<script>
    displayNavBar();
</script>

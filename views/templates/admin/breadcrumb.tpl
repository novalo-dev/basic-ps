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

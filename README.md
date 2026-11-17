# basic-ps

> **For experimentation only.** This is a personal rework of PrestaShop Edition Basic
> (`ps_edition_basic`). It is not supported, not published anywhere and comes with no
> guarantee. Do not install it on a shop you cannot restore.

## What it does

| | PrestaShop 8 | PrestaShop 9 |
|---|---|---|
| Back office look | PrestaShop 9 stylesheets, shipped in the module, replace the PrestaShop 8 ones | unchanged: PrestaShop 9 already has it |
| Simplified menu | optional, switched on in the module configuration | optional, switched on in the module configuration |
| Settings page | cards for the sections the simplified menu leaves out | same |

With the simplified menu on:

- the menu keeps the dashboard and the selling sections;
- entries added by modules are grouped above *Settings*, between two separators;
- *Modules*, *Design*, shipping, payment, international and both parameter sections
  become cards on the settings page.

Compared with the original module, it has no homepage, no setup guide, no PrestaShop
Account or Billing dependency, no analytics and nothing loaded from another site.
It never updates itself from PrestaShop Addons.

## Getting a build

There are no releases. Run **Actions → Build for Testing → Run workflow** and download
the artifact: a zip with `ps_edition_basic/` at its root, ready to upload in
*Modules → Module Manager → Upload a module*.

## Versions

Each change moves the last digit by one; a digit past 9 carries over:
`1.1.9` is followed by `1.2.0`, `1.9.9` by `2.0.0`.

## Licence

Academic Free License 3.0, like the original module by PrestaShop SA. The PrestaShop 9
stylesheets in `views/ps9` come from the PrestaShop 9.0.0 back office themes.

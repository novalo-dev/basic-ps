# basic-ps

> **For experimentation only.** This is a personal rework of PrestaShop Edition Basic
> (`ps_edition_basic`). It is not supported, not published anywhere and comes with no
> guarantee. Do not install it on a shop you cannot restore.

## What it does

| | PrestaShop 8 | PrestaShop 9 |
|---|---|---|
| Back office look | PrestaShop 9 stylesheets, shipped in the module, replace the PrestaShop 8 ones; the login page gets the PrestaShop 9 look from a stylesheet of its own | unchanged: PrestaShop 9 already has it |
| Simplified menu | optional, switched on in the module configuration | optional, switched on in the module configuration |
| Settings page | cards for the sections the simplified menu leaves out | same |
| Notifications | the bell's count on the browser tab's icon, and browser notifications for new orders, customers and messages | same |

With the simplified menu on:

- the menu keeps the dashboard, a *Help Center* just under it, and the selling sections;
  the Help Center gathers the links to PrestaShop's documentation and support offers,
  and takes the place of PrestaShop's *Care Center*, which needs a PrestaShop account;
- module pages are listed as cards on the settings page, each with a switch: a page
  switched on joins the menu, grouped above *Settings* between two separators (all
  are off at first);
- *Wall of Fame* stays at the very end of the menu, below *Settings*;
- *Modules*, *Design*, shipping, payment, international and both parameter sections
  become cards on the settings page.

### Notifications

- The count of the bell at the top of the back office shows on the icon of the
  browser tab, for the kinds chosen in *Settings → Notifications*, in the colors set
  there. The script reads the bell's own answers and sends no request of its own.
- Each employee switches notifications on per browser, on the settings page. They
  arrive with every back office tab closed, in the employee's language, and only for
  what the employee's profile may open; a click opens the order, the customer or the
  message thread. Chrome, Edge, Firefox and Safari 16.4 or later; on iPhone and iPad,
  once the back office is added to the home screen. The back office must open on https.
- They are sent with the Web Push protocol (VAPID keys created at install, aes128gcm
  encryption) by PHP's OpenSSL and cURL, with no library and no third-party account.
  The shop's hooks only note the order, account or message in memory; the sending
  happens once the visitor's page is handed over (PHP-FPM, LiteSpeed), or at the end
  of the request with a two-second limit on other servers.
- *Validity* is how long the browser's push service keeps a notification for a
  browser that is offline.

Compared with the original module, it has no homepage, no setup guide, no PrestaShop
Account or Billing dependency, no analytics and nothing loaded from another site;
the only requests it makes are the notifications, to the push service of each
browser an employee switched on.
It never updates itself from PrestaShop Addons. The menu, the breadcrumb, the settings
page and the Help Center are drawn by the server with the class names of PrestaShop's
design system, whose rules they use ship in `views/css`: no JavaScript app, only small
scripts that open sections, save the switches and settings, draw the tab's badge and
receive the notifications.

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

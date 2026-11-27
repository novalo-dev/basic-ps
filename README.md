# basic-ps

> **For experimentation only.** This is a personal rework of PrestaShop Edition Basic
> (`ps_edition_basic`). It is not supported, not published anywhere and comes with no
> guarantee. Do not install it on a shop you cannot restore.

## What it does

| | PrestaShop 8 | PrestaShop 9 |
|---|---|---|
| Back office look | the stylesheet PrestaShop wrote for PrestaShop 8 in PrestaShop Edition Basic, laid over the theme of the shop, which stays in place; the login page is unchanged | unchanged: PrestaShop 9 already has it |
| Simplified menu | optional, switched on in the module configuration | optional, switched on in the module configuration |
| Settings page | cards for the sections the simplified menu leaves out | same |
| Notifications | with the simplified menu: the bell's count on the browser tab's icon, and browser notifications for new orders, customers and messages | same |

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

They come with the simplified menu, like the settings page they are set on: with
the menu off, no badge is drawn and nothing is sent.

- The count of the bell at the top of the back office shows on the icon of the
  browser tab, for the kinds chosen in *Settings → Notifications*, in the colors set
  there. The script reads the bell's own answers and sends no request of its own.
- Each employee switches notifications on per browser, on the settings page. They
  arrive with every back office tab closed, in the employee's language, with the shop's favicon, and
  only for what the employee's profile may open; a click opens the order, the customer or the
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

There are no official releases. Each build comes as two zips, each with a folder
`ps_edition_basic/` at its root, ready to upload in *Modules → Module Manager →
Upload a module*:

- **ps8**: the whole module. It runs on PrestaShop 8 and 9.
- **ps9**: without what only PrestaShop 8 uses to look like PrestaShop 9 (the
  stylesheet of the look and its font), and PrestaShop refuses it below 9.0.

Two ways to get them:

- **Actions → Build for Testing → Run workflow**, then download the two artifacts;
- **Actions → Pre-release → Run workflow** publishes both as a GitHub pre-release,
  tagged with the version of `config.xml`; a version already published is refused.

## Versions

Each change moves the last digit by one; a digit past 9 carries over:
`1.1.9` is followed by `1.2.0`, `1.9.9` by `2.0.0`.

## Licence

Academic Free License 3.0, like the original module by PrestaShop SA; the text is in
`LICENSE.md`, shipped in the zip. `views/css/back.css` is the stylesheet of
PrestaShop Edition Basic (PrestaShopCorp/ps_edition_basic), without its import of
the font from Google Fonts: IBM Plex Sans ships in `views/fonts`, under the SIL
Open Font License 1.1.

Each file says whose it is. A file of the original module keeps PrestaShop SA's
notice; one changed here adds Novalo's beside it, for the changes only; one written
here carries Novalo's alone.

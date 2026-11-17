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

/*
 * The settings page and the help center: cards that open on their pages, the
 * switches that show a module page in the menu, and the support offers slider.
 */
(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    // A card with pages of its own opens on them instead of leaving the page.
    var header = event.target.closest('.peb-card__header[aria-expanded]');
    if (header) {
      event.preventDefault();
      var open = header.getAttribute('aria-expanded') !== 'true';
      var card = header.closest('.peb-card');
      header.setAttribute('aria-expanded', open ? 'true' : 'false');
      card.querySelector('.peb-card__pages').classList.toggle('max-h-0', !open);
      header.querySelector('.peb-card__arrow').textContent = open ? 'expand_less' : 'expand_more';
      return;
    }

    var button = event.target.closest('#peb-menu-modules .peb-switch');
    if (button) {
      toggleMenuModule(button);
      return;
    }

    var arrow = event.target.closest('.peb-care__prev, .peb-care__next');
    if (arrow) {
      var track = arrow.closest('.peb-care').querySelector('.peb-care__track');
      var step = track.firstElementChild ? track.firstElementChild.getBoundingClientRect().width + 30 : track.clientWidth;
      track.scrollBy({ left: arrow.classList.contains('peb-care__prev') ? -step : step, behavior: 'smooth' });
    }
  });

  function toggleMenuModule(button) {
    var section = document.getElementById('peb-menu-modules');
    var card = button.closest('[data-class-name]');
    var shown = button.getAttribute('aria-checked') !== 'true';
    var body = new FormData();
    body.append('class_name', card.getAttribute('data-class-name'));
    body.append('shown', shown ? '1' : '0');

    button.setAttribute('aria-busy', 'true');
    fetch(section.getAttribute('data-url'), { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (response) {
        return response.ok ? response.json() : { saved: false };
      })
      .then(function (result) {
        if (!result.saved) {
          throw new Error('not saved');
        }
        button.setAttribute('aria-checked', shown ? 'true' : 'false');
        // The menu is drawn by the server: the next page shows the change.
      })
      .catch(function () {
        window.alert(section.getAttribute('data-error'));
      })
      .finally(function () {
        button.removeAttribute('aria-busy');
      });
  }
})();

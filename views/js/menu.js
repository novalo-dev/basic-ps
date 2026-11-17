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
 * The simplified menu and breadcrumb are drawn by the server at the end of the
 * page; these functions move them in place of PrestaShop's own, right where the
 * templates call them, so the native ones are never painted.
 */

// eslint-disable-next-line no-unused-vars
function displayNavBar() {
  var wrapper = document.getElementById('wrapper_side_bar');
  var nav = wrapper && wrapper.closest('.nav-bar');
  if (!nav) {
    return;
  }
  if (wrapper.classList.contains('collapsed')) {
    nav.classList.add('collapsed');
  }
  nav.innerHTML = wrapper.innerHTML;

  var bar = document.getElementById('peb-side-bar');
  if (bar) {
    pebSideBar(bar, nav);
  }
}

// eslint-disable-next-line no-unused-vars
function displayBreadcrumb() {
  var content = document.getElementById('content_breadcrumb');
  if (!content) {
    return;
  }
  var targets = document.querySelectorAll('.header-toolbar ol.breadcrumb');
  if (!targets.length) {
    targets = document.querySelectorAll('ul.breadcrumb');
  }
  targets.forEach(function (target) {
    target.innerHTML = content.innerHTML;
  });
  document.getElementById('wrapper_breadcrumb').remove();
}

function pebSideBar(bar, nav) {
  var collapsed = null;

  function groups() {
    return bar.querySelectorAll('.peb-side-bar__group');
  }

  function setOpen(group, open) {
    group.classList.toggle('is-open', open);
    group.querySelector('.peb-side-bar__links').classList.toggle('max-h-0', !open);
    group.querySelector('.peb-side-bar__row').setAttribute('aria-expanded', open ? 'true' : 'false');
    group.querySelector('.peb-side-bar__arrow').textContent = open ? 'expand_less' : 'expand_more';
  }

  // One section open at a time; in the full menu, the one holding the current
  // page starts open.
  function closeAll() {
    groups().forEach(function (group) {
      setOpen(group, false);
    });
  }

  function openCurrent() {
    groups().forEach(function (group) {
      if (group.querySelector('.peb-side-bar__links .text-\\[\\#2942CC\\]')) {
        setOpen(group, true);
      }
    });
  }

  // Never folded while the phone menu is in use: there the menu is a full panel.
  function shouldCollapse() {
    var mobile = document.querySelector('.js-mobile-menu');
    if (!mobile || mobile.getBoundingClientRect().width > 0) {
      return false;
    }

    return document.body.classList.contains('page-sidebar-closed');
  }

  function setCollapsed(value) {
    if (value === collapsed) {
      return;
    }
    collapsed = value;
    bar.classList.toggle('peb-collapsed', value);
    bar.querySelector('.peb-side-bar__collapse .puik-icon').textContent = value ? 'last_page' : 'first_page';
    closeAll();
    if (!value) {
      openCurrent();
    }
  }

  bar.addEventListener('click', function (event) {
    if (event.target.closest('.peb-side-bar__collapse')) {
      setCollapsed(!collapsed);
      // PrestaShop's own toggle stores the choice and resizes the page.
      var toggle = nav.querySelector('.menu-collapse');
      if (toggle) {
        toggle.click();
      }
      nav.classList.toggle('collapsed');
      return;
    }

    var row = event.target.closest('.peb-side-bar__row[aria-expanded]');
    if (row) {
      var group = row.closest('.peb-side-bar__group');
      var open = !group.classList.contains('is-open');
      closeAll();
      setOpen(group, open);
    }
  });

  bar.addEventListener('keydown', function (event) {
    if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('.peb-side-bar__row[aria-expanded]')) {
      event.preventDefault();
      event.target.click();
    }
  });

  setCollapsed(shouldCollapse());
  if (window.ResizeObserver) {
    new ResizeObserver(function () {
      setCollapsed(shouldCollapse());
    }).observe(document.body);
  }
}

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
 * The settings page and the help center: the settings views that open in place,
 * the switches that show a module page in the menu, the notifications of this
 * browser and their settings, and the support offers slider.
 */
(function () {
  'use strict';

  var WAIT = 250;

  function settings() {
    return document.getElementById('peb-settings');
  }

  // One view shows at a time: the sections, the pages of one of them, the module
  // pages, or the waiting cards between two of them. The address keeps the view,
  // so a reload or the browser's back button lands on it again.
  function show(name, wait) {
    var root = settings();
    if (!root) {
      return;
    }
    var target = root.querySelector('[data-peb-view="' + name + '"]') ? name : 'home';
    var views = root.querySelectorAll('[data-peb-view]');
    var swap = function () {
      views.forEach(function (view) {
        view.hidden = view.getAttribute('data-peb-view') !== target;
      });
      var view = root.querySelector('[data-peb-view="' + target + '"]');
      var home = target === 'home';
      root.querySelector('[data-peb-title]').textContent = home ? root.getAttribute('data-home-title') : view.getAttribute('data-title');
      root.querySelector('[data-peb-description]').textContent = home ? root.getAttribute('data-home-description') : view.getAttribute('data-description');
      root.querySelector('[data-peb-nav]').hidden = home;
      root.querySelector('[data-peb-trail]').textContent = home ? '' : root.getAttribute('data-home-title') + ' \u203A ' + view.getAttribute('data-title');
    };
    if (!wait) {
      swap();
      return;
    }
    views.forEach(function (view) {
      view.hidden = view.getAttribute('data-peb-view') !== 'waiting';
    });
    window.setTimeout(swap, WAIT);
  }

  function fromAddress(wait) {
    show(window.location.hash.replace(/^#/, '') || 'home', wait);
  }

  // The script can arrive after the page is parsed, when DOMContentLoaded has
  // already fired: the first view is then set up straight away.
  function ready(run) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', run);
    } else {
      run();
    }
  }

  ready(function () {
    fromAddress(false);
  });

  // The back office's own tooltips explain the switches: pstooltip, its name for
  // Bootstrap's, since jQuery UI takes the name tooltip on these pages. Its
  // scripts load after this one, so the tooltips wait for the whole page;
  // without them the title stays, which the browser shows on its own.
  function tooltips() {
    if (window.jQuery && window.jQuery.fn.pstooltip) {
      window.jQuery('#peb-menu-modules .peb-settings__switch').pstooltip({ placement: 'top' });
    }
  }
  if (document.readyState === 'complete') {
    tooltips();
  } else {
    window.addEventListener('load', tooltips);
  }
  window.addEventListener('hashchange', function () {
    fromAddress(true);
  });

  document.addEventListener('click', function (event) {
    var opener = event.target.closest('#peb-settings [data-peb-open]');
    if (opener) {
      event.preventDefault();
      window.location.hash = opener.getAttribute('data-peb-open');
      return;
    }

    if (event.target.closest('#peb-settings [data-peb-back]')) {
      event.preventDefault();
      // Back to the sections without leaving an empty "#" in the address.
      window.history.pushState(null, '', window.location.pathname + window.location.search);
      show('home', true);
      return;
    }

    var arrow = event.target.closest('.peb-care__prev, .peb-care__next');
    if (arrow) {
      var track = arrow.closest('.peb-care').querySelector('.peb-care__track');
      var step = track.firstElementChild ? track.firstElementChild.getBoundingClientRect().width + 30 : track.clientWidth;
      track.scrollBy({ left: arrow.classList.contains('peb-care__prev') ? -step : step, behavior: 'smooth' });
    }
  });

  // The notifications of this browser. A browser asks its push service for an
  // address the shop can write to, which the shop keeps for the employee; the
  // permission and the address belong to the browser, so each one is switched
  // on where it is used.
  function base64ToBytes(value) {
    var padded = (value + '==='.slice((value.length + 3) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    var raw = window.atob(padded);
    var bytes = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) {
      bytes[i] = raw.charCodeAt(i);
    }
    return bytes;
  }

  function post(url, data) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    }).then(function (response) {
      return response.ok ? response.json() : {};
    });
  }

  function push() {
    var card = document.getElementById('peb-push');
    if (!card) {
      return;
    }
    var status = card.querySelector('[data-peb-push-status]');
    var buttons = {
      on: card.querySelector('[data-peb-push-on]'),
      off: card.querySelector('[data-peb-push-off]'),
      test: card.querySelector('[data-peb-push-test]')
    };
    var text = function (name) {
      return card.getAttribute('data-text-' + name);
    };
    var state = function (name, subscribed) {
      status.textContent = text(name);
      buttons.on.hidden = name !== 'off';
      buttons.off.hidden = !subscribed;
      buttons.test.hidden = !subscribed;
    };
    var busy = function (on) {
      Object.keys(buttons).forEach(function (key) {
        buttons[key].disabled = on;
      });
    };

    if (!window.isSecureContext) {
      state('insecure', false);
      return;
    }
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      state('unsupported', false);
      return;
    }
    if (!card.getAttribute('data-key')) {
      state('no-server', false);
      return;
    }

    var registration = navigator.serviceWorker.register(card.getAttribute('data-worker'));
    var current = null;

    var refresh = function () {
      return registration.then(function (worker) {
        return worker.pushManager.getSubscription();
      }).then(function (subscription) {
        current = subscription;
        if (Notification.permission === 'denied') {
          state('denied', false);
        } else if (subscription) {
          state('on', true);
          // Told again on each visit: the shop forgets a browser whose push
          // service stopped answering, and the browser may have a new address.
          post(card.getAttribute('data-subscribe'), subscription.toJSON());
        } else {
          state('off', false);
        }
      });
    };

    refresh().catch(function () {
      state('unsupported', false);
    });

    // Asked from the click itself: Safari and Firefox only show the
    // permission prompt for a gesture of the user.
    buttons.on.addEventListener('click', function () {
      busy(true);
      Notification.requestPermission().then(function (permission) {
        if (permission !== 'granted') {
          return refresh();
        }
        return registration.then(function (worker) {
          return worker.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: base64ToBytes(card.getAttribute('data-key'))
          });
        }).then(function (subscription) {
          return post(card.getAttribute('data-subscribe'), subscription.toJSON()).then(function (result) {
            if (!result.saved) {
              throw new Error('not saved');
            }
            return refresh();
          });
        });
      }).catch(function () {
        window.alert(card.getAttribute('data-error'));
      }).finally(function () {
        busy(false);
      });
    });

    buttons.off.addEventListener('click', function () {
      if (!current) {
        return;
      }
      busy(true);
      var subscription = current.toJSON();
      current.unsubscribe().then(function () {
        return post(card.getAttribute('data-unsubscribe'), subscription);
      }).then(refresh).catch(function () {
        window.alert(card.getAttribute('data-error'));
      }).finally(function () {
        busy(false);
      });
    });

    buttons.test.addEventListener('click', function () {
      if (!current) {
        return;
      }
      busy(true);
      post(card.getAttribute('data-test'), current.toJSON()).then(function (result) {
        status.textContent = text(result.sent ? 'sent' : 'failed');
      }).catch(function () {
        status.textContent = text('failed');
      }).finally(function () {
        busy(false);
      });
    });
  }

  function notificationsForm() {
    var form = document.getElementById('peb-notifications-form');
    if (!form) {
      return;
    }
    var saved = form.querySelector('[data-peb-saved]');
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var button = form.querySelector('[type="submit"]');
      button.disabled = true;
      saved.hidden = true;
      fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
        .then(function (response) {
          return response.ok ? response.json() : { saved: false };
        })
        .then(function (result) {
          if (!result.saved) {
            throw new Error('not saved');
          }
          saved.hidden = false;
        })
        .catch(function () {
          window.alert(form.getAttribute('data-error'));
        })
        .finally(function () {
          button.disabled = false;
        });
    });
  }

  ready(function () {
    push();
    notificationsForm();
  });

  window.addEventListener('popstate', function () {
    fromAddress(true);
  });

  document.addEventListener('change', function (event) {
    var input = event.target.closest('#peb-menu-modules .peb-settings__switch input');
    if (input) {
      toggleMenuModule(input);
    }
  });

  function toggleMenuModule(input) {
    var section = document.getElementById('peb-menu-modules');
    var card = input.closest('[data-class-name]');
    var shown = input.value === '1';
    var inputs = card.querySelectorAll('.peb-settings__switch input');
    var body = new FormData();
    body.append('class_name', card.getAttribute('data-class-name'));
    body.append('shown', shown ? '1' : '0');

    card.setAttribute('aria-busy', 'true');
    inputs.forEach(function (other) {
      other.disabled = true;
    });
    fetch(section.getAttribute('data-url'), { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (response) {
        return response.ok ? response.json() : { saved: false };
      })
      .then(function (result) {
        if (!result.saved) {
          throw new Error('not saved');
        }
        // The menu is drawn by the server: the next page shows the change.
      })
      .catch(function () {
        // The switch goes back to what is stored.
        card.querySelector('input[value="' + (shown ? '0' : '1') + '"]').checked = true;
        window.alert(section.getAttribute('data-error'));
      })
      .finally(function () {
        inputs.forEach(function (other) {
          other.disabled = false;
        });
        card.removeAttribute('aria-busy');
      });
  }
})();

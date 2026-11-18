/**
 * Copyright since 2026 Novalo
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to ouanis.m@novalo.fr so we can send you a copy immediately.
 *
 * @author    Novalo <ouanis.m@novalo.fr>
 * @copyright Since 2026 Novalo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

/*
 * The count of the bell at the top of the back office, drawn on the browser
 * tab's icon. The script reads the bell's own answers, every two minutes as the
 * bell asks, and sends no request of its own.
 */
(function () {
  'use strict';

  // The settings are printed in the page head after the scripts it loads:
  // read once the bell first answers, when the page is there.
  var settings = null;
  function config() {
    if (settings === null) {
      var element = document.getElementById('peb-favicon-badge');
      settings = element ? JSON.parse(element.textContent) : false;
    }
    return settings;
  }
  var counts = {};
  var original = null;
  var badged = null;
  var image = null;

  function icon() {
    return document.querySelector("link[rel~='icon']");
  }

  function total() {
    return config().kinds.reduce(function (sum, kind) {
      return sum + (counts[kind] || 0);
    }, 0);
  }

  function draw() {
    var link = icon();
    if (!link || !config()) {
      return;
    }
    var count = total();
    // The icon as the page set it, before any badge: another script of the
    // module may have swapped it after this one first read it.
    if (link.href !== original && link.href !== badged) {
      original = link.href;
      image = null;
    }
    if (!count) {
      badged = null;
      link.href = original;
      return;
    }
    if (!image) {
      image = new Image();
      image.onload = draw;
      image.src = original;
      return;
    }
    if (!image.complete || !image.naturalWidth) {
      return;
    }
    var canvas = document.createElement('canvas');
    canvas.width = 32;
    canvas.height = 32;
    var context = canvas.getContext('2d');
    context.drawImage(image, 0, 0, 32, 32);
    context.beginPath();
    context.arc(21, 21, 11, 0, 2 * Math.PI);
    context.fillStyle = config().background;
    context.fill();
    context.fillStyle = config().text;
    context.font = 'bold ' + (count > 9 ? 14 : 18) + 'px sans-serif';
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.fillText(count > 99 ? '99' : String(count), 21, 22);
    try {
      badged = canvas.toDataURL('image/png');
      link.href = badged;
    } catch (error) {
      // An icon served by another site cannot be read back: it stays as it is.
    }
  }

  function json(xhr) {
    try {
      return JSON.parse(xhr.responseText);
    } catch (error) {
      return null;
    }
  }

  function read(xhr, url, body) {
    // Opening a tab of the bell marks its kind as seen.
    if (url.indexOf('notifications/ack') !== -1 || url.indexOf('updateEmployeeLastElement') !== -1) {
      var type = /(?:^|&)type=([a-z_]+)/.exec(typeof body === 'string' ? body : '');
      if (type) {
        counts[type[1]] = 0;
        draw();
      }
      return;
    }
    var data = json(xhr);
    if (data && data.order && data.customer && data.customer_message && 'total' in data.order) {
      counts = {
        order: parseInt(data.order.total, 10) || 0,
        customer: parseInt(data.customer.total, 10) || 0,
        customer_message: parseInt(data.customer_message.total, 10) || 0
      };
      draw();
    }
  }

  // The bell's requests are read as they come back. The back office can carry
  // more than one copy of jQuery, each with its own events, so they are caught
  // at the level every copy shares.
  var open = XMLHttpRequest.prototype.open;
  var send = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function (method, url) {
    this.pebUrl = String(url || '');
    return open.apply(this, arguments);
  };
  XMLHttpRequest.prototype.send = function (body) {
    var xhr = this;
    if (/notifications|updateEmployeeLastElement/.test(xhr.pebUrl || '')) {
      xhr.addEventListener('load', function () {
        if (xhr.status === 200) {
          read(xhr, xhr.pebUrl, body);
        }
      });
    }
    return send.apply(this, arguments);
  };
})();

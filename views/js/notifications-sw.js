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
 * The service worker of the back office notifications: it shows what the shop
 * sends, even with every back office tab closed, and opens the order, the
 * customer or the thread when the notification is clicked. Every text comes
 * from the shop, translated for the employee.
 */
'use strict';

self.addEventListener('install', function () {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
  var data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (error) {
    data = {};
  }
  // Every push must show a notification: browsers revoke the permission of a
  // site that receives one silently.
  event.waitUntil(self.registration.showNotification(data.title || '', {
    body: data.body || '',
    icon: data.icon || undefined,
    tag: data.tag || undefined,
    renotify: !!data.tag,
    data: { url: data.url || '' }
  }));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var url = event.notification.data && event.notification.data.url;
  if (url) {
    event.waitUntil(self.clients.openWindow(url));
  }
});

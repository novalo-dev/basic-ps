/**
 * Module cards of the settings page: placed under the app's configuration cards
 * once the app has drawn them, and each switch saved as soon as it is clicked.
 */
(function () {
  var section = document.getElementById('peb-menu-modules');
  if (!section) {
    return;
  }

  // The app renders its cards after loading; the section joins their column so
  // it has the same width and the app's styles, then shows.
  function place() {
    var column = document.querySelector('#vue-app .puik-card');
    if (!column) {
      return false;
    }
    column.parentNode.appendChild(section);
    section.hidden = false;
    return true;
  }

  if (!place()) {
    var observer = new MutationObserver(function () {
      if (place()) {
        observer.disconnect();
      }
    });
    observer.observe(document.getElementById('vue-app') || document.body, { childList: true, subtree: true });
  }

  section.addEventListener('click', function (event) {
    var button = event.target.closest('.peb-switch');
    if (!button) {
      return;
    }
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
  });
})();

/**
 * PrestaShop 8 templates rendered with the PrestaShop 9 stylesheets.
 */
document.addEventListener('DOMContentLoaded', function () {
  // The PrestaShop 9 icon font no longer knows "info_outline", so the word
  // itself was printed instead of the icon.
  document.querySelectorAll('.material-icons').forEach(function (icon) {
    if (icon.childElementCount === 0 && icon.textContent.trim() === 'info_outline') {
      icon.textContent = 'info';
    }
  });

  // PrestaShop 8 draws the menu toggle as two overlapping chevrons that only its own
  // stylesheet stacks; PrestaShop 9 styles a single "first_page" icon.
  document.querySelectorAll('.nav-bar .menu-collapse').forEach(function (toggle) {
    var icons = toggle.querySelectorAll('.material-icons');
    if (icons.length === 2 && icons[0].textContent.trim() === 'chevron_left') {
      icons[1].remove();
      icons[0].textContent = 'first_page';
    }
  });

  // Legacy pages place their content under a fixed page header whose height the
  // PrestaShop 9 stylesheet assumes. A taller header (a module's configuration
  // page with its subtitle, a wrapped title or toolbar) then covered the first
  // block, so the space is set from the header's real height, 16px under it.
  var content = document.querySelector('body:not(.display-modal) #content.bootstrap');
  var head = content && content.querySelector('.page-head');
  if (content && head && window.getComputedStyle(head).position === 'fixed') {
    var place = function () {
      var contentTop = content.getBoundingClientRect().top + window.scrollY;
      content.style.paddingTop = Math.max(0, Math.round(head.getBoundingClientRect().bottom - contentTop + 16)) + 'px';
    };
    place();
    window.addEventListener('resize', place);
    if (window.ResizeObserver) {
      new ResizeObserver(place).observe(head);
    }
  }
});

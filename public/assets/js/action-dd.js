(function () {
  'use strict';

  var SELECTOR_DD = '.action-dd';
  var SELECTOR_BTN = '.action-dd__btn';
  var SELECTOR_MENU = '.action-dd__menu';
  var SELECTOR_ITEM = '.action-dd__item';
  var CLASS_SHOW = 'show';
  var CLASS_ACTIVE = 'is-active';

  var ActionDD = {
    init: function () {
      document.addEventListener('click', function (e) {
        var dd = e.target.closest(SELECTOR_DD);
        if (!dd) {
          ActionDD.closeAll();
          return;
        }

        var btn = e.target.closest(SELECTOR_BTN);
        if (btn) {
          var menu = dd.querySelector(SELECTOR_MENU);
          if (menu && menu.id) {
            ActionDD._toggle(btn, menu);
          }
          return;
        }

        var menu = e.target.closest(SELECTOR_MENU);
        if (menu) {
          var item = e.target.closest(SELECTOR_ITEM);
          if (item) {
            menu.classList.remove(CLASS_SHOW);
            var ddBtn = dd.querySelector(SELECTOR_BTN);
            if (ddBtn) ddBtn.classList.remove(CLASS_ACTIVE);
          }
          return;
        }

        ActionDD.closeAll();
      });

      document.addEventListener('keydown', function (e) {
        var openMenu = document.querySelector(SELECTOR_MENU + '.' + CLASS_SHOW);
        if (!openMenu) return;

        if (e.key === 'Escape') {
          e.preventDefault();
          ActionDD.closeAll();
          return;
        }

        var items = openMenu.querySelectorAll(SELECTOR_ITEM);
        if (items.length === 0) return;
        var currentIndex = Array.from(items).indexOf(document.activeElement);

        if (e.key === 'ArrowDown') {
          e.preventDefault();
          var nextIndex = (currentIndex + 1) % items.length;
          items[nextIndex].focus();
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          var prevIndex = (currentIndex - 1 + items.length) % items.length;
          items[prevIndex].focus();
        } else if (e.key === 'Enter' || e.key === ' ') {
          if (currentIndex >= 0) {
            e.preventDefault();
            items[currentIndex].click();
          }
        } else if (e.key === 'Tab') {
          ActionDD.closeAll();
        }
      });

      var scrollTimer = null;
      document.addEventListener(
        'scroll',
        function () {
          if (scrollTimer) return;
          scrollTimer = setTimeout(function () {
            var openMenu = document.querySelector(
              SELECTOR_MENU + '.' + CLASS_SHOW
            );
            if (openMenu) {
              var dd = openMenu.closest(SELECTOR_DD);
              if (dd && !isElementVisible(dd)) {
                ActionDD.closeAll();
              }
            }
            scrollTimer = null;
          }, 50);
        },
        true
      );

      function isElementVisible(el) {
        var rect = el.getBoundingClientRect();
        var winW = window.innerWidth;
        var winH = window.innerHeight;
        return (
          rect.left < winW &&
          rect.right > 0 &&
          rect.top < winH &&
          rect.bottom > 0
        );
      }
    },

    _toggle: function (btn, menu) {
      var isOpening = !menu.classList.contains(CLASS_SHOW);
      ActionDD.closeAll();

      if (isOpening) {
        menu.classList.add(CLASS_SHOW);
        menu.offsetHeight;
        if (btn) btn.classList.add(CLASS_ACTIVE);
        ActionDD._positionMenu(menu, btn);
        ActionDD._focusFirstItem(menu);
      }
    },

    closeAll: function () {
      document
        .querySelectorAll(SELECTOR_MENU + '.' + CLASS_SHOW)
        .forEach(function (el) {
          el.classList.remove(CLASS_SHOW);
          var dd = el.closest(SELECTOR_DD);
          if (dd) {
            var btn = dd.querySelector(SELECTOR_BTN);
            if (btn) btn.classList.remove(CLASS_ACTIVE);
          }
        });
    },

    _positionMenu: function (menu, btn) {
      if (!btn) return;
      var dd = btn.closest(SELECTOR_DD);
      if (!dd) return;

      menu.style.removeProperty('top');
      menu.style.removeProperty('left');
      menu.style.removeProperty('right');
      menu.style.removeProperty('bottom');

      var ddRect = dd.getBoundingClientRect();
      var menuRect = menu.getBoundingClientRect();
      var vw = window.innerWidth;
      var vh = window.innerHeight;
      var gap = 6;

      var topPos = ddRect.bottom + gap;
      var bottomPos = ddRect.top - gap - menuRect.height;

      var useBottom = topPos + menuRect.height > vh - 12 && bottomPos > 0;
      if (useBottom) {
        menu.style.top = 'auto';
        menu.style.bottom = vh - ddRect.top + gap + 'px';
      } else {
        menu.style.top = ddRect.bottom + gap + 'px';
        menu.style.bottom = 'auto';
      }

      var rightPos = vw - ddRect.right;
      var leftEdge = rightPos - menuRect.width;

      if (leftEdge < 12) {
        menu.style.left = '12px';
        menu.style.right = 'auto';
      } else {
        menu.style.right = rightPos + 'px';
        menu.style.left = 'auto';
      }
    },

    _focusFirstItem: function (menu) {
      var first = menu.querySelector(SELECTOR_ITEM);
      if (first) {
        setTimeout(function () {
          first.focus();
        }, 50);
      }
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      ActionDD.init();
    });
  } else {
    ActionDD.init();
  }

  window.ActionDD = ActionDD;
})();

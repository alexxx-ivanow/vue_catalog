/**
 * AbcwwwMarkers — интерактивные маркеры поверх изображения.
 */
(function (window, document) {
  'use strict';

  var AbcwwwMarkers = {

    DEBUG: false,

    DEFAULTS: {
      iconPc: 24,
      iconMobile: 18,
      mainColor: '#1E88E5',
      activeColor: '#FF5722',
      tooltipMargin: 8,
      hoverCloseDelay: 120,
      allowPreviewHtml: true,
      modalWidth: 500
    },

    SEL: {
      root: '.abcwww-markers',
      board: '.abcwww-markers__board',
      image: '.abcwww-markers__image',
      layer: '.abcwww-markers__layer',
      item: '.abcwww-markers__item',
      pin: '.abcwww-markers__pin',
      tooltip: '.abcwww-markers__tooltip',
      openTooltip: '.abcwww-markers__tooltip.is-open'
    },

    AJAX_URL: '/bitrix/tools/abcwww.markers/ajax.php',

    instances: [],
    globalBound: false,
    modalEl: null,
    modalMarker: null,
    _refreshPending: false,

    log: function (label, payload) {
      if (!this.DEBUG || !window.console) return;
      if (typeof console.groupCollapsed === 'function') {
        console.groupCollapsed('[abcwww.markers] ' + label);
        console.log(payload);
        console.groupEnd();
        return;
      }
      console.log('[abcwww.markers] ' + label, payload);
    },

    escapeHtml: function (value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    },

    safeUrl: function (value, allowDataImage) {
      var url = String(value == null ? '' : value)
        .replace(/[\u0000-\u001F\u007F]/g, '')
        .trim();
      if (!url) return '';

      var scheme = url.match(/^([a-z][a-z0-9+.\-]*):/i);
      if (!scheme) return url;

      var proto = scheme[1].toLowerCase();
      if (proto === 'http' || proto === 'https' || proto === 'mailto' || proto === 'tel') return url;
      if (allowDataImage && /^data:image\//i.test(url)) return url;
      return '';
    },

    toNumber: function (value, fallback) {
      var num = parseFloat(value);
      return isFinite(num) ? num : fallback;
    },

    clamp: function (value, min, max) {
      if (max < min) return min;
      return Math.min(Math.max(value, min), max);
    },

    iconSvg: function () {
      return '<svg viewBox="0 0 24 24" aria-hidden="true">' +
        '<path fill="currentColor" d="M12 2c-4.1 0-7.5 3.3-7.5 7.4 0 5.1 6.3 11.8 7.1 12.5.2.2.6.2.8 0 ' +
        '.8-.7 7.1-7.4 7.1-12.5C19.5 5.3 16.1 2 12 2zm0 10.2a2.8 2.8 0 1 1 0-5.6 2.8 2.8 0 0 1 0 5.6z"/></svg>';
    },

    iconMask: function (url) {
      var safe = this.safeUrl(url, true);
      if (!safe) return this.iconSvg();
      return '<span class="abcwww-markers__icon-mask" style="--marker-icon:url(&quot;' +
        this.escapeHtml(safe) + '&quot;);"></span>';
    },

    iconImage: function (url) {
      var safe = this.safeUrl(url, true);
      if (!safe) return this.iconSvg();
      return '<img class="abcwww-markers__icon-image" src="' + this.escapeHtml(safe) + '" alt="" loading="lazy">';
    },

    buildIconHtml: function (data) {
      if (!data.icon) return this.iconSvg();
      if (data.colorizeIcons === false) return this.iconImage(data.icon);
      return this.iconMask(data.icon);
    },

    buildButton: function (marker, data) {
      if (!marker.showMoreButton || !marker.buttonText) return '';

      var color = this.escapeHtml(marker.buttonColor || data.mainColor || this.DEFAULTS.mainColor);
      var text = this.escapeHtml(marker.buttonText);
      var href = this.safeUrl(marker.productUrl);

      if (href) {
        return '<a class="abcwww-markers__btn" style="background:' + color + '" href="' +
          this.escapeHtml(href) + '">' + text + '</a>';
      }
      return '<span class="abcwww-markers__btn" style="background:' + color + '">' + text + '</span>';
    },

    buildContentHtml: function (marker, data, forModal) {
      var html = '';
      var image = this.safeUrl(forModal ? (marker.detailImage || marker.previewImage) : marker.previewImage, true);
      var text = forModal ? (marker.detailText || marker.previewText) : marker.previewText;

      if (image) {
        html += '<img class="abcwww-markers__preview-image" src="' +
          this.escapeHtml(image) + '" alt="" loading="lazy">';
      }
      if (text) {
        html += '<div class="abcwww-markers__preview-text">' +
          (this.DEFAULTS.allowPreviewHtml ? text : this.escapeHtml(text)) +
          '</div>';
      }
      html += this.buildButton(marker, data);
      return html;
    },

    buildTooltip: function (marker, data) {
      var tooltipClass = 'abcwww-markers__tooltip' + (marker.opened ? ' is-open' : '');
      return '<div class="' + tooltipClass + '" role="dialog">' +
        '<h4>' + this.escapeHtml(marker.name || '') + '</h4>' +
        this.buildContentHtml(marker, data, false) + '</div>';
    },

    buildModalSource: function (marker, data) {
      return '<div class="abcwww-markers__modal-source" hidden>' +
        '<h4>' + this.escapeHtml(marker.name || '') + '</h4>' +
        this.buildContentHtml(marker, data, true) +
        '</div>';
    },

    buildModalShell: function () {
      return '<div class="abcwww-markers-modal" aria-hidden="true" hidden>' +
        '<div class="abcwww-markers-modal__backdrop" data-modal-close="Y"></div>' +
        '<div class="abcwww-markers-modal__dialog" role="dialog" aria-modal="true">' +
          '<button type="button" class="abcwww-markers-modal__close" data-modal-close="Y" aria-label="Close">&times;</button>' +
          '<div class="abcwww-markers-modal__body"></div>' +
        '</div>' +
      '</div>';
    },

    buildItem: function (marker, data) {
      var item = document.createElement('div');
      var colorize = data.colorizeIcons !== false;
      item.className = 'abcwww-markers__item' + (colorize ? '' : ' is-natural');
      item.setAttribute('data-marker-id', String(marker.id == null ? '' : marker.id));
      item.style.left = this.clamp(this.toNumber(marker.x, 0), 0, 100) + '%';
      item.style.top = this.clamp(this.toNumber(marker.y, 0), 0, 100) + '%';

      if (colorize) {
        item.style.setProperty('--marker-color', marker.color || data.mainColor || this.DEFAULTS.mainColor);
        item.style.setProperty('--marker-hover', marker.hoverColor || data.activeColor || this.DEFAULTS.activeColor);
      }
      if (marker.opened && !data.useModal) {
        item.classList.add('is-active');
      }

      var html =
        '<button type="button" class="abcwww-markers__pin" aria-expanded="false" aria-label="' +
        this.escapeHtml(marker.name || '') + '">' + this.buildIconHtml(data) + '</button>';

      if (data.useModal) {
        html += this.buildModalSource(marker, data);
      } else {
        html += this.buildTooltip(marker, data);
      }

      item.innerHTML = html;
      return item;
    },

    render: function (target, data) {
      if (!target || !data) return;

      var image = this.safeUrl(data.image, true);
      if (!image) return;

      this.destroy(target.querySelector(this.SEL.root));

      var sizeStyle =
        '--marker-size-desktop:' + this.toNumber(data.iconPc, this.DEFAULTS.iconPc) + 'px;' +
        '--marker-size-mobile:' + this.toNumber(data.iconMobile, this.DEFAULTS.iconMobile) + 'px;';

      target.innerHTML =
        '<div class="abcwww-markers" style="' + sizeStyle + '">' +
          '<div class="abcwww-markers__board">' +
            '<img class="abcwww-markers__image" src="' + this.escapeHtml(image) +
              '" alt="' + this.escapeHtml(data.name || '') + '">' +
            '<div class="abcwww-markers__layer"></div>' +
          '</div>' +
        '</div>' +
        (data.useModal ? this.buildModalShell() : '');

      var layer = target.querySelector(this.SEL.layer);
      if (!layer) return;

      var self = this;
      var fragment = document.createDocumentFragment();
      (data.markers || []).forEach(function (marker) {
        fragment.appendChild(self.buildItem(marker, data));
      });
      layer.appendChild(fragment);

      this.hydrate(target, data);
    },

    syncLayerGeometry: function (root) {
      if (!root) return;
      var board = root.querySelector(this.SEL.board);
      var image = root.querySelector(this.SEL.image);
      var layer = root.querySelector(this.SEL.layer);
      if (!board || !image || !layer) return;

      layer.style.left = image.offsetLeft + 'px';
      layer.style.top = image.offsetTop + 'px';
      layer.style.width = image.clientWidth + 'px';
      layer.style.height = image.clientHeight + 'px';
    },

    refresh: function (root) {
      if (!root) return;
      var self = this;
      this.syncLayerGeometry(root);
      root.querySelectorAll(this.SEL.openTooltip).forEach(function (tooltip) {
        var item = tooltip.closest(self.SEL.item);
        var pin = item && item.querySelector(self.SEL.pin);
        if (item && pin) self.applyTooltipPlacement(item, pin, tooltip);
      });
    },

    refreshAll: function () {
      var self = this;
      this.instances.forEach(function (entry) { self.refresh(entry.root); });
    },

    applyTooltipPlacement: function (item, pin, tooltip) {
      if (!item || !pin || !tooltip) return;
      var layer = item.offsetParent || item.closest(this.SEL.layer);
      if (!layer) return;

      var wasOpen = tooltip.classList.contains('is-open');
      tooltip.classList.add('is-open');
      tooltip.style.visibility = 'hidden';

      var layerRect = layer.getBoundingClientRect();
      var itemRect = item.getBoundingClientRect();
      var pinRect = pin.getBoundingClientRect();
      var width = tooltip.offsetWidth;
      var height = tooltip.offsetHeight;
      var margin = this.DEFAULTS.tooltipMargin;

      var left = this.clamp(
        pinRect.left + (pinRect.width / 2) - (width / 2),
        layerRect.left + margin,
        layerRect.right - width - margin
      );

      var top = pinRect.bottom + margin;
      if (top + height > layerRect.bottom - margin) {
        top = pinRect.top - height - margin;
      }
      top = this.clamp(top, layerRect.top + margin, layerRect.bottom - height - margin);

      tooltip.style.left = (left - itemRect.left) + 'px';
      tooltip.style.top = (top - itemRect.top) + 'px';
      tooltip.style.right = 'auto';
      tooltip.style.bottom = 'auto';
      tooltip.style.transform = 'none';
      tooltip.style.visibility = '';

      if (!wasOpen) tooltip.classList.remove('is-open');
    },

    bringItemFront: function (item) {
      if (!item) return;
      var root = item.closest(this.SEL.root);
      if (root) {
        root.querySelectorAll(this.SEL.item).forEach(function (node) {
          node.classList.remove('is-open-front');
        });
      }
      item.classList.add('is-open-front');
    },

    openTooltip: function (item) {
      var pin = item.querySelector(this.SEL.pin);
      var tooltip = item.querySelector(this.SEL.tooltip);
      if (!pin || !tooltip) return;
      this.bringItemFront(item);
      this.applyTooltipPlacement(item, pin, tooltip);
      tooltip.classList.add('is-open');
      item.classList.add('is-active');
      pin.setAttribute('aria-expanded', 'true');
    },

    closeTooltip: function (item) {
      var pin = item.querySelector(this.SEL.pin);
      var tooltip = item.querySelector(this.SEL.tooltip);
      if (tooltip) tooltip.classList.remove('is-open');
      item.classList.remove('is-active');
      item.classList.remove('is-open-front');
      if (pin) pin.setAttribute('aria-expanded', 'false');
    },

    isOpen: function (item) {
      var tooltip = item.querySelector(this.SEL.tooltip);
      return !!tooltip && tooltip.classList.contains('is-open');
    },

    cancelClose: function (item) {
      if (!item || !item._markersCloseTimer) return;
      clearTimeout(item._markersCloseTimer);
      item._markersCloseTimer = null;
    },

    scheduleClose: function (item) {
      if (!item) return;
      var self = this;
      this.cancelClose(item);
      item._markersCloseTimer = setTimeout(function () {
        item._markersCloseTimer = null;
        self.closeTooltip(item);
      }, this.DEFAULTS.hoverCloseDelay);
    },

    closeAll: function (except) {
      var self = this;
      this.instances.forEach(function (entry) {
        if (entry.root && entry.root.dataset && entry.root.dataset.tooltipAlways === 'Y') {
          return;
        }
        entry.root.querySelectorAll(self.SEL.item).forEach(function (item) {
          if (item !== except) self.closeTooltip(item);
        });
      });
    },

    /* ------------------------------------------------------------------ */
    /* Modal                                                               */
    /* ------------------------------------------------------------------ */

    findModalRoot: function (fromNode) {
      if (!fromNode || !fromNode.closest) return null;
      return fromNode.closest('.abcwww-markers-root') || fromNode.closest(this.SEL.root) || fromNode;
    },

    bindModalEvents: function (el) {
      if (!el || el.dataset.modalBound === 'Y') return;
      el.dataset.modalBound = 'Y';

      var self = this;
      el.addEventListener('click', function (event) {
        var target = event.target;
        if (!target) return;
        if (target.getAttribute && target.getAttribute('data-modal-close') === 'Y') {
          self.closeModal(el);
          return;
        }
        if (typeof target.closest === 'function' && target.closest('[data-modal-close="Y"]')) {
          self.closeModal(el);
        }
      });

      var dialog = el.querySelector('.abcwww-markers-modal__dialog');
      if (dialog) {
        dialog.addEventListener('click', function (event) {
          var target = event.target;
          if (target && (
            (target.getAttribute && target.getAttribute('data-modal-close') === 'Y') ||
            (typeof target.closest === 'function' && target.closest('[data-modal-close="Y"]'))
          )) {
            return;
          }
          event.stopPropagation();
        });
      }

      var closeBtn = el.querySelector('.abcwww-markers-modal__close');
      if (closeBtn) {
        closeBtn.addEventListener('click', function (event) {
          event.preventDefault();
          event.stopPropagation();
          self.closeModal(el);
        });
      }
    },

    ensureModal: function (fromNode) {
      var scope = this.findModalRoot(fromNode);
      var el = scope ? scope.querySelector('.abcwww-markers-modal') : null;

      if (!el) {
        if (this.modalEl && document.body.contains(this.modalEl)) {
          el = this.modalEl;
        } else {
          el = document.createElement('div');
          el.className = 'abcwww-markers-modal';
          el.setAttribute('aria-hidden', 'true');
          el.hidden = true;
          el.innerHTML =
            '<div class="abcwww-markers-modal__backdrop" data-modal-close="Y"></div>' +
            '<div class="abcwww-markers-modal__dialog" role="dialog" aria-modal="true">' +
              '<button type="button" class="abcwww-markers-modal__close" data-modal-close="Y" aria-label="Close">&times;</button>' +
              '<div class="abcwww-markers-modal__body"></div>' +
            '</div>';
          document.body.appendChild(el);
          this.modalEl = el;
        }
      }

      this.bindModalEvents(el);
      this.modalEl = el;
      return el;
    },

    openModal: function (item, marker, data) {
      var el = this.ensureModal(item || null);
      var body = el.querySelector('.abcwww-markers-modal__body');
      var dialog = el.querySelector('.abcwww-markers-modal__dialog');
      if (!body || !dialog) return;

      dialog.style.width = this.DEFAULTS.modalWidth + 'px';

      var source = item && item.querySelector
        ? item.querySelector('.abcwww-markers__modal-source')
        : null;

      if (source) {
        // Берём разметку из шаблона компонента.
        body.innerHTML = source.innerHTML;
      } else {
        body.innerHTML =
          '<h4>' + this.escapeHtml(marker.name || '') + '</h4>' +
          this.buildContentHtml(marker, data, true);
      }

      this.modalMarker = marker;
      el.hidden = false;
      el.classList.add('is-open');
      el.setAttribute('aria-hidden', 'false');
      document.documentElement.style.overflow = 'hidden';
    },

    closeModal: function (modalEl) {
      var el = modalEl || this.modalEl;
      if (!el) return;
      el.classList.remove('is-open');
      el.hidden = true;
      el.setAttribute('aria-hidden', 'true');
      this.modalMarker = null;
      document.documentElement.style.overflow = '';
    },

    isModalOpen: function () {
      return !!(this.modalEl && this.modalEl.classList.contains('is-open'));
    },

    /* ------------------------------------------------------------------ */
    /* Init                                                                */
    /* ------------------------------------------------------------------ */

    hydrate: function (target, data) {
      var root = target.querySelector(this.SEL.root) ||
        (target.classList && target.classList.contains('abcwww-markers') ? target : null);
      if (!root || !data) return;
      if (root.dataset.hydrated === 'Y') return;
      root.dataset.hydrated = 'Y';

      this.bindGlobalEvents();
      this.trackInstance(root);

      var self = this;
      var useModal = !!data.useModal;
      var tooltipOnHover = !!data.tooltipOnHover;
      var showTooltipAlways = !useModal && !!data.showTooltipAlways;
      root.dataset.tooltipAlways = showTooltipAlways ? 'Y' : 'N';
      root.dataset.useModal = useModal ? 'Y' : 'N';

      var markerMap = {};
      (data.markers || []).forEach(function (marker) {
        markerMap[String(marker.id)] = marker;
      });

      root.querySelectorAll(this.SEL.item).forEach(function (item) {
        var id = String(item.getAttribute('data-marker-id') || '');
        var marker = markerMap[id];
        if (!marker) {
          self.log('markerNotFound', { id: id });
          return;
        }
        self.bindMarker(item, marker, data, tooltipOnHover, showTooltipAlways, useModal);
      });
    },

    bindMarker: function (item, marker, data, tooltipOnHover, showTooltipAlways, useModal) {
      var self = this;
      var pin = item.querySelector(this.SEL.pin);
      if (!pin) return;

      var openPopup = function () {
        if (useModal) {
          self.closeAll();
          self.openModal(item, marker, data);
          return;
        }
        self.cancelClose(item);
        if (self.isOpen(item)) {
          self.closeTooltip(item);
        } else {
          self.closeAll(item);
          self.openTooltip(item);
        }
      };

      if (showTooltipAlways) {
        self.openTooltip(item);
        return;
      }

      pin.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        openPopup();
      });

      if (!useModal) {
        item.addEventListener('click', function (event) { event.stopPropagation(); });
        pin.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') self.closeTooltip(item);
        });
      }

      if (tooltipOnHover) {
        item.addEventListener('mouseenter', function () {
          if (useModal) {
            self.openModal(item, marker, data);
            return;
          }
          self.cancelClose(item);
          self.closeAll(item);
          self.openTooltip(item);
        });
        if (!useModal) {
          item.addEventListener('mouseleave', function () {
            self.scheduleClose(item);
          });
        }
      }

      var tooltip = item.querySelector(this.SEL.tooltip);
      if (tooltip && tooltip.classList.contains('is-open')) {
        this.bringItemFront(item);
        this.applyTooltipPlacement(item, pin, tooltip);
      }
    },

    trackInstance: function (root) {
      var self = this;
      var image = root.querySelector(this.SEL.image);
      var entry = { root: root, image: image, observer: null, onImageLoad: null };

      this.syncLayerGeometry(root);

      if (image) {
        entry.onImageLoad = function () { self.refresh(root); };
        image.addEventListener('load', entry.onImageLoad);
        image.addEventListener('error', entry.onImageLoad);

        if (typeof ResizeObserver !== 'undefined') {
          entry.observer = new ResizeObserver(function () { self.refresh(root); });
          entry.observer.observe(image);
          entry.observer.observe(root);
        }
      }

      this.instances.push(entry);
      return entry;
    },

    destroy: function (root) {
      if (!root) return;
      for (var i = this.instances.length - 1; i >= 0; i--) {
        var entry = this.instances[i];
        if (entry.root !== root) continue;
        if (entry.observer) entry.observer.disconnect();
        if (entry.image && entry.onImageLoad) {
          entry.image.removeEventListener('load', entry.onImageLoad);
          entry.image.removeEventListener('error', entry.onImageLoad);
        }
        this.instances.splice(i, 1);
      }
      delete root.dataset.hydrated;
    },

    requestFrame: function (callback) {
      if (typeof window.requestAnimationFrame === 'function') {
        return window.requestAnimationFrame(callback);
      }
      return setTimeout(callback, 16);
    },

    onViewportChange: function () {
      if (this._refreshPending) return;
      this._refreshPending = true;

      var self = this;
      this.requestFrame(function () {
        self._refreshPending = false;
        self.refreshAll();
      });
    },

    bindGlobalEvents: function () {
      if (this.globalBound) return;
      this.globalBound = true;

      var self = this;
      var onViewportChange = function () { self.onViewportChange(); };

      window.addEventListener('resize', onViewportChange);
      window.addEventListener('scroll', onViewportChange, true);

      document.addEventListener('click', function () {
        self.closeAll();
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' || event.key === 'Esc') {
          if (self.isModalOpen()) {
            self.closeModal();
            return;
          }
          self.closeAll();
        }
      });
    },

    initInstances: function () {
      var list = window.AbcwwwMarkersInstances || [];
      window.AbcwwwMarkersInstances = [];

      var self = this;
      list.forEach(function (entry) {
        if (!entry || !entry.targetId) return;
        var target = document.getElementById(entry.targetId);
        if (!target) return;
        if (entry.serverRendered) {
          self.hydrate(target, entry.data || {});
        } else {
          self.render(target, entry.data || {});
        }
      });
    },

    initByDataAttr: function () {
      var self = this;
      var nodes = document.querySelectorAll('div[data-abcwww-snippet="markers"][data-id]');

      Array.prototype.forEach.call(nodes, function (node) {
        if (node.dataset.initialized === 'Y') return;

        var sectionId = self.toNumber(node.getAttribute('data-id'), 0);
        var iblockId = self.toNumber(
          node.getAttribute('data-iblock-id') || window.AbcwwwMarkersDefaultIblock,
          0
        );
        if (!sectionId || !iblockId) return;

        node.dataset.initialized = 'Y';

        var tooltipOnHover = String(node.getAttribute('data-tooltip-hover') || '').toUpperCase() === 'Y';
        var useModal = String(node.getAttribute('data-use-modal') || '').toUpperCase() === 'Y';

        var params = new URLSearchParams({
          action: 'getScheme',
          section_id: String(sectionId),
          iblock_id: String(iblockId)
        });

        fetch(self.AJAX_URL + '?' + params.toString(), { credentials: 'same-origin' })
          .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
          })
          .then(function (res) {
            if (res && res.success && res.data) {
              res.data.tooltipOnHover = tooltipOnHover;
              res.data.useModal = useModal;
              if (useModal) {
                res.data.showTooltipAlways = false;
                (res.data.markers || []).forEach(function (marker) {
                  marker.opened = false;
                });
              }
              self.render(node, res.data);
            } else {
              throw new Error((res && res.error) || 'Некорректный ответ сервера');
            }
          })
          .catch(function (error) {
            delete node.dataset.initialized;
            self.log('fetchError', { sectionId: sectionId, error: error });
          });
      });
    },

    init: function () {
      this.initInstances();
      this.initByDataAttr();
    }
  };

  AbcwwwMarkers.init();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      AbcwwwMarkers.init();
    });
  }
})(window, document);

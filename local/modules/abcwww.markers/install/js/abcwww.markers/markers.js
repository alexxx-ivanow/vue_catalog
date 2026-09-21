/**
 * AbcwwwMarkers — интерактивные маркеры поверх изображения.
 * Рефакторинг: все свободные функции собраны в один объект с методами.
 */
(function (window, document) {
  'use strict';

  var AbcwwwMarkers = {

    /* ------------------------------------------------------------------ */
    /* Конфигурация                                                        */
    /* ------------------------------------------------------------------ */

    DEBUG: false,

    DEFAULTS: {
      iconPc: 24,
      iconMobile: 18,
      mainColor: '#1E88E5',
      activeColor: '#FF5722',
      tooltipMargin: 8,
      hoverCloseDelay: 120,
      // previewText приходит из админки как HTML. Если источник недоверенный —
      // поставьте false, тогда текст будет экранирован.
      allowPreviewHtml: true
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

    /** Активные экземпляры: { root, image, observer, onImageLoad } */
    instances: [],

    globalBound: false,

    /* ------------------------------------------------------------------ */
    /* Утилиты                                                             */
    /* ------------------------------------------------------------------ */

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

    /**
     * Отсекает javascript:/vbscript:/data: и прочие опасные схемы.
     * Относительные пути пропускаются как есть.
     */
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

    /* ------------------------------------------------------------------ */
    /* Разметка                                                            */
    /* ------------------------------------------------------------------ */

    iconSvg: function () {
      return '<svg viewBox="0 0 24 24" aria-hidden="true">' +
        '<path fill="currentColor" d="M12 2c-4.1 0-7.5 3.3-7.5 7.4 0 5.1 6.3 11.8 7.1 12.5.2.2.6.2.8 0 ' +
        '.8-.7 7.1-7.4 7.1-12.5C19.5 5.3 16.1 2 12 2zm0 10.2a2.8 2.8 0 1 1 0-5.6 2.8 2.8 0 0 1 0 5.6z"/></svg>';
    },

    iconMask: function (url) {
      var safe = this.safeUrl(url, true);
      if (!safe) return this.iconSvg();
      // кавычки уже экранированы, url() не разорвать
      return '<span class="abcwww-markers__icon-mask" style="--marker-icon:url(&quot;' +
        this.escapeHtml(safe) + '&quot;);"></span>';
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

    buildTooltip: function (marker, data) {
      var preview = '';

      var previewImage = this.safeUrl(marker.previewImage, true);
      if (previewImage) {
        preview += '<img class="abcwww-markers__preview-image" src="' +
          this.escapeHtml(previewImage) + '" alt="" loading="lazy">';
      }
      if (marker.previewText) {
        preview += '<div class="abcwww-markers__preview-text">' +
          (this.DEFAULTS.allowPreviewHtml ? marker.previewText : this.escapeHtml(marker.previewText)) +
          '</div>';
      }

      var tooltipClass = 'abcwww-markers__tooltip' + (marker.opened ? ' is-open' : '');
      return '<div class="' + tooltipClass + '" role="dialog">' +
        '<h4>' + this.escapeHtml(marker.name || '') + '</h4>' +
        preview + this.buildButton(marker, data) + '</div>';
    },

    buildItem: function (marker, data) {
      var item = document.createElement('div');
      item.className = 'abcwww-markers__item';
      item.setAttribute('data-marker-id', String(marker.id == null ? '' : marker.id));
      item.style.left = this.clamp(this.toNumber(marker.x, 0), 0, 100) + '%';
      item.style.top = this.clamp(this.toNumber(marker.y, 0), 0, 100) + '%';
      item.style.setProperty('--marker-color', marker.color || data.mainColor || this.DEFAULTS.mainColor);
      item.style.setProperty('--marker-hover', marker.hoverColor || data.activeColor || this.DEFAULTS.activeColor);
      if (marker.opened) {
        item.classList.add('is-active');
      }

      var icon = data.icon ? this.iconMask(data.icon) : this.iconSvg();
      item.innerHTML =
        '<button type="button" class="abcwww-markers__pin" aria-expanded="false" aria-label="' +
        this.escapeHtml(marker.name || '') + '">' + icon + '</button>' +
        this.buildTooltip(marker, data);

      return item;
    },

    /* ------------------------------------------------------------------ */
    /* Рендер                                                              */
    /* ------------------------------------------------------------------ */

    render: function (target, data) {
      if (!target || !data) return;

      var image = this.safeUrl(data.image, true);
      if (!image) return;

      // если в контейнере уже был экземпляр — снимаем его слушатели
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
        '</div>';

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

    /* ------------------------------------------------------------------ */
    /* Геометрия                                                           */
    /* ------------------------------------------------------------------ */

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

      this.log('syncLayerGeometry', {
        board: { offsetWidth: board.offsetWidth, offsetHeight: board.offsetHeight },
        image: {
          offsetLeft: image.offsetLeft,
          offsetTop: image.offsetTop,
          clientWidth: image.clientWidth,
          clientHeight: image.clientHeight,
          naturalWidth: image.naturalWidth || 0,
          naturalHeight: image.naturalHeight || 0,
          complete: !!image.complete
        },
        layer: { left: layer.style.left, top: layer.style.top, width: layer.style.width, height: layer.style.height }
      });
    },

    /** Пересчёт позиции открытых тултипов (после resize/scroll). */
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

    /* ------------------------------------------------------------------ */
    /* Тултипы                                                             */
    /* ------------------------------------------------------------------ */

    openTooltip: function (item) {
      var pin = item.querySelector(this.SEL.pin);
      var tooltip = item.querySelector(this.SEL.tooltip);
      if (!pin || !tooltip) return;
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
      if (pin) pin.setAttribute('aria-expanded', 'false');
    },

    isOpen: function (item) {
      var tooltip = item.querySelector(this.SEL.tooltip);
      return !!tooltip && tooltip.classList.contains('is-open');
    },

    /** Отмена отложенного закрытия (состояние хранится на самом элементе). */
    cancelClose: function (item) {
      if (!item || !item._markersCloseTimer) return;
      clearTimeout(item._markersCloseTimer);
      item._markersCloseTimer = null;
    },

    /** Отложенное закрытие — даёт увести курсор с пина на тултип. */
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
    /* Инициализация экземпляра                                            */
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
      var tooltipOnHover = !!data.tooltipOnHover;
      var showTooltipAlways = !!data.showTooltipAlways;
      root.dataset.tooltipAlways = showTooltipAlways ? 'Y' : 'N';

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
        self.bindMarker(item, marker, tooltipOnHover, showTooltipAlways);
      });
    },

    bindMarker: function (item, marker, tooltipOnHover, showTooltipAlways) {
      var self = this;
      var pin = item.querySelector(this.SEL.pin);
      var tooltip = item.querySelector(this.SEL.tooltip);
      if (!pin || !tooltip) return;

      this.log('markerPosition', {
        id: marker.id,
        percent: { x: marker.x, y: marker.y },
        style: { left: item.style.left, top: item.style.top },
        px: {
          layerWidth: item.offsetParent ? item.offsetParent.clientWidth : null,
          layerHeight: item.offsetParent ? item.offsetParent.clientHeight : null,
          computedLeft: item.offsetLeft,
          computedTop: item.offsetTop
        }
      });

      if (showTooltipAlways) {
        self.openTooltip(item);
      } else {
        pin.addEventListener('click', function (event) {
          event.preventDefault();
          event.stopPropagation();
          self.cancelClose(item);
          if (self.isOpen(item)) {
            self.closeTooltip(item);
          } else {
            self.closeAll(item);
            self.openTooltip(item);
          }
        });

        // клик внутри тултипа не должен закрывать его
        item.addEventListener('click', function (event) { event.stopPropagation(); });

        pin.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') self.closeTooltip(item);
        });

        if (tooltipOnHover) {
          item.addEventListener('mouseenter', function () {
            self.cancelClose(item);
            self.closeAll(item);
            self.openTooltip(item);
          });
          item.addEventListener('mouseleave', function () {
            self.scheduleClose(item);
          });
        }
      }

      if (tooltip.classList.contains('is-open')) {
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

    /* ------------------------------------------------------------------ */
    /* Глобальные события — навешиваются один раз на всю страницу          */
    /* ------------------------------------------------------------------ */

    /** Флаг троттлинга пересчёта геометрии. */
    _refreshPending: false,

    requestFrame: function (callback) {
      if (typeof window.requestAnimationFrame === 'function') {
        return window.requestAnimationFrame(callback);
      }
      return setTimeout(callback, 16);
    },

    /** Один пересчёт на кадр вместо пересчёта на каждое событие resize/scroll. */
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

      document.addEventListener('click', function () { self.closeAll(); });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' || event.key === 'Esc') self.closeAll();
      });
    },

    /* ------------------------------------------------------------------ */
    /* Точки входа                                                         */
    /* ------------------------------------------------------------------ */

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
      var nodes = document.querySelectorAll('div[data-wnfgr="markers"][data-id]');

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
              self.render(node, res.data);
            } else {
              throw new Error((res && res.error) || 'Некорректный ответ сервера');
            }
          })
          .catch(function (error) {
            // снимаем флаг, чтобы повторный вызов мог попробовать снова
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

  //window.AbcwwwMarkers = AbcwwwMarkers;

  AbcwwwMarkers.init();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      AbcwwwMarkers.init();
    });
  }
})(window, document);

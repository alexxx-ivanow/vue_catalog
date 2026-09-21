(function () {
  var root = document.getElementById('abcwww-markers-editor');
  if (!root) return;

  var data = window.AbcwwwMarkersEditorData || { markers: [] };
  var board = root.querySelector('.abcwww-markers-editor__board');
  var image = root.querySelector('.abcwww-markers-editor__image');
  var layer = root.querySelector('.abcwww-markers-editor__layer');
  var tableBody = root.querySelector('#abcwww-markers-editor-table tbody');
  var iblockId = Number(root.getAttribute('data-iblock-id') || 0);
  var sectionId = Number(root.getAttribute('data-section-id') || 0);
  var sessid = root.getAttribute('data-sessid') || '';
  var iblockType = String((data && data.iblockType) || '');
  var dragState = null;
  var SNAP_STEP = 0.5;
  var EDGE_SNAP_THRESHOLD = 0.6;
  var hoveredMarkerId = null;

  function api(action, payload) {
    var formData = new FormData();
    formData.append('action', action);
    formData.append('sessid', sessid);
    Object.keys(payload || {}).forEach(function (key) {
      formData.append(key, String(payload[key]));
    });
    return fetch('/bitrix/tools/abcwww.markers/ajax.php', {
      method: 'POST',
      body: formData
    }).then(function (r) { return r.json(); });
  }

  function syncLayerGeometry() {
    if (!board || !image || !layer) return;
    layer.style.left = image.offsetLeft + 'px';
    layer.style.top = image.offsetTop + 'px';
    layer.style.width = image.clientWidth + 'px';
    layer.style.height = image.clientHeight + 'px';
  }

  function bindLayerSync() {
    if (!image) return;
    syncLayerGeometry();
    if (!image.complete) {
      image.addEventListener('load', syncLayerGeometry);
    }
    window.addEventListener('resize', syncLayerGeometry);
    if (typeof ResizeObserver !== 'undefined') {
      var observer = new ResizeObserver(syncLayerGeometry);
      observer.observe(image);
      observer.observe(board);
    }
  }

  function drawMarkers() {
    layer.innerHTML = '';
    (data.markers || []).forEach(function (marker) {
      var pin = document.createElement('button');
      pin.type = 'button';
      pin.className = 'abcwww-markers-editor__pin';
      pin.title = marker.name || '';
      pin.dataset.markerId = String(marker.id);
      pin.style.left = marker.x + '%';
      pin.style.top = marker.y + '%';
      pin.textContent = '+';

      pin.addEventListener('dblclick', function () {
        editMarker(marker.id);
      });
      pin.addEventListener('mousedown', function (event) {
        startDrag(event, marker.id);
      });
      pin.addEventListener('mouseenter', function () {
        hoveredMarkerId = Number(marker.id);
        setTableHoverState(hoveredMarkerId, true);
      });
      pin.addEventListener('mouseleave', function () {
        setTableHoverState(Number(marker.id), false);
        hoveredMarkerId = null;
      });
      layer.appendChild(pin);
    });
  }

  function renderTable() {
    tableBody.innerHTML = '';
    (data.markers || []).forEach(function (marker) {
      var tr = document.createElement('tr');
      tr.className = 'adm-list-table-row';
      tr.dataset.markerId = String(marker.id);
      tr.innerHTML =
        '<td class="adm-list-table-cell"><a href="' + marker.editUrl + '" target="_blank">' + escapeHtml(marker.name || '') + '</a></td>' +
        '<td class="adm-list-table-cell">' + marker.x + '</td>' +
        '<td class="adm-list-table-cell">' + marker.y + '</td>' +
        '<td class="adm-list-table-cell">' +
        '<button type="button" class="adm-btn js-edit">Переименовать</button> ' +
        '<button type="button" class="adm-btn js-delete">Удалить</button>' +
        '</td>';
      tr.querySelector('.js-edit').addEventListener('click', function () {
        editMarker(marker.id);
      });
      tr.querySelector('.js-delete').addEventListener('click', function () {
        deleteMarker(marker.id);
      });
      tr.addEventListener('mouseenter', function () {
        setPinHoverState(Number(marker.id), true);
      });
      tr.addEventListener('mouseleave', function () {
        setPinHoverState(Number(marker.id), false);
      });
      tableBody.appendChild(tr);
    });
    if (hoveredMarkerId !== null) {
      setTableHoverState(hoveredMarkerId, true);
    }
  }

  function setTableHoverState(markerId, enabled) {
    var row = tableBody.querySelector('tr[data-marker-id="' + String(markerId) + '"]');
    if (!row) return;
    row.classList.toggle('abcwww-markers-editor__row-hover', !!enabled);
  }

  function setPinHoverState(markerId, enabled) {
    var pin = layer.querySelector('.abcwww-markers-editor__pin[data-marker-id="' + String(markerId) + '"]');
    if (!pin) return;
    pin.classList.toggle('is-linked-hover', !!enabled);
  }

  function findMarker(id) {
    return (data.markers || []).find(function (x) { return Number(x.id) === Number(id); }) || null;
  }

  function editMarker(id) {
    var marker = findMarker(id);
    if (!marker) return;
    var name = prompt('Название метки', marker.name || '');
    if (name === null) return;

    api('updateMarker', {
      id: id,
      name: name
    }).then(function (updateRes) {
      if (!updateRes.success) {
        alert(updateRes.error || 'Ошибка обновления');
        return;
      }
      marker.name = name;
      drawMarkers();
      renderTable();
      focusMarker(id);
    });
  }

  function startDrag(event, markerId) {
    if (event.button !== 0) return;
    var marker = findMarker(markerId);
    if (!marker) return;
    var pin = event.currentTarget;
    event.preventDefault();

    dragState = {
      marker: marker,
      pin: pin,
      moved: false
    };
    pin.classList.add('is-dragging');

    window.addEventListener('mousemove', onDragMove);
    window.addEventListener('mouseup', onDragEnd);
  }

  function onDragMove(event) {
    if (!dragState) return;
    dragState.moved = true;
    var p = getClickCoords(event);
    dragState.marker.x = p.x;
    dragState.marker.y = p.y;
    dragState.pin.style.left = p.x + '%';
    dragState.pin.style.top = p.y + '%';
  }

  function onDragEnd() {
    if (!dragState) return;
    var marker = dragState.marker;
    var moved = dragState.moved;
    dragState.pin.classList.remove('is-dragging');
    dragState = null;
    window.removeEventListener('mousemove', onDragMove);
    window.removeEventListener('mouseup', onDragEnd);

    if (!moved) return;
    api('updateMarker', { id: marker.id, x: marker.x, y: marker.y }).then(function (res) {
      if (!res.success) {
        alert(res.error || 'Ошибка обновления координат');
        return;
      }
      renderTable();
      if (hoveredMarkerId !== null) {
        setTableHoverState(hoveredMarkerId, true);
      }
    });
  }

  function deleteMarker(id) {
    if (!confirm('Удалить метку?')) return;
    api('deleteMarker', { id: id }).then(function (res) {
      if (!res.success) {
        alert(res.error || 'Ошибка удаления');
        return;
      }
      data.markers = (data.markers || []).filter(function (x) { return Number(x.id) !== Number(id); });
      drawMarkers();
      renderTable();
    });
  }

  function normalizePercent(val) {
    if (!isFinite(val)) return 0;
    var normalized = Math.max(0, Math.min(100, Math.round(val * 100) / 100));
    return applySnap(normalized);
  }

  function applySnap(value) {
    var v = Number(value);
    if (!isFinite(v)) return 0;

    // Soft magnet to image borders.
    if (v <= EDGE_SNAP_THRESHOLD) {
      return 0;
    }
    if (v >= 100 - EDGE_SNAP_THRESHOLD) {
      return 100;
    }

    // Grid step snapping for easier precise positioning.
    var stepped = Math.round(v / SNAP_STEP) * SNAP_STEP;
    return Math.max(0, Math.min(100, Math.round(stepped * 100) / 100));
  }

  function getClickCoords(event) {
    var rect = image.getBoundingClientRect();
    if (!rect.width || !rect.height) {
      rect = board.getBoundingClientRect();
    }
    var x = ((event.clientX - rect.left) / rect.width) * 100;
    var y = ((event.clientY - rect.top) / rect.height) * 100;
    return { x: normalizePercent(x), y: normalizePercent(y) };
  }

  board.addEventListener('dblclick', function (event) {
    var p = getClickCoords(event);
    var name = prompt('Название новой метки', 'Новая метка');
    if (name === null) return;

    api('createMarker', {
      iblock_id: iblockId,
      section_id: sectionId,
      name: name,
      x: p.x,
      y: p.y
    }).then(function (res) {
      if (!res.success || !res.id) {
        alert(res.error || 'Ошибка создания');
        return;
      }
      var editUrl = '/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=' + encodeURIComponent(iblockId) +
        '&type=' + encodeURIComponent(iblockType) +
        '&ID=' + encodeURIComponent(res.id) + '&lang=ru&find_section_section=' + encodeURIComponent(sectionId);
      data.markers.push({ id: Number(res.id), name: name, x: p.x, y: p.y, editUrl: editUrl });
      drawMarkers();
      renderTable();
      focusMarker(Number(res.id));
    });
  });

  function focusMarker(id) {
    var marker = findMarker(id);
    if (!marker) return;
    var pin = layer.querySelector('.abcwww-markers-editor__pin[data-marker-id="' + String(id) + '"]');
    if (!pin) return;
    pin.classList.add('is-dragging');
    setTimeout(function () { pin.classList.remove('is-dragging'); }, 250);

    var boardRect = board.getBoundingClientRect();
    var xPx = (marker.x / 100) * boardRect.width;
    var yPx = (marker.y / 100) * boardRect.height;
    board.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (window.scrollBy) {
      window.scrollBy({ top: yPx - boardRect.height / 2, left: xPx - boardRect.width / 2, behavior: 'smooth' });
    }
  }

  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  drawMarkers();
  renderTable();
  bindLayerSync();
})();

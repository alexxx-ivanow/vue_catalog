(function () {
  function injectButton() {
    var cfg = window.AbcwwwMarkersAdmin || {};
    if (!cfg.iblockId || !cfg.sectionId) return false;

    var id = 'abcwww-markers-editor-btn';
    if (document.getElementById(id)) return true;

    var button = document.createElement('a');
    button.id = id;
    button.className = 'adm-btn';
    button.target = '_blank';
    button.href = '/bitrix/admin/abcwww_markers_editor.php?IBLOCK_ID=' +
      encodeURIComponent(cfg.iblockId) + '&SECTION_ID=' + encodeURIComponent(cfg.sectionId) +
      '';
    button.textContent = 'Открыть визуальный редактор меток';

    var rightBlock = document.querySelector(
      '.adm-detail-toolbar-right, .adm-context-menu, .adm-header-btns'
    );
    if (rightBlock) {
      rightBlock.appendChild(button);
      return true;
    }

    var target = document.querySelector('.adm-detail-toolbar, .adm-detail-title, .adm-workarea');
    if (target && target.parentNode) {
      var wrap = document.createElement('div');
      wrap.style.margin = '10px 0';
      wrap.appendChild(button);
      target.parentNode.insertBefore(wrap, target.nextSibling);
      return true;
    }

    if (document.body) {
      document.body.appendChild(button);
      return true;
    }

    return false;
  }

  function boot() {
    if (injectButton()) return;
    setTimeout(injectButton, 200);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.addEventListener('load', injectButton);
})();

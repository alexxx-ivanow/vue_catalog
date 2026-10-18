# abcwww.markers

MVP module for interactive markers on image in Bitrix.

## Includes

- Bitrix module `abcwww.markers`
- Component `abcwww:markers`
- Visual editor page in admin section:
  - `/bitrix/admin/abcwww_markers_editor.php`
- Data-attribute render mode:
  - `<div data-abcwww-snippet="markers" data-id="{SECTION_ID}" data-iblock-id="{IBLOCK_ID}"></div>`

## Data model

- Iblock section stores scheme defaults in UF fields:
  - `UF_MARKER_IMAGE`, `UF_MARKER_MAIN_COLOR`, `UF_MARKER_ICON`, `UF_MARKER_ICON_PC`, `UF_MARKER_ICON_MB`, `UF_MARKER_ACTIVE_COLOR`
- Iblock element stores marker data in UF fields:
  - `UF_MARKER_X`, `UF_MARKER_Y`, `UF_MARKER_MODAL_ONLY`, `UF_MARKER_COLOR`, `UF_MARKER_HOVER_COLOR`, `UF_MARKER_OPENED`, `UF_MARKER_HIDE_MORE`, `UF_MARKER_BTN_TEXT`, `UF_MARKER_BTN_COLOR`, `UF_MARKER_URL`
- Preview/Detail fields are reused for announcement and modal content.

## Setup

1. Install module.
2. Open module options and set markers `IBLOCK_ID`.
3. Click "Create/update user fields".
4. Fill section UF fields and create marker elements in this leaf section.
5. Use component or snippet mode on frontend.

## Notes

- Double click on editor image creates marker by click coordinates in percent.
- Cache path: `/abcwww_markers/`, auto-cleared by iblock update events.
- HTML content is sanitized before frontend output.

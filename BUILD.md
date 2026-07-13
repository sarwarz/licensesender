# Building the React Admin UI

## Prerequisites

- Node.js 18+ and npm

## Build

```bash
cd wp-content/plugins/licensesender
npm install
npm run build
```

Compiled assets are output to `admin/build/` (manifest at `admin/build/.vite/manifest.json`):
- `licenses.js`, `settings.js`, `download-links.js`, `activation-guides.js`
- `manifest.json` (used by PHP asset loader)
- Shared CSS bundle

## Development

```bash
npm run dev
```

Re-run `npm run build` before packaging a release ZIP.

## Feature flag

- **React UI (default):** `ls_admin_ui_version` = `react` (when `admin/build/.vite/manifest.json` exists)
- **Legacy rollback:** set option `ls_admin_ui_version` to `legacy` in the database

```sql
UPDATE wp_options SET option_value = 'legacy' WHERE option_name = 'ls_admin_ui_version';
```

## QA checklist

- License Keys: stats, search, pagination, copy, export CSV, edit sheet, change sheet
- Settings: all 6 tabs save, API ping, test email
- Download Links: list, create, edit, delete
- Activation Guides: list, add/edit text guide, delete
- Rollback: legacy UI loads when flag is `legacy`
- WP admin sidebar unaffected outside `#ls-app-root`

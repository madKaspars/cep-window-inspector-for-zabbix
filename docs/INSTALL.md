# Installation and removal

This module is for educational use in an isolated lab. Read the [README](../README.md), [compatibility requirements](BEHAVIOR.md) and [disclaimer](../DISCLAIMER.md) first. Validated with Zabbix **8.0.0rc3**.

## Locate the frontend

Use the frontend directory containing `zabbix.php`, `include/` and `modules/`. For example, it may be `/usr/share/zabbix/ui`; custom and distribution installations can use a different location. Verify the actual path rather than assuming this example applies.

## Install

1. Back up the frontend and arrange a recovery route outside the web interface.
2. Verify the release ZIP against its `SHA256SUMS`, then extract it outside the web root.
3. Copy its `cep-window-inspector/` directory into the frontend's `modules/` directory. The final path must be `modules/cep-window-inspector/manifest.json`.
4. Ensure the web service can read the files using the normal ownership and permissions for your installation. World-writable permissions are not required.
5. Sign in as a Super admin. Open **Administration → General → Modules**, select **Scan directory**, and enable **CEP Window Inspector**.
6. Open **Monitoring → CEP windows**. Test a known lab window and its Table/Timeline views.

GitHub-generated source archives have a different outer folder name. Rename the folder containing `manifest.json` to `cep-window-inspector` before copying it. Do not copy an extra repository folder inside the module directory.

No core frontend files are edited. The module adds no tables, migrations or background workers and does not modify events or rules. Native configuration links remain subject to Zabbix's own controls.

## Disable or remove

Disable **CEP Window Inspector** in Administration → General → Modules. To remove its files, remove only `modules/cep-window-inspector/` and rescan as appropriate. No module-owned database tables or monitoring history need deletion.

## Troubleshooting

- **Menu absent:** confirm that the module is enabled and the user is a Super admin.
- **Module not found:** verify the folder level and `manifest.json`; avoid nested folders or duplicate IDs.
- **No windows:** only current saved CEP windows are shown. Check the selected filter and whether the lab has a saved window.
- **Unavailable history or timing:** read the explanation in the view and [data semantics](BEHAVIOR.md); missing stored history cannot be reconstructed.
- **Missing schema/helper errors:** verify a matching CEP-enabled server, database and frontend. Do not alter database schema to make an incompatible build fit this module.

Report minimal synthetic reproductions through the project's issue tracker. Do not attach credentials or real monitoring data.

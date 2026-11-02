# Contributing

Use synthetic data and an isolated lab. Include the exact Zabbix server/frontend versions, database engine/version, PHP version, module version and steps to reproduce. Remove credentials, hostnames, monitoring data and other private details from reports. For vulnerabilities, see [SECURITY.md](SECURITY.md).

## Local checks

Requirements: PHP 8.4 CLI with PDO SQLite, Node.js 20 or later, Python 3.10 or later, and the official Zabbix 8.0.0rc3 frontend source. These are development tools; users install the module into their existing Zabbix frontend without installing Node or Python there.

From the repository root:

```sh
git clone https://github.com/zabbix/zabbix.git .cache/zabbix
git -C .cache/zabbix checkout 090f06dc325f1629c5cdc2bb3354a184e15e55d4
find Module.php actions includes views tests -name '*.php' -exec php -l '{}' \;
php tests/changes.php .cache/zabbix/ui
php tests/module.php .cache/zabbix/ui
node --check assets/js/timeline.js
node --check assets/js/changes.js
node tests/timeline.cjs
node tests/changes.cjs
python3 scripts/package_release.py
```

`tests/changes.php` includes the core PHP regression suite. `tests/module.php` installs a temporary copy under `modules/cep-window-inspector` and verifies the manifest, namespace and controllers using RC3's native module loader. The fixtures use SQLite adapters and actual Zabbix HTML/helper classes from the pinned checkout, not a running Zabbix database. See [VALIDATION.md](VALIDATION.md) for the scope and limits.

Changes to interactive behaviour also need browser checks on a compatible lab installation. Check Table as default, timeline scaling, recovery/expiry distinction, cause/symptom roles, rule-specific history, click-only popups, keyboard access, light/dark themes and in-place refresh with retained controls. Do not claim production database or browser coverage from unit tests alone.

## Design and licensing

Keep Super-admin-only access, read-only queries, output escaping, exact string IDs and bounded result pages. Do not infer admission times or window-specific history where the stored schema does not provide them. Keep native Zabbix controls and symbols, meaningful status colours and accessible keyboard behaviour.

Contributions must be compatible with GNU AGPL v3 only. Submit only material you are entitled to contribute, identify external sources and preserve their notices. Retain original project credit for **Kaspars Mednis (madKaspars)** and add your own contribution credit where appropriate. Disclose material AI assistance and review generated changes.

Maintenance and review are voluntary. A contribution or issue does not create a support or response-time commitment.

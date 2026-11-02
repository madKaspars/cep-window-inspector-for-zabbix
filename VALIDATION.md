# Validation — Zabbix 8.0.0rc3

Validated on **2026-11-02**, module version **1.5.7**.

## RC3 source

- Official release tag: [8.0.0rc3](https://github.com/zabbix/zabbix/tree/8.0.0rc3).
- Pinned commit: `090f06dc325f1629c5cdc2bb3354a184e15e55d4`.
- The checkout's `ui/include/defines.inc.php` identifies **8.0.0rc3**. CI checks this version explicitly before running tests.
- Kaspars Mednis has also tested the module on an RC3 installation.

## Automated validation

- **Fresh module loading:** RC3's actual module manager loads `modules/cep-window-inspector`, accepts module ID `cep-window-inspector`, resolves `Modules\CepWindowInspector\Module`, and resolves both action controller classes.
- **PHP regression and history suites:** run with PHP 8.4, actual RC3 HTML/helper classes and synthetic SQLite/controller adapters. Checks cover access restrictions, input validation, exact large IDs, keyset paging, escaping, recovery/expiry calculations, cause/symptom relationships, rule-specific history, cursor isolation and bounded results.
- **RC3 test bootstrap:** synthetic user data and the native partial directory provide RC3's footer context. Optional product banners are disabled in the CLI fixture; module code does not change their configuration.
- **Schema review:** all 10 synthetic database tables and their queried columns were checked against the official RC3 `create/src/schema.tmpl`. CEP constants and icons are present in RC3.
- **JavaScript:** syntax and both test suites pass, including 120 time-range/width/timezone cases, DST, recovery geometry, marker clustering and update/change counts.
- **Packaging:** the complete-source ZIP uses `cep-window-inspector/`, includes all manifest assets/controllers/views and passes link, licence, integrity and byte-parity checks. Repeated builds produce the same SHA-256.

The [Validate workflow](https://github.com/madKaspars/cep-window-inspector-for-zabbix/actions/workflows/validate.yml) runs the PHP, native module-loading, JavaScript and packaging checks against the pinned RC3 source. Reproduction commands are in [CONTRIBUTING.md](CONTRIBUTING.md).

## Preview and scope

Screenshots use synthetic storage-monitoring data: five staggered event times with one-hour retention, two recoveries and recorded CEP updates. The table preview uses the light theme. The timeline illustration enables CEP updates; the module defaults to Off.

Local SQLite and controller adapters are not a production MySQL/MariaDB/PostgreSQL load test or a complete live-session integration test. Production worker timing, independent security review and compatibility with other Zabbix versions are not claimed. Maintainer testing on RC3 is additional evidence.

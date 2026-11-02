# Release publication

Project repository: [madKaspars/cep-window-inspector-for-zabbix](https://github.com/madKaspars/cep-window-inspector-for-zabbix). Publishing source commits and publishing a versioned release are separate steps.

## Repository and release settings

| Field | Value |
| --- | --- |
| Owner | `madKaspars` |
| Repository | `cep-window-inspector-for-zabbix` |
| Visibility | Public |
| Description | Educational read-only CEP window inspector for Zabbix, with tables, timelines and recorded update history. |
| Topics | `zabbix`, `zabbix-module`, `cep`, `monitoring`, `education`, `php` |
| Original project author | Kaspars Mednis (madKaspars) |
| Licence | GNU AGPL v3 only |
| First release tag | `v1.5.7` |
| First release title | CEP Window Inspector — Educational preview |
| Release status | Prerelease |

The repository is public. The versioned release is published explicitly after review.

## Publish a release

1. Review the README, original-author credit, AI-generation disclosure and educational-use notices.
2. Run the checks in [CONTRIBUTING.md](../CONTRIBUTING.md). The checks must use the pinned official Zabbix 8.0.0rc3 source.
3. Build with `python3 scripts/package_release.py`. Check the archive and `dist/SHA256SUMS`.
4. Review the files to be committed. Exclude local caches, credentials, monitoring data and `dist/`. The screenshot fixtures are synthetic.
5. Push the reviewed source changes to the project repository.
6. Wait for GitHub validation, create a draft release for `v1.5.7`, and use [RELEASE.md](RELEASE.md) as its body. Keep the prerelease flag enabled for this educational preview.
7. Review the draft and explicitly publish when ready. The release workflow then builds and attaches the ZIP and checksums. Download and verify the uploaded assets.

## Workflows

`validate.yml` runs on pushes, pull requests or manual invocation. It lints PHP, runs PHP/JavaScript regression checks with the pinned official Zabbix 8.0.0rc3 checkout, and verifies deterministic packaging. It has read-only repository permissions and no publication step.

`release.yml` runs only after a GitHub release is explicitly **published**. It checks that the release tag matches `manifest.json`, rebuilds the archive and uploads it with `SHA256SUMS`. Only that job has permission to write release assets; it does not create releases. If Actions is unavailable, build locally and attach those two files manually to the reviewed release.

The package contains complete project source and notices, not just compiled assets. Preserve applicable notices and comply with the GNU AGPL when distributing modified versions or providing remote access as applicable. See [NOTICE.md](../NOTICE.md).

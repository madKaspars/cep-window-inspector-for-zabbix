# CEP Window Inspector — Educational preview

Version **1.5.7** of a read-only Zabbix frontend module for exploring saved CEP windows, member events and recorded CEP updates.

> **FOR EDUCATIONAL PURPOSES ONLY.** Experimental community software for learning, labs and demonstrations; not recommended for production or business-critical monitoring. This guidance does not add restrictions to the GNU AGPL licence.

Original project author: **Kaspars Mednis (madKaspars)**. Implementation generated with ChatGPT and OpenAI Codex under the author's direction. No independent security audit or production certification is claimed.

Tested with **Zabbix 8.0.0rc3**, including automated checks against the official RC3 source and maintainer testing on an RC3 installation. Other builds require compatibility testing.

Features include default Table and optional Timeline tabs, recovery markers, estimated window expiry, cause/symptom roles, rule-filtered CEP update popups, optional in-bar update symbols and an in-place Refresh that retains view settings. History popups open only on click or keyboard activation.

Download `cep-window-inspector-for-zabbix-v1.5.7.zip` and verify it against `SHA256SUMS`. The ZIP contains `cep-window-inspector/`, including complete source, tests, documentation and notices. Place that folder in your actual frontend's `modules/` directory, scan and enable the module, then open Monitoring → CEP windows as a Super admin. Follow the [installation guide](https://github.com/madKaspars/cep-window-inspector-for-zabbix/blob/main/docs/INSTALL.md).

This view shows current saved membership, not historical playback. Expiry is an estimate. CEP update logs identify the event and rule but not a window instance; different windows of the same rule may share history.

Licensed under GNU AGPL v3 only. It is not affiliated with, endorsed by, or supported by Zabbix LLC or Zabbix SIA. Zabbix is a registered trademark of Zabbix LLC. Provided as is, without warranty to the extent permitted by law, with voluntary maintenance and no guaranteed support. See [LICENSE](https://github.com/madKaspars/cep-window-inspector-for-zabbix/blob/main/LICENSE), [NOTICE.md](https://github.com/madKaspars/cep-window-inspector-for-zabbix/blob/main/NOTICE.md) and [DISCLAIMER.md](https://github.com/madKaspars/cep-window-inspector-for-zabbix/blob/main/DISCLAIMER.md).

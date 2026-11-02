# Disclaimer and intended use

**For educational purposes only.** CEP Window Inspector is an experimental community project intended for learning, isolated labs and demonstrations. It is not recommended for production, business-critical or safety-critical monitoring. This suitability guidance is not an additional restriction on rights granted by the GNU AGPL.

The implementation was generated with ChatGPT and OpenAI Codex under the direction of original project author Kaspars Mednis (madKaspars). AI output can contain errors. Passing tests and maintainer-reported compatibility are not an independent security audit, production certification or guarantee of accuracy.

The module runs within a privileged Zabbix frontend and reads its database. Its queries are read-only, but defects or expensive queries may still affect frontend availability, performance or confidentiality. Displayed membership is a saved state, recovery history may be missing, and expiry times are estimates. Do not use these views as the sole basis for operational decisions. Native event and rule links open Zabbix's own pages and permissions.

The project is independent of Zabbix LLC, Zabbix SIA and OpenAI. None of these organisations is represented as its author, sponsor, auditor or support provider.

The software and documentation are provided **as is**, without warranties of any kind, including accuracy, security, compatibility, merchantability or fitness for a particular purpose, to the extent permitted by applicable law. Liability exclusions and limitations apply only to the extent permitted by law; nothing here excludes rights or liabilities that cannot lawfully be excluded. The GNU AGPL's terms, including sections 15–17, govern. This document does not amend the licence or guarantee immunity from legal claims.

Maintenance, issue triage and contributions are voluntary. There is no guaranteed support period, response time or obligation to release updates. See [SECURITY.md](SECURITY.md) for reporting guidance.

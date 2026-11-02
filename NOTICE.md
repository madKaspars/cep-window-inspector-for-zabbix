# Attribution, licence and provenance

## Original project author

**Kaspars Mednis (madKaspars)** is the original project author and maintainer of CEP Window Inspector.

GitHub profile: <https://github.com/madKaspars>

This is project-origin credit: the author directed the project, selected its behaviour and interface, and tested the module. It is not a representation that every source line was manually written by the author.

Please preserve this original project attribution in forks and redistributions. Fork maintainers may add their own names, changes and maintenance information. Preserve all applicable copyright, licence and other notices as required by the licence. This request does not create an additional licence restriction or require visible product branding.

## AI generation

The project implementation was generated with ChatGPT and OpenAI Codex under the project author's direction. AI-generated work may contain defects or inaccurate instructions. No independent security audit or production certification is claimed. No particular model or exclusively human authorship is asserted.

Project attribution and a licence notice do not establish copyright in material that is not copyrightable under applicable law. The licence applies to the extent that relevant rights exist and can be licensed; third-party rights and notices remain with their respective holders.

## Licence

The project is distributed under **GNU Affero General Public License version 3 only** (`AGPL-3.0-only`). The complete unmodified text is in [LICENSE](LICENSE). When conveying covered material, preserve notices and comply with the source requirements. Section 13 addresses remote network interaction with modified covered software. The licence text governs.

The educational-purpose notice describes intended use and suitability. It does not prohibit uses permitted by the AGPL or add a noncommercial restriction. See [DISCLAIMER.md](DISCLAIMER.md).

## Zabbix and external material

- The module depends on the installed Zabbix frontend, its native HTML classes, theme styles, icons and database connection. It does not bundle a complete Zabbix frontend, icon-font binaries or external chart libraries.
- Automated validation uses the official **Zabbix 8.0.0rc3** release, commit `090f06dc325f1629c5cdc2bb3354a184e15e55d4`, from <https://github.com/zabbix/zabbix>. That source carries its own Zabbix SIA notices and AGPL licence. The test checkout is an external dependency, not part of this archive.
- Kaspars Mednis has also tested the module on an RC3 installation.
- Screenshots in `docs/images/` show native Zabbix interface components and module output using synthetic local fixtures. They are illustrations, not monitoring records. Zabbix components retain their original authorship and notices.
- Packaging and licence conventions were adapted from [madKaspars/halloween-theme-for-zabbix](https://github.com/madKaspars/halloween-theme-for-zabbix). The AGPL licence text is identical to that repository's licence; theme-specific installers, fonts and artwork are not bundled here.

Zabbix is a registered trademark of Zabbix LLC. OpenAI names and marks belong to their respective owners. Naming them does not imply affiliation, endorsement, support or a grant of trademark rights.

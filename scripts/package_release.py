#!/usr/bin/env python3
# SPDX-License-Identifier: AGPL-3.0-only
# Original project author: Kaspars Mednis (madKaspars)
# AI-generated implementation; see NOTICE.md for provenance.
"""Build a deterministic, complete-source release ZIP using only the stdlib."""

import hashlib
import json
from pathlib import Path
import re
import stat
import zipfile
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parents[1]
ROOT_FILES = (
    "Module.php", "manifest.json", "README.md", "LICENSE", "NOTICE.md",
    "DISCLAIMER.md", "PRODUCTION-NOTICE.txt", "SECURITY.md", "CONTRIBUTING.md",
    "CHANGELOG.md", "VALIDATION.md", ".gitignore", ".gitattributes",
)
DIRECTORIES = ("actions", "includes", "views", "assets", "tests", "scripts", "docs", ".github")
IGNORED = {".DS_Store", "__pycache__", ".git", "node_modules", ".cache", ".venv"}
EXTENSIONS = {".php", ".js", ".cjs", ".css", ".py", ".md", ".txt", ".png", ".yml", ".yaml"}


def source_files():
    files = [ROOT / name for name in ROOT_FILES]
    for name in DIRECTORIES:
        directory = ROOT / name
        if directory.is_symlink() or not directory.is_dir():
            raise ValueError(f"Missing or linked source directory: {name}")
        for path in sorted(directory.rglob("*")):
            relative = path.relative_to(ROOT)
            if path.is_symlink():
                raise ValueError(f"Symlink is not allowed: {relative}")
            if any(part in IGNORED for part in relative.parts):
                continue
            if path.is_file():
                if path.suffix not in EXTENSIONS:
                    raise ValueError(f"Unexpected source file: {relative}")
                files.append(path)
    for path in files:
        if path.is_symlink() or not path.is_file():
            raise ValueError(f"Missing or linked file: {path.relative_to(ROOT)}")
    return sorted(files, key=lambda path: path.relative_to(ROOT).as_posix())


def validate(files):
    manifest = json.loads((ROOT / "manifest.json").read_text(encoding="utf-8"))
    version = manifest["version"]
    if manifest["id"] != "cep-window-inspector" or not re.fullmatch(r"\d+\.\d+\.\d+", version):
        raise ValueError("Unexpected module ID or version")
    included = {path.resolve() for path in files}
    required = [ROOT / "NOTICE.md", ROOT / "docs/INSTALL.md"]
    for kind, names in manifest["assets"].items():
        required.extend(ROOT / "assets" / kind / name for name in names)
    for action in manifest["actions"].values():
        required.append(ROOT / "actions" / (action["class"] + ".php"))
        if "view" in action:
            required.append(ROOT / "views" / (action["view"] + ".php"))
    for path in required:
        if path.resolve() not in included:
            raise ValueError(f"Required file missing from package: {path}")
    licence = (ROOT / "LICENSE").read_bytes()
    blob = b"blob " + str(len(licence)).encode() + b"\0" + licence
    if hashlib.sha1(blob).hexdigest() != "be3f7b28e564e7dd05eaf59d64adba1a4065ac0e":
        raise ValueError("LICENSE differs from the agreed unmodified AGPL v3 text")
    for path in files:
        if path.suffix != ".md":
            continue
        text = path.read_text(encoding="utf-8")
        for target in re.findall(r"\]\(([^\s)]+)\)", text):
            link = urlsplit(target)
            if link.scheme or link.netloc or not link.path:
                continue
            resolved = (path.parent / unquote(link.path)).resolve()
            if resolved not in included:
                raise ValueError(f"Broken or unpackaged link in {path.name}: {target}")
    return version


def main():
    files = source_files()
    version = validate(files)
    output = ROOT / "dist"
    if output.is_symlink():
        raise ValueError("dist must not be a symlink")
    output.mkdir(exist_ok=True)
    archive = output / f"cep-window-inspector-for-zabbix-v{version}.zip"
    checksums = output / "SHA256SUMS"
    if archive.is_symlink() or checksums.is_symlink():
        raise ValueError("Release outputs must not be symlinks")
    # Stored entries avoid differences between compression-library versions.
    with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_STORED) as package:
        for path in files:
            relative = path.relative_to(ROOT).as_posix()
            entry = zipfile.ZipInfo(f"cep-window-inspector/{relative}", (2026, 1, 1, 0, 0, 0))
            entry.create_system = 3
            entry.external_attr = (stat.S_IFREG | 0o644) << 16
            package.writestr(entry, path.read_bytes())
    with zipfile.ZipFile(archive) as package:
        if package.testzip() is not None:
            raise ValueError("ZIP integrity check failed")
        for path in files:
            member = "cep-window-inspector/" + path.relative_to(ROOT).as_posix()
            if package.read(member) != path.read_bytes():
                raise ValueError(f"Archive/source mismatch: {member}")
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    checksums.write_text(f"{digest}  {archive.name}\n", encoding="utf-8")
    print(f"Created {archive.name}: {len(files)} files; SHA256 {digest}")


if __name__ == "__main__":
    main()

import json
from pathlib import Path, PurePosixPath
import re
import stat
import sys
import zipfile

archive, destination = map(Path, sys.argv[1:3])
seen = set()
total = 0
allowed_roots = {"backend", "index.html", "styles.css", "app.mjs", "portfolio-data.mjs", "favicon.svg", "VERSION"}
with zipfile.ZipFile(archive) as bundle:
    entries = bundle.infolist()
    if len(entries) > 10000:
        raise ValueError("Archive entry budget exceeded")
    for entry in entries:
        name = entry.orig_filename
        path = PurePosixPath(name)
        components = path.parts
        mode = entry.external_attr >> 16
        if not components or path.is_absolute() or "\\" in name or ":" in name or "\x00" in name \
                or any(part in (".", "..") or part.endswith((" ", ".")) for part in components) \
                or name.casefold().rstrip("/") in seen or entry.flag_bits & 1 \
                or (stat.S_IFMT(mode) not in (0, stat.S_IFREG, stat.S_IFDIR)) \
                or any(re.fullmatch(r"(con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\..*)?", part, re.I) for part in components):
            raise ValueError("Unsafe release archive entry")
        seen.add(name.casefold().rstrip("/"))
        total += entry.file_size
        if entry.file_size > 50 * 1024 * 1024 or total > 200 * 1024 * 1024:
            raise ValueError("Archive expansion budget exceeded")
        if components[0] not in allowed_roots or any(part.startswith(".env") for part in components) \
                or name.startswith(("backend/storage/", "backend/vendor/", "backend/bootstrap/cache/")):
            raise ValueError("Release contains forbidden private/runtime paths")
    for entry in entries:
        target = destination.joinpath(*PurePosixPath(entry.filename).parts)
        if entry.is_dir():
            target.mkdir(parents=True, exist_ok=True)
        else:
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(bundle.read(entry))
for required in ("VERSION", "index.html", "backend/artisan", "backend/composer.lock", "backend/public/index.php", "backend/config/platform.php"):
    if not (destination / required).is_file():
        raise ValueError(f"Required application file missing: {required}")
manifest = json.loads((archive.parent / "portfolio-manifest.json").read_text())
if (destination / "VERSION").read_text().strip() != manifest["version"]:
    raise ValueError("VERSION does not match the signed release")
print("Archive paths, expansion, CRC and required application files verified.")

import hashlib
import json
from pathlib import Path
import re
import subprocess
import sys
import zipfile

root = Path(__file__).resolve().parents[1]
destination = Path(sys.argv[1]).resolve()
engine = sys.argv[2]
minimum, maximum = sys.argv[3:5]
version = (root / "VERSION").read_text().strip()
# The code's schema comes from the single source of truth the application uses.
config = (root / "backend" / "config" / "platform.php").read_text()
found = re.search(r"'schema_version'\s*=>\s*(\d+)\s*,", config)
if not found:
    raise ValueError("platform.schema_version not found in backend/config/platform.php")
schema_to = int(found.group(1))
# Optional fifth argument: the schema of the release being upgraded from.
# Leave it out for schema-preserving releases; pass it for a reviewed migration release.
schema_from = int(sys.argv[5]) if len(sys.argv) > 5 else schema_to
if not 1 <= schema_from <= schema_to:
    raise ValueError("schema_from must be a positive version no newer than the code's schema")
if not re.fullmatch(r"(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)", version):
    raise ValueError("Publish a stable VERSION, not a development checkout")
if engine not in ("mysql", "mariadb") or not all(re.fullmatch(r"\d+\.\d+\.\d+", v) for v in (minimum, maximum)):
    raise ValueError("Provide reviewed database-family compatibility bounds")
destination.mkdir(parents=True, exist_ok=True)
paths = subprocess.check_output(["git", "ls-files", "-z"], cwd=root).decode().split("\0")
public = {"VERSION", "index.html", "styles.css", "app.mjs", "portfolio-data.mjs", "favicon.svg"}
with zipfile.ZipFile(destination / "portfolio.zip", "w", zipfile.ZIP_DEFLATED) as archive:
    for path in sorted(paths):
        if path not in public and not path.startswith("backend/"):
            continue
        parts = Path(path).parts
        if any(part.startswith(".env") for part in parts) or path.startswith((
            "backend/vendor/", "backend/node_modules/", "backend/storage/", "backend/bootstrap/cache/"
        )):
            continue
        source = root / path
        if source.is_symlink():
            raise ValueError("Release source contains a symlink")
        archive.write(source, arcname=path)
archive = (destination / "portfolio.zip").read_bytes()
if len(archive) > 50 * 1024 * 1024:
    raise ValueError("Release archive exceeds deployment budget")
manifest = {
    "repository": "hamidreza-wq/hamidPortfolio", "version": version, "tag": f"v{version}",
    "notes": "Reviewed portfolio application update. " + (
        "Database schema is unchanged." if schema_from == schema_to
        else f"Database schema {schema_from} -> {schema_to}: requires a reviewed migration deployment, not one-click Update."),
    "released_at": subprocess.check_output(["git", "show", "-s", "--format=%cI", "HEAD"], cwd=root).decode().strip(),
    "asset": {"name": "portfolio.zip", "sha256": hashlib.sha256(archive).hexdigest()},
    "minimum_php": "8.3.0", "required_extensions": ["pdo_mysql", "fileinfo", "sodium", "zip", "curl", "dom", "mbstring"],
    "database_engine": engine, "mysql_min": minimum, "mysql_max_exclusive": maximum,
    "schema_from": schema_from, "schema_to": schema_to,
}
(destination / "portfolio-manifest.json").write_bytes(json.dumps(manifest, indent=2).encode())
print("Release package and exact-byte manifest created. Sign and verify before publishing.")

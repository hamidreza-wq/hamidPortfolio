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
    "notes": "Reviewed portfolio application update. Database schema is unchanged.",
    "released_at": subprocess.check_output(["git", "show", "-s", "--format=%cI", "HEAD"], cwd=root).decode().strip(),
    "asset": {"name": "portfolio.zip", "sha256": hashlib.sha256(archive).hexdigest()},
    "minimum_php": "8.3.0", "required_extensions": ["pdo_mysql", "fileinfo", "sodium", "zip", "curl", "dom", "mbstring"],
    "database_engine": engine, "mysql_min": minimum, "mysql_max_exclusive": maximum,
    "schema_from": 3, "schema_to": 3,
}
(destination / "portfolio-manifest.json").write_bytes(json.dumps(manifest, indent=2).encode())
print("Release package and exact-byte manifest created. Sign and verify before publishing.")

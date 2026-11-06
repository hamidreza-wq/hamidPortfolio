from pathlib import Path
import json
import stat
import subprocess
import sys
import tempfile
import unittest
import zipfile

SCRIPT = Path(__file__).resolve().parents[1] / "extract-release.py"
REQUIRED = ["VERSION", "index.html", "backend/artisan", "backend/composer.lock",
            "backend/public/index.php", "backend/config/platform.php"]


class ExtractionTests(unittest.TestCase):
    def run_archive(self, extra=None):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "portfolio-manifest.json").write_text(json.dumps({"version": "1.0.0"}))
            with zipfile.ZipFile(root / "portfolio.zip", "w") as archive:
                for name in REQUIRED:
                    archive.writestr(name, "1.0.0" if name == "VERSION" else "reviewed content")
                if extra:
                    name, content = extra
                    if isinstance(name, str):
                        entry = zipfile.ZipInfo()
                        entry.filename = name
                    else:
                        entry = name
                    archive.writestr(entry, content)
            return subprocess.run([sys.executable, str(SCRIPT), str(root / "portfolio.zip"),
                                   str(root / "output")], capture_output=True).returncode

    def test_valid_archive(self):
        self.assertEqual(0, self.run_archive())

    def test_traversal_and_private_runtime_entries(self):
        for name in ["../outside", "/absolute", "backend/../escape", "backend\\escape",
                     "backend/.env", "backend/storage/app/private", "backend/vendor/file",
                     "backend/bootstrap/cache/config.php", "backend/CON.php", "INDEX.HTML"]:
            with self.subTest(name=name):
                self.assertNotEqual(0, self.run_archive((name, "forbidden")))

    def test_symlink(self):
        entry = zipfile.ZipInfo("backend/link")
        entry.create_system = 3
        entry.external_attr = (stat.S_IFLNK | 0o777) << 16
        self.assertNotEqual(0, self.run_archive((entry, "../outside")))


if __name__ == "__main__":
    unittest.main()

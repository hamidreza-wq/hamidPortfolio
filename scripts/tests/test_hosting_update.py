import hashlib
import os
from pathlib import Path
import shlex
import subprocess
import tarfile
import tempfile
import unittest


@unittest.skipIf(os.name == "nt", "Requires a POSIX hosting runtime")
class HostingUpdateTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(
            prefix="portfolio-runner-test-", dir=str(Path(__file__).resolve().parent),
        )
        self.base = Path(self.temporary.name)
        self.request = "dd32f6b2-e9c2-42d8-921c-cd21f31b61af"
        self.stage = self.base / "shared" / f"update-{self.request}"
        self.previous = self.base / "releases" / "previous"
        self.release = self.base / "releases" / f"v0.3.0-{self.request}"
        self.backup = self.base / "backups" / f"update-{self.request}"
        self.stage.mkdir(parents=True)
        self.backup.parent.mkdir()
        (self.previous / "backend" / "public").mkdir(parents=True)
        (self.previous / "VERSION").write_text("0.2.0-dev\n")
        (self.previous / "backend" / ".env").write_text("test-private-environment\n")
        (self.base / "public_html").symlink_to(self.previous / "backend" / "public")
        uploads = self.base / "shared" / "storage" / "app" / "public"
        uploads.mkdir(parents=True)
        (uploads / "existing-upload").write_text("keep-existing-upload")
        source = Path(__file__).resolve().parents[1] / "hosting-update.sh"
        self.assertNotIn(b"\r", source.read_bytes(), "Hosting Bash scripts must use LF line endings")
        script = source.read_text()
        target = "base=/home/u504051894/domains/hamidbayat.co.uk"
        self.assertEqual(script.count(target), 1)
        self.runner = self.stage / "hosting-update.sh"
        self.runner.write_text(script.replace(target, "base=" + shlex.quote(str(self.base))))
        for name in ("portfolio-manifest.json", "hosting-update.php"):
            (self.stage / name).write_text("{}")
        runtime = self.base / "runtime"
        (runtime / "backend" / "public").mkdir(parents=True)
        (runtime / "VERSION").write_text("0.3.0\n")
        (runtime / "backend" / "artisan").write_text("mock-artisan")
        archive = self.stage / "runtime.tar.gz"
        with tarfile.open(archive, "w:gz") as bundle:
            bundle.add(runtime, arcname=".")
        self.digest = hashlib.sha256(archive.read_bytes()).hexdigest()
        binaries = self.base / "bin"
        binaries.mkdir()
        self.env = {
            key: value for key, value in os.environ.items()
            if key not in ("BASH_ENV", "ENV") and not key.startswith("BASH_FUNC_")
        }
        self.env["PATH"] = str(binaries) + os.pathsep + os.environ["PATH"]
        self.runner.write_text(self.runner.read_text().replace(
            "umask 077\n", "umask 077\nexport PATH=" + shlex.quote(self.env["PATH"]) + "\n",
        ))
        self.mock(binaries, "php", """
if [[ "$1" == */hosting-update.php ]]; then
  printf 'test-backup-options\\n' > "$2/backup-client.cnf"
  printf 'test_database\\n' > "$2/database-name"
elif [[ "$*" == 'artisan view:cache' && "${MOCK_CACHE_FAIL:-0}" == 1 ]]; then
  exit 91
fi
""")
        self.mock(binaries, "mysqldump", """
[[ "${MOCK_DUMP_FAIL:-0}" == 0 ]] || exit 92
echo test-database-backup
""")
        self.mock(binaries, "curl", """
while [[ $# -gt 0 ]]; do
  if [[ "$1" == '-D' ]]; then
    printf 'X-Portfolio-Version: %s\\r\\n' "${MOCK_VERSION:-0.3.0}" > "$2"
    shift
  fi
  if [[ "$1" == 'https://hamidbayat.co.uk/privacy' && "${MOCK_HEALTH_FAIL:-0}" == 1 ]]; then
    exit 93
  fi
  shift
done
echo test-health-response
""")
        self.mock(binaries, "cp", """
if [[ "$1" == */portfolio-manifest.json && "${MOCK_COPY_FAIL:-0}" == 1 ]]; then
  exit 94
fi
exec /bin/cp "$@"
""")

    def tearDown(self):
        self.temporary.cleanup()

    @staticmethod
    def mock(directory, name, body):
        executable = directory / name
        executable.write_text("#!/usr/bin/env bash\nset -euo pipefail\n" + body)
        executable.chmod(0o700)

    def run_update(self, **environment):
        return subprocess.run(
            ["bash", str(self.runner), self.request, "v0.3.0", self.digest, "0.2.0-dev"],
            env={**self.env, **environment}, universal_newlines=True,
            stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=15,
        )

    def assert_preserved(self):
        self.assertEqual((self.base / "public_html").resolve(), self.previous / "backend" / "public")
        self.assertEqual((self.previous / "backend" / ".env").read_text(), "test-private-environment\n")
        self.assertEqual(
            (self.base / "shared" / "storage" / "app" / "public" / "existing-upload").read_text(),
            "keep-existing-upload",
        )
        self.assertFalse((self.stage / "backup-client.cnf").exists())

    def test_success_creates_verified_backups_and_preserves_private_runtime(self):
        result = self.run_update()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertEqual((self.base / "public_html").resolve(), self.release / "backend" / "public")
        self.assertEqual((self.release / "backend" / ".env").read_text(), "test-private-environment\n")
        self.assertTrue((self.release / "backend" / "storage").is_symlink())
        self.assertTrue((self.backup / "database.sql").is_file())
        verification = subprocess.run(
            ["sha256sum", "-c", str(self.backup / "SHA256SUMS")],
            stdout=subprocess.PIPE, stderr=subprocess.PIPE,
        )
        self.assertEqual(verification.returncode, 0)
        self.assertFalse(self.stage.exists())
        self.assertTrue(self.previous.exists())

    def test_database_backup_failure_never_switches_the_public_root(self):
        result = self.run_update(MOCK_DUMP_FAIL="1")
        self.assertNotEqual(result.returncode, 0)
        self.assert_preserved()

    def test_cache_failure_never_switches_the_public_root(self):
        result = self.run_update(MOCK_CACHE_FAIL="1")
        self.assertNotEqual(result.returncode, 0)
        self.assert_preserved()

    def test_health_failure_rolls_back_without_restoring_database(self):
        result = self.run_update(MOCK_HEALTH_FAIL="1")
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("Database was not changed", result.stdout)
        self.assert_preserved()
        self.assertTrue((self.backup / "database.sql").is_file())

    def test_wrong_public_version_rolls_back(self):
        result = self.run_update(MOCK_VERSION="0.2.0-dev")
        self.assertNotEqual(result.returncode, 0)
        self.assert_preserved()

    def test_post_activation_failure_triggers_exit_trap_rollback(self):
        result = self.run_update(MOCK_COPY_FAIL="1")
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("previous release restored", result.stdout)
        self.assert_preserved()

    def test_concurrent_update_is_refused(self):
        with subprocess.Popen(
            ["flock", str(self.base / "shared" / "update.lock"), "bash", "-c", "echo locked; sleep 1"],
            stdout=subprocess.PIPE, universal_newlines=True,
        ) as holder:
            self.assertEqual(holder.stdout.readline().strip(), "locked")
            result = self.run_update()
            self.assertNotEqual(result.returncode, 0)
            self.assertIn("Another update is running", result.stdout)
            self.assert_preserved()
            holder.wait(timeout=5)


if __name__ == "__main__":
    unittest.main()

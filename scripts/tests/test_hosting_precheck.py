from pathlib import Path
import shutil
import subprocess
import unittest

SCRIPT = Path(__file__).resolve().parents[1] / "hosting-update.php"


@unittest.skipIf(shutil.which("php") is None, "Requires PHP")
class HostingPrecheckTest(unittest.TestCase):
    def run_precheck(self, *arguments):
        return subprocess.run(["php", str(SCRIPT), *arguments], capture_output=True, text=True, timeout=30)

    def test_syntax_is_valid(self):
        result = subprocess.run(["php", "-l", str(SCRIPT)], capture_output=True, text=True, timeout=30)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_refusal_reports_its_reason_and_exits_nonzero(self):
        result = self.run_precheck("/tmp/not-a-deployment-stage")
        self.assertEqual(result.returncode, 1)
        self.assertEqual(result.stdout, "")
        self.assertIn("Deployment pre-check failed: RuntimeException: Invalid private deployment stage", result.stderr)

    def test_missing_stage_argument_is_reported(self):
        result = self.run_precheck()
        self.assertEqual(result.returncode, 1)
        self.assertIn("Invalid private deployment stage", result.stderr)


if __name__ == "__main__":
    unittest.main()

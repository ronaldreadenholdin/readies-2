import json
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = shutil.which("php")


@unittest.skipIf(PHP is None, "php CLI is required for PSP conformance tests")
class PspConformanceTests(unittest.TestCase):
    def test_p003_gate_writes_json_and_markdown_report(self):
        with tempfile.TemporaryDirectory() as tmp:
            cmd = [
                PHP,
                str(ROOT / "bob-c-0609/laravel/bin/run-psp-conformance.php"),
                "P003",
                tmp,
            ]
            proc = subprocess.run(cmd, check=True, text=True, capture_output=True)
            report = json.loads(proc.stdout)

            summary = report["summary"]["P003"]
            self.assertEqual(summary["total_checks"], 49)
            self.assertEqual(summary["passed"], 47)
            self.assertEqual(summary["failed"], 2)
            self.assertEqual(summary["score_percent"], 95.92)
            self.assertFalse(summary["eligible_for_cascade"])

            issue_ids = {row["preflight_check_id"] for row in report["conversion_killers"]}
            self.assertEqual(issue_ids, {"P003-PF-002", "P003-PF-004"})

            json_path = Path(report["artifacts"]["json"])
            markdown_path = Path(report["artifacts"]["markdown"])
            self.assertTrue(json_path.is_file())
            self.assertTrue(markdown_path.is_file())
            markdown = markdown_path.read_text()
            self.assertIn("# PSP adapter conformance report", markdown)
            self.assertIn("Webhook Handling", markdown)
            self.assertIn("Eligible for cascade", markdown)

    def test_conversion_killer_reports_amount_minor_unit_mismatch(self):
        proc = subprocess.run(
            [PHP, str(ROOT / "tests/php_conversion_killer_failure.php")],
            check=True,
            text=True,
            capture_output=True,
        )
        issues = json.loads(proc.stdout)
        amount_issue = next(row for row in issues if row["preflight_check_id"] == "amount.minor_units")
        self.assertEqual(amount_issue["killer_category"], "unit/cents")
        self.assertEqual(amount_issue["field_path"]["internal"], "amount.value")
        self.assertEqual(amount_issue["field_path"]["psp"], "amount_cents")
        self.assertEqual(amount_issue["severity"], "blocks cascade")


if __name__ == "__main__":
    unittest.main()

import json
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = shutil.which("php")
FIXTURE_ROOT = ROOT / "bob-c-0609/laravel/resources/psp-conformance/p003"


class PspConformanceFixtureTests(unittest.TestCase):
    def test_recorded_preflight_fixture_matches_checked_in_harness_count(self):
        checks = json.loads((FIXTURE_ROOT / "preflight-checks.json").read_text())
        self.assertEqual(len(checks), 4)
        self.assertEqual([row["status"] for row in checks].count("green"), 2)
        self.assertEqual([row["status"] for row in checks].count("flagged"), 2)

        required_issue_fields = {
            "id",
            "name",
            "connection",
            "expected",
            "actual",
            "field_path",
            "killer_category",
            "why",
            "code_location",
            "severity",
            "suggested_fix",
        }
        for row in checks:
            self.assertTrue(required_issue_fields.issubset(row.keys()))
            self.assertIn("internal", row["field_path"])
            self.assertIn("psp", row["field_path"])

    def test_golden_outputs_follow_normalized_contract(self):
        required = {
            "schema_version",
            "psp_code",
            "operation",
            "merchant_reference",
            "payment_id",
            "psp_reference",
            "status",
            "amount",
            "created_at",
            "updated_at",
            "decline",
            "webhook_event",
            "metadata",
        }
        for path in sorted((FIXTURE_ROOT / "golden").glob("*.json")):
            payload = json.loads(path.read_text())
            self.assertEqual(set(payload.keys()), required)
            self.assertEqual(payload["schema_version"], "readies.psp.normalized.v1")
            self.assertEqual(payload["psp_code"], "P003")
            self.assertRegex(payload["amount"]["currency"], r"^[A-Z]{3}$")
            self.assertEqual(int(round(float(payload["amount"]["value"]) * 100)), payload["amount"]["minor_units"])
            self.assertRegex(payload["created_at"], r"^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$")
            self.assertRegex(payload["updated_at"], r"^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$")
            self.assertIn(payload["decline"]["class"], {"none", "soft", "hard"})
            self.assertIsInstance(payload["metadata"]["cascade_eligible"], bool)

    def test_documented_p003_score_matches_fixture_math(self):
        preflight_total = len(json.loads((FIXTURE_ROOT / "preflight-checks.json").read_text()))
        preflight_failed = 2
        required_field_checks = 4
        endpoint_checks = (10 * 3) + 11
        total = preflight_total + required_field_checks + endpoint_checks
        passed = total - preflight_failed
        self.assertEqual(total, 49)
        self.assertEqual(round((passed / total) * 100, 2), 95.92)


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

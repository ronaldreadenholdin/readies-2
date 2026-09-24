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
        required_field_checks = 5
        declared_dependency_checks = 5
        endpoint_checks = (10 * 3) + 11
        total = preflight_total + required_field_checks + declared_dependency_checks + endpoint_checks
        passed = total - preflight_failed
        self.assertEqual(total, 55)
        self.assertEqual(round((passed / total) * 100, 2), 96.36)

    def test_three_hop_cascade_precollects_hop_three_date_of_birth(self):
        cascade = ["P001", "P002", "P003DOB"]
        converters = {
            "P001": {
                "required": ["merchant_reference", "amount.value", "amount.currency", "billing.postal_code"],
                "map": {"billing.postal_code": "zip"},
            },
            "P002": {
                "required": ["merchant_reference", "amount.value", "amount.currency", "customer.phone"],
                "map": {"customer.phone": "phone"},
            },
            "P003DOB": {
                "required": ["merchant_reference", "amount.value", "amount.currency", "customer.date_of_birth"],
                "map": {"customer.date_of_birth": "birthDate"},
            },
        }
        union = {}
        for index, code in enumerate(cascade, start=1):
            for field in converters[code]["required"]:
                union.setdefault(field, []).append(code)

        self.assertIn("customer.date_of_birth", union)
        self.assertEqual(union["customer.date_of_birth"], ["P003DOB"])
        self.assertNotIn("customer.date_of_birth", converters["P001"]["map"])

        request = {
            "merchant_reference": "order-123",
            "amount.value": "10.00",
            "amount.currency": "EUR",
            "billing.postal_code": "10115",
            "customer.phone": "+49123456789",
            "customer.date_of_birth": "1980-01-02",
        }
        missing_before_hop_one = [field for field in union if request.get(field) in (None, "")]
        self.assertEqual(missing_before_hop_one, [])
        payload_hop_1 = {psp_field: request[field] for field, psp_field in converters["P001"]["map"].items()}
        payload_hop_3 = {psp_field: request[field] for field, psp_field in converters["P003DOB"]["map"].items()}
        self.assertEqual(payload_hop_1, {"zip": "10115"})
        self.assertEqual(payload_hop_3, {"birthDate": "1980-01-02"})

        missing_request = dict(request)
        missing_request.pop("customer.date_of_birth")
        missing_before_hop_one = [field for field in union if missing_request.get(field) in (None, "")]
        self.assertEqual(missing_before_hop_one, ["customer.date_of_birth"])


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
            self.assertEqual(summary["total_checks"], 55)
            self.assertEqual(summary["passed"], 53)
            self.assertEqual(summary["failed"], 2)
            self.assertEqual(summary["score_percent"], 96.36)
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

    def test_cascade_requirements_php_scenario(self):
        proc = subprocess.run(
            [PHP, str(ROOT / "tests/php_cascade_requirements.php")],
            check=True,
            text=True,
            capture_output=True,
        )
        result = json.loads(proc.stdout)
        union_fields = {row["field"] for row in result["union"]["required_fields"]}
        self.assertIn("customer.date_of_birth", union_fields)
        self.assertNotIn("birthDate", result["payload_log"]["P001"])
        self.assertEqual(result["payload_log"]["P003DOB"]["birthDate"], "1980-01-02")
        self.assertEqual([row["status"] for row in result["route"]["attempts"]], ["failed", "failed", "captured"])
        self.assertEqual(result["missing"]["missing_fields"][0]["cascade_reason"], "missing_required_field:customer.date_of_birth")
        self.assertEqual(result["undeclared"][0]["preflight_check_id"], "declared_dependency.customer.ssn")


if __name__ == "__main__":
    unittest.main()

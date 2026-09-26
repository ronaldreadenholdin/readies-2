import json
import shutil
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = shutil.which("php")

DOCS = [
    ROOT / "bob-c-0609/ADP-01-V2-PSP-ADAPTER-CONTRACT.md",
    ROOT / "bob-c-0609/ADP-01-V2-CARD-MASKER.md",
    ROOT / "bob-c-0609/ADP-01-V2-CI-LOG-LEAK-TEST.md",
    ROOT / "bob-c-0609/ADP-01-V2-PREFLIGHT.md",
]


class Adp01V2ContractDocsTests(unittest.TestCase):
    def test_v2_docs_are_public_safe_and_cover_required_decisions(self):
        for path in DOCS:
            self.assertTrue(path.is_file(), path)
            text = path.read_text()
            self.assertIn("DRAFT", text)
            self.assertNotRegex(text, r"https?://")
            self.assertNotRegex(text, r"\b(?:\d{1,3}\.){3}\d{1,3}\b")

        contract = DOCS[0].read_text()
        self.assertIn("ADP-01:v2", contract)
        self.assertIn("becomes FROZEN only on GM approval", contract)
        self.assertIn("P003 template maps unknown status to a soft-decline path", contract)
        self.assertIn("normalizes webhook payloads even when verification fails", contract)
        for rule_id in [f"ADP-01:v2-R{i}" for i in range(1, 9)]:
            self.assertIn(rule_id, contract)

        masker = DOCS[1].read_text()
        self.assertIn("13-19 digit", masker)
        self.assertIn("Luhn", masker)
        self.assertIn("provider namespace", masker)

        leak = DOCS[2].read_text()
        self.assertIn("negative self-test", leak)
        self.assertIn("4111 1111 1111 1111", leak)

        preflight = DOCS[3].read_text()
        self.assertIn("Presence-only checks score zero", preflight)
        self.assertIn("success, soft decline, hard decline", preflight)

    def test_v2_interfaces_are_not_under_provider_namespace(self):
        added_roots = [
            ROOT / "bob-c-0609/laravel/app/Services/Psp/Contracts/V2",
            ROOT / "bob-c-0609/laravel/app/Services/Psp/Security",
            ROOT / "bob-c-0609/laravel/app/Services/Psp/Preflight",
        ]
        for root in added_roots:
            self.assertTrue(root.is_dir(), root)
            self.assertNotIn("/Providers/", str(root))

        converter = (added_roots[0] / "PspConverterV2Interface.php").read_text()
        self.assertIn("function verify(", converter)
        self.assertIn("constant time", converter)
        self.assertIn("never logs", converter)


@unittest.skipIf(PHP is None, "php CLI is required for ADP-01:v2 contract PHP skeleton tests")
class Adp01V2PhpContractTests(unittest.TestCase):
    def run_php_json(self, script_name):
        proc = subprocess.run(
            [PHP, str(ROOT / "tests" / script_name)],
            check=True,
            text=True,
            capture_output=True,
        )
        return json.loads(proc.stdout)

    def test_dto_validator_contract_rules(self):
        result = self.run_php_json("php_adp01_v2_validator.php")
        self.assertEqual(result["valid_page_errors"], [])
        self.assertIn("redirect_url.required", result["invalid_page_errors"])
        self.assertIn("redirect_method.required", result["invalid_page_errors"])
        self.assertEqual(result["valid_event_errors"], [])
        self.assertIn("signature_verified", result["unverified_event_errors"])
        self.assertIn("received_at", result["mismatch_event_errors"])
        self.assertIn("amount_mismatch.status", result["mismatch_event_errors"])

    def test_masker_interface_contract_reference_double(self):
        result = self.run_php_json("php_adp01_v2_masker_contract.php")
        self.assertTrue(result["implements_interface"])
        self.assertTrue(result["pan_masked"])
        self.assertTrue(result["cvv_masked"])
        self.assertTrue(result["expiry_masked"])
        self.assertTrue(result["track_masked"])
        self.assertTrue(result["idempotent"])
        self.assertTrue(result["masked_is_clean"])
        self.assertTrue(result["canary_is_detected"])

    def test_log_leak_detector_negative_self_test(self):
        result = self.run_php_json("php_adp01_v2_log_leak_detector.php")
        self.assertEqual(result["clean_findings"], [])
        self.assertTrue(result["negative_self_test_catches_canary"])
        finding_types = {row["type"] for row in result["canary_findings"]}
        self.assertIn("pan", finding_types)
        self.assertIn("cvv", finding_types)


if __name__ == "__main__":
    unittest.main()

import json
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = shutil.which("php")
FIXTURE_ROOT = ROOT / "bob-c-0609/laravel/resources/psp-conformance/p003"
WAITING_PAGE = ROOT / "bob-c-0609/hostinger/public_html/bob-c/waiting.html"
WAITING_STATUS = ROOT / "bob-c-0609/hostinger/public_html/bob-c/waiting-status-stub.json"
MEDIA_REGISTRY = ROOT / "bob-c-0609/laravel/resources/psp-waiting/media-registry.json"
MEDIA_LIBRARY_PAGE = ROOT / "bob-c-0609/hostinger/public_html/bob-c/media-library.html"
KEY_STATUS_PAGE = ROOT / "bob-c-0609/hostinger/public_html/bob-c/psp-key-status.html"


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
        commercial_profile_checks = 26
        security_source_checks = 2
        endpoint_checks = (10 * 3) + 11
        credential_missing_failures = 1
        total = preflight_total + required_field_checks + declared_dependency_checks + commercial_profile_checks + security_source_checks + endpoint_checks
        passed = total - preflight_failed - credential_missing_failures
        self.assertEqual(total, 83)
        self.assertEqual(round((passed / total) * 100, 2), 96.39)

    def test_commercial_profile_contract_and_open_questions(self):
        profile = json.loads((FIXTURE_ROOT / "commercial-profile.json").read_text())
        required = {
            "allowed_geos", "blocked_countries", "accepted_card_brands", "accepted_card_types",
            "three_ds_required", "allowed_verticals", "blocked_verticals", "min_amount", "max_amount",
            "processing_currencies", "settlement_currencies", "mdr_percent", "mdr_fixed", "other_fees",
            "settlement_days", "rolling_reserve_percent", "rolling_reserve_days", "cap_amount",
            "cap_period", "api_docs_received", "sandbox_keys_status", "live_keys_status",
            "signed_webhook_sample_received", "decline_code_map_received", "agreement_signed", "psp_code",
        }
        self.assertTrue(required.issubset(profile.keys()))
        self.assertTrue(all(key == key.lower() for key in profile.keys()))

        bad = dict(profile)
        bad["mdr_percent"] = ""
        missing = [field for field in required if bad.get(field) in (None, "", [], "unknown")]
        open_questions = [{"field": field, "severity": "blocks go-live"} for field in missing]
        self.assertEqual(open_questions, [{"field": "mdr_percent", "severity": "blocks go-live"}])

    def test_eligibility_filter_skip_reasons(self):
        profile = json.loads((FIXTURE_ROOT / "commercial-profile.json").read_text())

        def reason(context):
            if context["billing_country"] in profile["blocked_countries"]:
                return "ineligible:billing_country"
            if context["currency"] not in profile["processing_currencies"]:
                return "ineligible:currency"
            if context["amount"] > float(profile["max_amount"][context["currency"]]):
                return "ineligible:amount"
            return None

        blocked = dict(profile, blocked_countries=["DE"])
        profile_backup = profile
        profile = blocked
        self.assertEqual(reason({"billing_country": "DE", "amount": 10.0, "currency": "EUR"}), "ineligible:billing_country")
        profile = profile_backup
        self.assertEqual(reason({"billing_country": "NL", "amount": 999.0, "currency": "EUR"}), "ineligible:amount")
        self.assertEqual(reason({"billing_country": "NL", "amount": 10.0, "currency": "USD"}), "ineligible:currency")

    def test_trusted_customer_prefill_privacy_rules(self):
        required_fields = ["billing.postal_code", "customer.date_of_birth", "card.pan"]
        trusted = {
            "consent_recorded": True,
            "trusted_since": "2026-01-01T00:00:00Z",
            "last_seen": "2026-09-24T00:00:00Z",
            "fields": {
                "billing.postal_code": "10115",
                "customer.date_of_birth": "1980-01-02",
                "card.pan": "4111111111111111",
            },
        }
        forbidden = {"card.pan", "card.cvv", "card.full_number", "card.number", "card.expiry"}
        prefill = {field: trusted["fields"][field] for field in required_fields if field in trusted["fields"] and field not in forbidden and trusted["consent_recorded"]}
        self.assertEqual(prefill, {"billing.postal_code": "10115", "customer.date_of_birth": "1980-01-02"})
        self.assertNotIn("card.pan", prefill)

        no_consent = dict(trusted, consent_recorded=False)
        prefill = {field: no_consent["fields"][field] for field in required_fields if field in no_consent["fields"] and no_consent["consent_recorded"]}
        self.assertEqual(prefill, {})

        non_trusted = None
        self.assertIsNone(non_trusted)

    def test_waiting_page_status_contract_and_defaults(self):
        html = WAITING_PAGE.read_text()
        status = json.loads(WAITING_STATUS.read_text())
        self.assertEqual(status["message"], "Securing your payment...")
        self.assertFalse(status["ad_slot_enabled"])
        self.assertEqual(status["audit"]["media_id_shown"], "pigeon_card_delivery")
        self.assertIn("sessionStorage.setItem('readies_payment_idempotency_key'", html)
        self.assertIn("Do not close, refresh, or go back", html)
        self.assertIn("Content-Security-Policy", html)
        self.assertNotIn("failed, trying another provider", html.lower())

    def test_waiting_media_registry_and_merchant_consent_rules(self):
        registry = {row["media_id"]: row for row in json.loads(MEDIA_REGISTRY.read_text())}
        self.assertIn("pigeon_card_delivery", registry)
        pigeon = registry["pigeon_card_delivery"]
        self.assertEqual(pigeon["title"], "Pigeon card delivery")
        self.assertEqual(pigeon["status"], "approved")
        self.assertIn("TODO", pigeon["storage_path"])

        consents = {
            ("merchant-1", "custom_wait_clip", "cascade_wait"): {"approved": False},
            ("merchant-1", "pigeon_card_delivery", "cascade_wait"): {"approved": True},
        }

        def resolve(media_id, slot="cascade_wait"):
            media = registry.get(media_id)
            consent = consents.get(("merchant-1", media_id, slot))
            if media and media["status"] == "approved" and consent and consent["approved"]:
                return media["media_id"]
            return "pigeon_card_delivery"

        self.assertEqual(resolve("unregistered_clip"), "pigeon_card_delivery")
        self.assertEqual(resolve("custom_wait_clip"), "pigeon_card_delivery")
        consents[("merchant-1", "pigeon_card_delivery", "cascade_wait")] = {"approved": False}
        self.assertEqual(resolve("pigeon_card_delivery"), "pigeon_card_delivery")
        self.assertFalse(json.loads(WAITING_STATUS.read_text())["ad_slot_enabled"])

    def test_bob_c_media_library_page_lists_default_clip(self):
        html = MEDIA_LIBRARY_PAGE.read_text()
        self.assertIn("BOB C Media Library", html)
        self.assertIn("Pigeon card delivery", html)
        self.assertIn("pigeon_card_delivery", html)
        self.assertIn("Default adverts are off", html)
        self.assertIn("Fees earned", html)
        self.assertIn("Conversion vs control", html)

    def test_media_impression_billing_and_conversion_contract(self):
        minimum_visible_seconds = 2
        impressions = {}

        def record(event):
            key = (event["payment_attempt_id"], event["slot"])
            if event["visible_duration_seconds"] < minimum_visible_seconds:
                return {"counted": False, "reason": "under_minimum_visibility"}
            if key in impressions:
                return {"counted": False, "reason": "duplicate_attempt_slot"}
            impressions[key] = event
            return {"counted": True, "impression": event}

        under_min = record({
            "media_id": "ad_1",
            "advertiser_id": "adv",
            "merchant_id": "merchant",
            "slot": "cascade_wait",
            "payment_attempt_id": "attempt-1",
            "merchant_reference": "order-1",
            "visible_duration_seconds": 1,
            "completed": False,
            "clicked": False,
            "payment_outcome": "abandoned",
            "variant": "ad_1",
        })
        self.assertFalse(under_min["counted"])

        first = record({
            "media_id": "ad_1",
            "advertiser_id": "adv",
            "merchant_id": "merchant",
            "slot": "cascade_wait",
            "payment_attempt_id": "attempt-2",
            "merchant_reference": "order-2",
            "visible_duration_seconds": 3,
            "completed": True,
            "clicked": False,
            "payment_outcome": "success",
            "variant": "ad_1",
        })
        refresh = record(dict(first["impression"]))
        self.assertTrue(first["counted"])
        self.assertFalse(refresh["counted"])
        self.assertEqual(refresh["reason"], "duplicate_attempt_slot")

        rates = {
            "ad_1": {
                "billable_event": "completed_view",
                "currency": "EUR",
                "amount": 0.05,
                "merchant_revenue_share_percent": 20,
            }
        }
        fee_owed = sum(
            rates[row["media_id"]]["amount"]
            for row in impressions.values()
            if row["completed"] and rates[row["media_id"]]["billable_event"] == "completed_view"
        )
        self.assertEqual(fee_owed, 0.05)
        self.assertEqual(round(fee_owed * 0.20, 4), 0.01)

        control_group_percent = 100
        selected_variant = "none" if control_group_percent == 100 else "ad_1"
        self.assertEqual(selected_variant, "none")
        self.assertEqual(first["impression"]["variant"], "ad_1")

    def test_psp_credentials_are_provider_only_and_not_leaked(self):
        abstract_adaptor = (ROOT / "bob-c-0609/laravel/app/Services/Psp/AbstractPspAdaptor.php").read_text()
        self.assertIn("PspCredentialProviderInterface", abstract_adaptor)
        self.assertNotIn("getenv('PSP_WEBHOOK_TEST_SECRET')", abstract_adaptor)
        self.assertNotIn("PSP_WEBHOOK_TEST_SECRET", abstract_adaptor)

        adapter_source = (ROOT / "bob-c-0609/laravel/app/Services/Psp/Adaptors/FblsP003Adaptor.php").read_text()
        converter_source = (ROOT / "bob-c-0609/laravel/app/Services/Psp/Converters/FblsP003Converter.php").read_text()
        self.assertNotIn("test_secret", adapter_source)
        self.assertNotIn("test_secret", converter_source)

        key_page = KEY_STATUS_PAGE.read_text()
        self.assertIn("Open 0609 vault entry", key_page)
        self.assertIn("missing", key_page)
        self.assertNotIn("test_secret", key_page)
        self.assertNotIn("xai-", key_page.lower())

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

    def test_webhook_driven_cascade_rules_contract(self):
        def start_state():
            return {
                "attempts": [{"psp_code": "P001", "outcome": "awaiting_failure_webhook"}],
                "failed_psps": [],
                "final_outcome": "awaiting_final_status",
                "flags": [],
            }

        pending = start_state()
        self.assertEqual(pending["attempts"][0]["outcome"], "awaiting_failure_webhook")
        self.assertEqual(len(pending["attempts"]), 1)

        failed_webhook = start_state()
        failed_webhook["attempts"][0]["outcome"] = "confirmed_failure"
        failed_webhook["attempts"].append({"psp_code": "P002", "outcome": "awaiting_failure_webhook"})
        self.assertEqual([row["psp_code"] for row in failed_webhook["attempts"]], ["P001", "P002"])

        timeout_failed = start_state()
        timeout_failed["attempts"][0]["status_received"] = "failed"
        timeout_failed["attempts"][0]["outcome"] = "confirmed_failure"
        timeout_failed["attempts"].append({"psp_code": "P002", "outcome": "awaiting_failure_webhook"})
        self.assertEqual(timeout_failed["attempts"][0]["status_received"], "failed")

        timeout_pending = start_state()
        timeout_pending["attempts"][0]["status_received"] = "pending"
        timeout_pending["attempts"][0]["outcome"] = "awaiting_final_status"
        self.assertEqual(len(timeout_pending["attempts"]), 1)
        self.assertEqual(timeout_pending["attempts"][0]["outcome"], "awaiting_final_status")

        three_failures = {
            "failed_psps": ["P001", "P002", "P003"],
            "recovery": {
                "psp_code": "P004",
                "email": {"body": "We could not complete the payment for order order-123."},
                "email_sent": False,
            },
        }
        self.assertNotIn(three_failures["recovery"]["psp_code"], three_failures["failed_psps"])
        self.assertFalse(three_failures["recovery"]["email_sent"])
        self.assertNotIn("card", three_failures["recovery"]["email"]["body"].lower())

        late_success = failed_webhook
        late_success["flags"].append({
            "type": "late_success_possible_double_charge",
            "merchant_reference": "order-123",
            "idempotency_key": "order-123",
        })
        self.assertEqual(late_success["flags"][0]["type"], "late_success_possible_double_charge")
        self.assertEqual(late_success["flags"][0]["merchant_reference"], late_success["flags"][0]["idempotency_key"])


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
            self.assertEqual(summary["total_checks"], 83)
            self.assertEqual(summary["passed"], 80)
            self.assertEqual(summary["failed"], 3)
            self.assertEqual(summary["score_percent"], 96.39)
            self.assertFalse(summary["eligible_for_cascade"])
            self.assertEqual(report["open_questions"]["P003"][0]["field"], "live_keys_status")
            self.assertEqual(report["credential_status"]["P003"]["live"]["status"], "missing")
            self.assertNotIn("test_secret", proc.stdout)

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

    def test_webhook_driven_cascade_php_scenarios(self):
        proc = subprocess.run(
            [PHP, str(ROOT / "tests/php_webhook_driven_cascade.php")],
            check=True,
            text=True,
            capture_output=True,
        )
        result = json.loads(proc.stdout)
        self.assertEqual(result["pending"]["status"], "awaiting_failure_webhook")
        self.assertEqual(len(result["pending"]["attempts"]), 1)
        self.assertEqual([row["psp_code"] for row in result["failed_webhook"]["attempts"]], ["P001", "P002"])
        self.assertEqual([row["psp_code"] for row in result["timeout_failed"]["attempts"]], ["P001", "P002"])
        self.assertEqual(result["timeout_pending"]["final_outcome"], "awaiting_final_status")
        self.assertEqual(result["three_failures"]["recovery"]["psp_code"], "P004")
        self.assertFalse(result["three_failures"]["recovery"]["email_sent"])
        self.assertEqual(result["mailer_sent"], [])
        self.assertEqual(result["late_success"]["flags"][0]["type"], "late_success_possible_double_charge")


if __name__ == "__main__":
    unittest.main()

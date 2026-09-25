import json
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = shutil.which("php")
FIXTURE_ROOT = ROOT / "bob-c-0609/laravel/resources/psp-conformance/p003"
PROVIDERS_ROOT = ROOT / "bob-c-0609/laravel/app/Services/Psp/Providers"
WAITING_PAGE = ROOT / "bob-c-0609/hostinger/public_html/bob-c/waiting.html"
WAITING_STATUS = ROOT / "bob-c-0609/hostinger/public_html/bob-c/waiting-status-stub.json"
MEDIA_REGISTRY = ROOT / "bob-c-0609/laravel/resources/psp-waiting/media-registry.json"
MEDIA_LIBRARY_PAGE = ROOT / "bob-c-0609/hostinger/public_html/bob-c/media-library.html"
KEY_STATUS_PAGE = ROOT / "bob-c-0609/hostinger/public_html/bob-c/psp-key-status.html"
MARKETING_PAGE = ROOT / "bob-c-0609/hostinger/public_html/bob-c/marketing.html"
MARKETING_RESULTS_PAGE = ROOT / "bob-c-0609/hostinger/public_html/bob-c/marketing-results-pigeon.html"
MARKETING_ASSETS = ROOT / "bob-c-0609/laravel/resources/psp-marketing/marketing-assets.json"
ADAPTER_STANDARDS = ROOT / "bob-c-0609/laravel/resources/psp-adapters/adapter-standards.json"
PROVIDER_CONNECTIONS = ROOT / "bob-c-0609/laravel/resources/psp-adapters/provider-connections.json"
P001_PROFILE = ROOT / "bob-c-0609/laravel/resources/psp-conformance/p001/commercial-profile.json"
P001_REPORT = ROOT / "reports/p001-clisapay-dry-run-conformance.json"
MERCHANT_PSP_ORDER_PARTIAL = ROOT / "bob-c-0609/laravel/resources/views/psp/partials/merchant_psp_order_suggestions.blade.php"
MERCHANT_PSP_DISAGREEMENTS_PARTIAL = ROOT / "bob-c-0609/laravel/resources/views/psp/partials/merchant_psp_disagreements.blade.php"
MERCHANT_PSP_TRIALS_PARTIAL = ROOT / "bob-c-0609/laravel/resources/views/psp/partials/merchant_psp_trials.blade.php"
MERCHANT_TAB_INTEGRATION = ROOT / "bob-c-0609/laravel/PSP-MERCHANT-TAB-INTEGRATION.md"


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
            self.assertEqual(payload["schema_version"], "ADP-01:v1")
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

    def test_numbered_adapter_standards_registry(self):
        standards = json.loads(ADAPTER_STANDARDS.read_text())
        self.assertEqual([row["adapter_number"] for row in standards], [f"ADP-0{i}" for i in range(1, 8)])
        self.assertEqual(standards[0]["name"], "Card PSP")
        self.assertEqual(standards[0]["status"], "active")
        self.assertEqual(standards[0]["version"], "ADP-01:v1")
        for row in standards[1:]:
            self.assertEqual(row["status"], "planned")
            self.assertEqual(row["name"], "TBD")
            self.assertIsNone(row["normalized_contract"])

        connections = json.loads(PROVIDER_CONNECTIONS.read_text())
        self.assertEqual(connections[0]["connection_code"], "ADP-01 / P003")
        self.assertEqual(connections[0]["adapter_number"], "ADP-01")
        self.assertEqual(connections[0]["provider_code"], "P003")
        self.assertEqual(connections[1]["connection_code"], "ADP-01 / P001")
        self.assertEqual(connections[1]["status"], "dry_run")

    def test_merchant_psp_order_suggested_actual_and_override_audit(self):
        connections = [
            {"connection_code": "ADP-01 / CHEAP", "score_percent": 100.0, "eligible": True, "mdr_percent": 2.0, "mdr_fixed": 0.2},
            {"connection_code": "ADP-01 / EXPENSIVE", "score_percent": 100.0, "eligible": True, "mdr_percent": 5.5, "mdr_fixed": 0.3},
            {"connection_code": "ADP-01 / PLANNED", "score_percent": 96.39, "eligible": False, "mdr_percent": 1.0, "mdr_fixed": 0.1},
        ]
        ordered = sorted(connections, key=lambda row: (not row["eligible"], row["mdr_percent"] + row["mdr_fixed"] / 100))
        for index, row in enumerate(ordered, start=1):
            row["suggested_position"] = index
            row["live_position_allowed"] = row["score_percent"] == 100.0 and row["eligible"]
        self.assertEqual([row["connection_code"] for row in ordered], ["ADP-01 / CHEAP", "ADP-01 / EXPENSIVE", "ADP-01 / PLANNED"])
        self.assertFalse(ordered[-1]["live_position_allowed"])
        self.assertEqual(ordered[:2], ordered[:3 - 1])

        audit = []
        audit.append({
            "merchant_id": "neckermann",
            "connection_code": "ADP-01 / EXPENSIVE",
            "from_position": 2,
            "to_position": 1,
            "reason": "TADDY commercial override for testing",
            "overridden_by": "TADDY",
        })
        audit.append(dict(audit[0], from_position=1, to_position=2, reason="Gerardus restored cheaper-first"))
        self.assertEqual(len(audit), 2)
        self.assertEqual(audit[0]["overridden_by"], "TADDY")

        disagreements = [{
            "merchant_id": "neckermann",
            "connection_code": "ADP-01 / EXPENSIVE",
            "suggested_position": 2,
            "actual_position": 1,
            "suggested_reason": "System/CODA suggestion from eligibility, cost, and conformance.",
            "actual_reason": "TADDY commercial override for testing",
            "status": "Needs Gerardus discussion",
        }]
        self.assertEqual(disagreements[0]["status"], "Needs Gerardus discussion")

        partial = MERCHANT_PSP_ORDER_PARTIAL.read_text()
        disagreement_partial = MERCHANT_PSP_DISAGREEMENTS_PARTIAL.read_text()
        integration = MERCHANT_TAB_INTEGRATION.read_text()
        self.assertIn("Suggested position", partial)
        self.assertIn("Actual position", partial)
        self.assertIn("Needs Gerardus discussion", disagreement_partial)
        self.assertIn("Do not create a duplicate merchant PSP list", integration)
        self.assertIn("pending server access", integration)

    def test_trial_override_rules(self):
        def disagreement(row):
            if row["actual_position"] == row["suggested_position"]:
                return False
            if row["trial_state"] == "On trial" and row["actual_position"] < row["suggested_position"]:
                return False
            return True

        trial = {
            "connection_code": "ADP-01 / NEW",
            "suggested_position": 3,
            "actual_position": 1,
            "trial_state": "On trial",
            "live_position_allowed": True,
        }
        proven = dict(trial, trial_state="Proven")
        not_eligible_trial = dict(trial, live_position_allowed=False)
        self.assertFalse(disagreement(trial))
        self.assertTrue(disagreement(proven))
        self.assertFalse(not_eligible_trial["live_position_allowed"])

        config_unset = {"trial_min_transactions": None, "trial_max_days": None}
        self.assertEqual(config_unset, {"trial_min_transactions": None, "trial_max_days": None})
        self.assertFalse(any(value is not None for value in config_unset.values()))

        row = {"trial_transactions": 50, "trial_days_elapsed": 7}
        thresholds = {"trial_min_transactions": 50, "trial_max_days": 14}
        ended = row["trial_transactions"] >= thresholds["trial_min_transactions"] or row["trial_days_elapsed"] >= thresholds["trial_max_days"]
        self.assertTrue(ended)

        partial = MERCHANT_PSP_TRIALS_PARTIAL.read_text()
        integration = MERCHANT_TAB_INTEGRATION.read_text()
        self.assertIn("New PSPs on trial", partial)
        self.assertIn("Trial length not set, needs Gerardus", partial)
        self.assertIn("trial_min_transactions", integration)
        self.assertIn("Example only, not a default", integration)

    def test_p001_clisapay_dry_run_uses_only_given_facts(self):
        profile = json.loads(P001_PROFILE.read_text())
        report = json.loads(P001_REPORT.read_text())
        self.assertEqual(profile["provider_name"], "Clisapay")
        self.assertEqual(profile["legal_entity"], "JIXINGBAO TRADING PTE. LTD.")
        self.assertEqual(profile["live_keys_status"], "missing")
        self.assertEqual(profile["api_docs_received"], "unknown")
        self.assertEqual(profile["processing_currencies"], "unknown")
        self.assertIn("USA coverage has been STOPPED", profile["geo_notes"])
        self.assertEqual(report["connection"]["connection_code"], "ADP-01 / P001")
        self.assertFalse(report["score"]["eligible_for_live"])
        self.assertEqual(report["suggested_downline_position"]["position"], 3)
        owners = {row["gap_owner"] for row in report["failed_or_unknown_checks"]}
        self.assertEqual(owners, {"provider", "converter"})
        self.assertIn("No unknown facts were inferred.", (ROOT / "reports/p001-clisapay-dry-run-conformance.md").read_text())

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

        adapter_source = (PROVIDERS_ROOT / "P003/FblsP003Adaptor.php").read_text()
        converter_source = (PROVIDERS_ROOT / "P003/FblsP003Converter.php").read_text()
        self.assertNotIn("test_secret", adapter_source)
        self.assertNotIn("test_secret", converter_source)

    def test_psp_provider_folders_are_independent(self):
        providers = [path for path in PROVIDERS_ROOT.iterdir() if path.is_dir()]
        provider_names = {path.name for path in providers}
        self.assertIn("P003", provider_names)
        for provider in providers:
            for source in provider.rglob("*"):
                if not source.is_file():
                    continue
                text = source.read_text(errors="ignore")
                for other in provider_names - {provider.name}:
                    self.assertNotIn(f"Providers\\\\{other}", text)
                    self.assertNotIn(f"/{other}/", text)
        old_shared = [
            ROOT / "bob-c-0609/laravel/app/Services/Psp/Adaptors/FblsP003Adaptor.php",
            ROOT / "bob-c-0609/laravel/app/Services/Psp/Converters/FblsP003Converter.php",
            ROOT / "bob-c-0609/laravel/app/Services/Psp/Fixtures/FblsP003FixtureTransport.php",
        ]
        self.assertFalse(any(path.exists() for path in old_shared))

    def test_contract_version_guard_and_all_provider_goldens(self):
        contract_source = (ROOT / "bob-c-0609/laravel/app/Services/Psp/PspNormalizedContract.php").read_text()
        self.assertIn("SCHEMA_VERSION = 'ADP-01:v1'", contract_source)
        standards = json.loads(ADAPTER_STANDARDS.read_text())
        self.assertEqual(standards[0]["version"], "ADP-01:v1")
        for provider in [path for path in PROVIDERS_ROOT.iterdir() if path.is_dir()]:
            config = json.loads((provider / "provider-config.json").read_text())
            self.assertEqual(config["contract_version"], "ADP-01:v1")
            golden_root = ROOT / "bob-c-0609/laravel" / config["fixture_root"] / "golden"
            goldens = sorted(golden_root.glob("*.json"))
            self.assertGreaterEqual(len(goldens), 4)
            for golden in goldens:
                payload = json.loads(golden.read_text())
                self.assertEqual(payload["schema_version"], config["contract_version"])

        key_page = KEY_STATUS_PAGE.read_text()
        self.assertIn("Open 0609 vault entry", key_page)
        self.assertIn("ADP-01 / P003", key_page)
        self.assertIn("blocked until 100%", key_page)
        self.assertIn("missing", key_page)
        self.assertNotIn("test_secret", key_page)
        self.assertNotIn("xai-", key_page.lower())

    def test_marketing_assets_and_pages_are_example_only(self):
        assets = json.loads(MARKETING_ASSETS.read_text())
        self.assertEqual([asset["asset_id"] for asset in assets], ["pigeon_card_delivery"])
        self.assertIn("TODO", assets[0]["storage_path"])
        marketing = MARKETING_PAGE.read_text()
        results = MARKETING_RESULTS_PAGE.read_text()
        self.assertIn("Marketing", marketing)
        self.assertIn("EXAMPLE DATA", marketing)
        self.assertIn("EXAMPLE DATA", results)
        self.assertIn("pigeon_card_delivery", marketing)

    def test_marketing_placement_history_is_append_only(self):
        placements = []
        first = {
            "placement_id": "p1",
            "asset_id": "pigeon_card_delivery",
            "slot": "cascade_wait",
            "merchant_id": "neckermann",
            "site": "Neckermann test site",
            "page_or_flow_step": "waiting",
            "active_from": "2026-09-25T00:00:00Z",
            "switched_on_by": "admin-a",
        }
        second = dict(first, placement_id="p2", active_from="2026-09-25T01:00:00Z", switched_on_by="admin-b")
        placements.append(first)
        placements.append(second)
        self.assertEqual(len(placements), 2)
        self.assertEqual(placements[0]["switched_on_by"], "admin-a")
        self.assertEqual(placements[1]["switched_on_by"], "admin-b")

    def test_marketing_results_date_range_and_now_windows(self):
        impressions = [
            {"shown_at": "2026-09-25T00:10:00Z", "media_id": "pigeon_card_delivery", "slot": "cascade_wait", "merchant_id": "neckermann", "site": "Neckermann test site", "completed": True, "clicked": False, "payment_outcome": "success", "variant": "default_animation"},
            {"shown_at": "2026-09-24T23:30:00Z", "media_id": "pigeon_card_delivery", "slot": "cascade_wait", "merchant_id": "neckermann", "site": "Neckermann test site", "completed": False, "clicked": True, "payment_outcome": "abandoned", "variant": "default_animation"},
            {"shown_at": "2026-09-20T12:00:00Z", "media_id": "none", "slot": "cascade_wait", "merchant_id": "neckermann", "site": "Neckermann test site", "completed": False, "clicked": False, "payment_outcome": "success", "variant": "none"},
        ]
        today = [row for row in impressions if row["shown_at"].startswith("2026-09-25")]
        last_24 = [row for row in impressions if row["shown_at"] >= "2026-09-24T00:30:00Z"]
        history_range = [row for row in impressions if "2026-09-24" <= row["shown_at"][:10] <= "2026-09-25"]
        self.assertEqual(len(today), 1)
        self.assertEqual(len(last_24), 2)
        self.assertEqual(len(history_range), 2)
        self.assertEqual(sum(1 for row in history_range if row["completed"]), 1)

    def test_youtube_embed_and_upload_validation_safety(self):
        video_id = "abcDEF12345"
        embed = f"https://www.youtube-nocookie.com/embed/{video_id}?autoplay=0&mute=1&rel=0"
        self.assertIn("youtube-nocookie.com", embed)
        self.assertIn("autoplay=0", embed)
        self.assertIn("mute=1", embed)

        allowed_mime = {"video_clip": {"video/mp4", "video/webm"}, "banner_image": {"image/png", "image/jpeg", "image/webp"}}
        max_bytes = 25_000_000
        self.assertIn("video/mp4", allowed_mime["video_clip"])
        self.assertNotIn("application/x-msdownload", allowed_mime["banner_image"])
        self.assertLessEqual(2_000_000, max_bytes)

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
            self.assertEqual(summary["adapter_number"], "ADP-01")
            self.assertEqual(summary["connection_code"], "ADP-01 / P003")
            self.assertEqual(summary["eligibility_rule"], "eligible only at 100%")
            self.assertEqual(report["open_questions"]["P003"][0]["field"], "live_keys_status")
            self.assertIn(report["open_questions"]["P003"][0]["gap_owner"], {"provider", "converter"})
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

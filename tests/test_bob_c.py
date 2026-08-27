import os
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "bob-c-0609" / "standalone-demo"))

from bob_g_helper import catalog_summary, local_reply, recommend, status  # noqa: E402


class BobCHelperTests(unittest.TestCase):
    def test_status_extends_bob_g_workdesk(self):
        os.environ.pop("XAI_API_KEY", None)
        data = status()
        self.assertEqual(data["agent"], "BOB C")
        self.assertEqual(data["assistant"], "Bob G")
        self.assertEqual(data["extends"], "Bob G")
        self.assertFalse(data["connected"])
        self.assertEqual(data["mode"], "bob-g-workdesk")
        self.assertGreaterEqual(len(data["work"]["completed"]), 5)

    def test_catalog_lists_bob_g_functions(self):
        work = catalog_summary()
        titles = [row["id"] for row in work["completed"]]
        self.assertIn("bob-recommendations", titles)
        self.assertIn("preflight-harness", titles)
        self.assertIn("psp-adaptor-interface", titles)
        self.assertIn("list_work", work["functions"])

    def test_cashforo_reuses_existing_adaptors(self):
        reply = local_reply("How do I integrate CashForo OR001?")
        self.assertIn("CashForoOnrampAdaptor", reply)
        self.assertIn("API_DOCS_REQUIRED", reply)
        self.assertIn("not write new", reply.lower())

    def test_afrpay_stays_separate(self):
        reply = local_reply("Build AfrPay like CashForo")
        self.assertIn("Europe", reply)
        self.assertIn("Kazakhstan", reply)
        self.assertIn("Tunisia", reply)
        self.assertIn("Do not copy CashForo", reply)

    def test_recommendations_reuse_bob_g_letter(self):
        reply = local_reply("Write Bob recommendations for flagged items")
        self.assertIn("Dear PSP Team", reply)
        self.assertIn("Webhook sample", reply)

    def test_recommend_function(self):
        letter = recommend([], "FBLS")
        self.assertIn("No Bob recommendations needed", letter)

    def test_laravel_function_reuses_catalog(self):
        reply = local_reply("Create a Laravel controller function")
        self.assertIn("PspSandboxController", reply)
        self.assertIn("BobRecommendationService", reply)

    def test_psp_codes(self):
        reply = local_reply("What is P003 and P004?")
        self.assertIn("PspTestHarnessService", reply)
        self.assertIn("P003", reply)


class PackLayoutTests(unittest.TestCase):
    def test_hostinger_files_exist(self):
        base = ROOT / "bob-c-0609" / "hostinger" / "public_html" / "bob-c"
        for rel in [
            "index.php",
            "api.php",
            "work/catalog.json",
            "src/BobGWorkDesk.php",
            "src/BobRecommendationService.php",
            "src/BobGClient.php",
        ]:
            self.assertTrue((base / rel).is_file(), rel)

    def test_ui_says_extension(self):
        html = (ROOT / "bob-c-0609/hostinger/public_html/bob-c/index.php").read_text()
        self.assertIn("BOB C extends Bob G", html)

    def test_php_client_contains_grok_endpoint(self):
        source = (ROOT / "bob-c-0609/hostinger/public_html/bob-c/src/BobGClient.php").read_text()
        self.assertIn("/chat/completions", source)
        self.assertIn("BOB C is your 0609 sidebar extension", source)


if __name__ == "__main__":
    unittest.main()

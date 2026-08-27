import os
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "bob-c-0609" / "standalone-demo"))

from bob_g_helper import local_reply, status  # noqa: E402


class BobCHelperTests(unittest.TestCase):
    def test_status_defaults_to_local_helper(self):
        os.environ.pop("XAI_API_KEY", None)
        data = status()
        self.assertEqual(data["agent"], "BOB C")
        self.assertEqual(data["assistant"], "Bob G")
        self.assertFalse(data["connected"])
        self.assertEqual(data["mode"], "local-helper")

    def test_cashforo_codes_are_not_afrpay(self):
        reply = local_reply("How do I integrate CashForo OR001?")
        self.assertIn("OR001", reply)
        self.assertIn("OB003", reply)
        self.assertNotIn("Kazakhstan", reply)

    def test_afrpay_stays_separate(self):
        reply = local_reply("Build AfrPay like CashForo")
        self.assertIn("Europe", reply)
        self.assertIn("Kazakhstan", reply)
        self.assertIn("Tunisia", reply)
        self.assertIn("Do not reuse CashForo", reply)

    def test_laravel_function_help(self):
        reply = local_reply("Create a Laravel controller function")
        self.assertIn("layouts.adminpanel", reply)
        self.assertIn("app/Http/Controllers", reply)

    def test_psp_codes(self):
        reply = local_reply("What is P003 and P004?")
        self.assertIn("FBLS", reply)
        self.assertIn("Xcore", reply)


class PackLayoutTests(unittest.TestCase):
    def test_hostinger_files_exist(self):
        base = ROOT / "bob-c-0609" / "hostinger" / "public_html" / "bob-c"
        for rel in [
            "index.php",
            "api.php",
            ".env.example",
            "src/BobGClient.php",
            "assets/app.js",
            "assets/app.css",
        ]:
            self.assertTrue((base / rel).is_file(), rel)

    def test_laravel_sidebar_and_route_exist(self):
        laravel = ROOT / "bob-c-0609" / "laravel"
        sidebar = (laravel / "resources/views/partials/bob_c_sidebar.blade.php").read_text()
        routes = (laravel / "routes/bob_c.php").read_text()
        self.assertIn("BOB C", sidebar)
        self.assertIn("/bob-c", routes)
        self.assertIn("Ask Bob G", (laravel / "resources/views/bob_c/index.blade.php").read_text())

    def test_php_client_contains_grok_endpoint(self):
        source = (ROOT / "bob-c-0609/hostinger/public_html/bob-c/src/BobGClient.php").read_text()
        self.assertIn("/chat/completions", source)
        self.assertIn("api.x.ai", source)
        self.assertIn("You are Bob G", source)


if __name__ == "__main__":
    unittest.main()

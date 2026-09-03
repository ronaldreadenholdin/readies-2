import tempfile
import unittest
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "ftd-trusted" / "standalone-demo"))

from trusted_list import FTD, MATCH_ORDER, TRUSTED, TrustedList  # noqa: E402


class FtdTrustedTests(unittest.TestCase):
    def setUp(self):
        self.list = TrustedList()

    def test_unknown_is_ftd(self):
        result = self.list.classify({"email": "new@example.com"})
        self.assertEqual(result["status"], FTD)
        self.assertIsNone(result["matched_by"])

    def test_paid_once_makes_trusted(self):
        paid = self.list.mark_paid({
            "email": "a@b.com",
            "phone": "+31 6 12345678",
            "card_first6": "424242",
            "card_last4": "4242",
            "birthday": "1990-05-01",
            "full_name": "Jane Doe",
            "provider": "P003",
        })
        self.assertEqual(paid["status"], TRUSTED)
        self.assertEqual(paid["matched_by"], "successful_payment")

    def test_match_order_email_before_phone(self):
        self.list.mark_paid({
            "email": "same@x.com",
            "phone": "31612345678",
            "full_name": "Jane Doe",
        })
        result = self.list.classify({
            "email": "same@x.com",
            "phone": "31699999999",
            "full_name": "Other Person",
        })
        self.assertEqual(result["status"], TRUSTED)
        self.assertEqual(result["matched_by"], "email")

    def test_phone_then_card_then_birthday_then_name(self):
        self.list.mark_paid({
            "phone": "31612345678",
            "card_first6": "555555",
            "card_last4": "4444",
            "birthday": "1988-02-02",
            "full_name": "Sam Trusted",
        })
        self.assertEqual(self.list.classify({"phone": "+31-6-1234-5678"})["matched_by"], "phone")
        self.assertEqual(
            self.list.classify({"card_first6": "555555", "card_last4": "4444"})["matched_by"],
            "card_first6_last4",
        )
        self.assertEqual(self.list.classify({"birthday": "1988-02-02"})["matched_by"], "birthday")
        self.assertEqual(self.list.classify({"full_name": " Sam   Trusted "})["matched_by"], "full_name")

    def test_card_needs_first6_and_last4(self):
        self.list.mark_paid({"card_first6": "424242", "card_last4": "1111", "email": "c@d.com"})
        self.assertEqual(self.list.classify({"card_first6": "424242", "card_last4": "9999"})["status"], FTD)
        self.assertEqual(self.list.classify({"card_first6": "424242", "card_last4": "1111"})["status"], TRUSTED)

    def test_every_provider_uses_same_list(self):
        self.list.mark_paid({"email": "shared@x.com", "provider": "P003"})
        later = self.list.classify({"email": "shared@x.com", "provider": "OR001"})
        self.assertEqual(later["status"], TRUSTED)

    def test_single_name_is_not_a_key(self):
        self.list.mark_paid({"full_name": "Jane", "email": "n@n.com"})
        self.assertEqual(self.list.classify({"full_name": "Jane"})["status"], FTD)

    def test_match_order_constant(self):
        self.assertEqual(
            MATCH_ORDER,
            ("email", "phone", "card_first6_last4", "birthday", "full_name"),
        )

    def test_persists(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "trusted-list.json"
            first = TrustedList(path)
            first.mark_paid({"email": "keep@x.com"})
            second = TrustedList(path)
            self.assertEqual(second.classify({"email": "keep@x.com"})["status"], TRUSTED)


class PackLayoutTests(unittest.TestCase):
    def test_files_exist(self):
        base = ROOT / "ftd-trusted"
        for rel in [
            "README.md",
            "standalone/api.php",
            "standalone/src/TrustedList.php",
            "laravel/app/Services/TrustedListService.php",
            "laravel/database/migrations/2026_09_03_000000_create_trusted_customers_table.php",
        ]:
            self.assertTrue((base / rel).is_file(), rel)

    def test_php_has_match_order(self):
        source = (ROOT / "ftd-trusted/standalone/src/TrustedList.php").read_text()
        self.assertIn("MATCH_EMAIL", source)
        self.assertIn("successful_payment", source)
        self.assertNotIn("replit", source.lower())


if __name__ == "__main__":
    unittest.main()

#!/usr/bin/env python3
"""Local BOB C demo. Same UI and API shape as the Hostinger pack."""

from __future__ import annotations

import json
import os
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

from bob_g_helper import local_reply, status

ROOT = Path(__file__).resolve().parent
HOSTINGER_ASSETS = ROOT.parent / "hostinger" / "public_html" / "bob-c"
HISTORY: list[dict[str, str]] = []


class Handler(SimpleHTTPRequestHandler):
    def translate_path(self, path: str) -> str:
        parsed = urlparse(path)
        rel = parsed.path.lstrip("/")
        if rel in {"", "index.html", "bob-c", "bob-c/"}:
            return str(ROOT / "index.html")
        if rel.startswith("assets/"):
            return str(HOSTINGER_ASSETS / rel)
        return str(ROOT / rel)

    def log_message(self, format: str, *args) -> None:
        return

    def _json(self, payload: dict, code: int = 200) -> None:
        body = json.dumps(payload).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _read_json(self) -> dict:
        length = int(self.headers.get("Content-Length") or 0)
        raw = self.rfile.read(length) if length else b""
        if not raw:
            return {}
        try:
            data = json.loads(raw.decode("utf-8"))
        except json.JSONDecodeError:
            return {}
        return data if isinstance(data, dict) else {}

    def _action(self) -> str:
        query = parse_qs(urlparse(self.path).query)
        if "action" in query:
            return query["action"][0]
        return self._read_json().get("action", "status")

    def do_GET(self) -> None:
        parsed = urlparse(self.path)
        if parsed.path.startswith("/api") or parsed.path.endswith("api.php"):
            action = parse_qs(parsed.query).get("action", ["status"])[0]
            if action == "history":
                self._json({"ok": True, "messages": HISTORY})
                return
            if action == "work":
                from bob_g_helper import catalog_summary
                self._json({"ok": True, **catalog_summary()})
                return
            self._json({"ok": True, **status(), "history_count": len(HISTORY)})
            return
        super().do_GET()

    def do_POST(self) -> None:
        payload = self._read_json()
        action = parse_qs(urlparse(self.path).query).get("action", [payload.get("action", "ask")])[0]
        if action == "clear":
            HISTORY.clear()
            self._json({"ok": True, "messages": []})
            return
        if action == "recommend":
            from bob_g_helper import recommend
            flagged = payload.get("flagged") or []
            psp = str(payload.get("psp") or "PSP")
            if not isinstance(flagged, list):
                flagged = []
            self._json({
                "ok": True,
                "function": "BobRecommendationService::generate",
                "reply": recommend(flagged, psp),
            })
            return
        if action == "ask":
            message = str(payload.get("message") or "").strip()
            if not message:
                self._json({"ok": False, "error": "Ask Bob G a question first."}, 422)
                return
            reply = local_reply(message)
            HISTORY.append({"role": "user", "content": message})
            HISTORY.append({"role": "assistant", "content": reply})
            result = {
                "ok": True,
                "mode": status()["mode"],
                "model": status()["model"],
                "reply": reply,
                "source": "bob-g" if status()["connected"] else "bob-g-workdesk",
                "desk": status().get("work"),
                "messages": HISTORY,
            }
            if not status()["connected"]:
                result["notice"] = "Using Bob G work desk. Add XAI_API_KEY if you want live Grok on top of this catalog."
            self._json(result)
            return
        self._json({"ok": False, "error": "Unknown BOB C action."}, 404)


def main() -> None:
    port = int(os.environ.get("PORT", "8765"))
    server = ThreadingHTTPServer(("127.0.0.1", port), Handler)
    print(f"BOB C demo: http://127.0.0.1:{port}/")
    server.serve_forever()


if __name__ == "__main__":
    main()

# readies-2

## PSP pre-flight test harness

The standalone FBLS (P003) test page is available in these forms:

- Laravel route: `/pre-flight-test`
- Static hosting route: `/pre-flight-test/`
- Static file fallback: `/pre-flight-test.html`

For a quick local static test from this repository:

```bash
python3 -m http.server 8000 --directory public
```

Then open:

```text
http://127.0.0.1:8000/pre-flight-test/
```

Use the page in this order:

1. Click **Run Full Test**.
2. Click **Run Second Test**.
3. Review both Bob recommendation sections.
4. Click **Go Live Check** after both tests have run.

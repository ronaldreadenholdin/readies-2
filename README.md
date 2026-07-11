# readies-2

## PSP pre-flight test harness

The standalone FBLS (P003) and Xcore (P004) test page is available in these forms:

- Laravel route: `/pre-flight-test`
- Static hosting route: `/pre-flight-test/`
- Static file fallback: `/pre-flight-test.html`

## Starting the next PSP

Use these files:

- Original recovered test: `public/pre-flight-test/index.html`
- Next PSP starter copy: `next-psp-start-here.html`
- Reusable provider test board: `provider-test-board.html`
- Polished current dashboard: `pre-flight-test.html`

For the next PSP, open `next-psp-start-here.html`, change the PSP name/code/region, and click **Run Full Test**.

For any provider, open `provider-test-board.html`. It has two configurable connection tests. Each connection runs an 84-point checklist. Current CashForo values are:

1. Connection 1 / Onramp: `OR001`
2. Connection 2 / Open Banking: `OB003`

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
3. Click **Run Xcore P004 Test**.
4. Review all Bob recommendation sections.
5. Click **Go Live Check** after all tests have run.

## CashForo public documentation evidence

Public CashForo pages were checked and summarized in `cashforo-public-docs-evidence.md`. The board marks only those items green that are supported by the public site, AML policy, or terms. API endpoints, authentication, webhook samples, signature details, and schemas remain red until actual API documentation is provided.

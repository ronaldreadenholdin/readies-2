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
- Flamingo dual-solution test: `flamingo-test.html`
- Polished current dashboard: `pre-flight-test.html`

For the next PSP, open `next-psp-start-here.html`, change the PSP name/code/region, and click **Run Full Test**.

For Flamingo, open `flamingo-test.html`. It has two separate tests:

1. Onramp: `FLM-ONRAMP`
2. Open Banking: `FLM-OB`

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

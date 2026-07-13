HOSTINGER UPLOAD — AFRPAY LIVE API
=================================

This package is ready for Hostinger. No Laravel required.

1. Log in to Hostinger hPanel
2. Open File Manager
3. Open public_html for the site
4. Upload EVERYTHING inside the public_html folder from this ZIP into Hostinger public_html:
   - index.php
   - .htaccess
   - bootstrap.php
   - config.php
   - .env.example  (rename/copy values into .env)
   - src/
   - GO_LIVE.txt
   - status.html
5. Create file .env in public_html (copy from .env.example) and fill AfrPay keys
6. Open:
   https://YOUR-DOMAIN/
   https://YOUR-DOMAIN/api/afrpay/status

API endpoints after upload:
- GET  /api/afrpay/status
- POST /api/afrpay/OR001/payments
- GET  /api/afrpay/OR001/payments/{ref}
- POST /api/afrpay/OR001/refunds
- POST /api/afrpay/OB003/payments
- GET  /api/afrpay/OB003/payments/{ref}
- POST /api/afrpay/OB003/refunds
- POST /api/afrpay/go-live/approve
- POST /webhooks/afrpay/OR001
- POST /webhooks/afrpay/OB003

Keep AFRPAY_LIVE_ENABLED=false until pre-flight is green, then follow GO_LIVE.txt.

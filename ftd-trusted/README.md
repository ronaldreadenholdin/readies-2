# FTD vs trusted list

One list for every provider. Two states only:

- **FTD** — not on the list
- **trusted** — on the list, or paid once successfully

No extra product names in this pack.

## Who uploads

Merchants do **not** upload this list.

0609 admin staff upload and maintain the list **for** a merchant on the admin backend (`layouts.adminpanel`). An admin upload **replaces that merchant’s entire list**.

## Match order

Stop at the first hit:

1. email
2. phone
3. card first 6 + last 4
4. birthday
5. full name

## Live folder on 0609

```text
/var/www/html/adapter/public/ftd-trusted/
```

Laravel app root: `/var/www/html/adapter`

Admin page (after merging `laravel/` and the sidebar snippet):

```text
https://0609.readies.biz/admin/ftd-trusted
```

Sidebar item: `0609-admin-sidebar.blade.php`

## API

Classify and paid stay on the payment path. Upload is admin-only.

- `GET /ftd-trusted/api.php?action=status`
- `POST /ftd-trusted/api.php?action=classify`
- `POST /ftd-trusted/api.php?action=paid`
- `POST /ftd-trusted/api.php?action=upload` — 0609 staff only

Classify body:

```json
{
  "email": "a@b.com",
  "phone": "+31612345678",
  "card_first6": "424242",
  "card_last4": "4242",
  "birthday": "1990-05-01",
  "full_name": "Jane Doe",
  "provider": "P003",
  "merchant": "shop-a",
  "biz": "gambling"
}
```

A successful pay call stores the identifiers and returns **trusted**.

## Admin upload for a merchant

Staff choose the merchant, then upload that merchant’s CSV. That file **replaces the whole trusted list for that merchant**. After upload, classify/paid for that merchant uses only that list (plus later successful payments).

```text
POST /admin/ftd-trusted/upload
merchant=shop-a
file=merchant-list.csv
```

CSV columns: `email,phone,card_first6,card_last4,birthday,full_name,biz`

Example file: `standalone/merchant-list.example.csv`

## Biz column

One column: `biz` — the vertical they pay for. Examples:

- gambling
- gaming
- mlm
- food_supplements
- pharma
- forex
- digital_products
- other

`biz` is stored on the record. It is not a match key.

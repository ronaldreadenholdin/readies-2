# FTD vs trusted list

One list for every provider. Two states only:

- **FTD** — not on the list
- **trusted** — on the list, or paid once successfully

No extra product names in this pack.

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

## API

- `GET /ftd-trusted/api.php?action=status`
- `POST /ftd-trusted/api.php?action=classify`
- `POST /ftd-trusted/api.php?action=paid`

Classify body:

```json
{
  "email": "a@b.com",
  "phone": "+31612345678",
  "card_first6": "424242",
  "card_last4": "4242",
  "birthday": "1990-05-01",
  "full_name": "Jane Doe",
  "provider": "P003"
}
```

A successful pay call stores the identifiers and returns **trusted**.

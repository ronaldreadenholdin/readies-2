# Cancel the old Replit Bob completely

The old Replit Bob is on the live 0609 host, not in this GitHub repo.

Keep only BOB C:

```text
/var/www/html/adapter/public/bob-c/
```

## Correct path on the VPS

After BOB C is uploaded, the script is here:

```text
/var/www/html/adapter/public/bob-c/remove-replit-bob.sh
```

Run:

```bash
bash /var/www/html/adapter/public/bob-c/remove-replit-bob.sh /var/www/html/adapter
```

If that file is missing, download it onto the VPS:

```bash
curl -fsSL "https://raw.githubusercontent.com/ronaldreadenholdin/readies-2/okepaybob-c-sidebar-06bd/remove-replit-bob.sh" -o /var/www/html/adapter/public/bob-c/remove-replit-bob.sh
chmod +x /var/www/html/adapter/public/bob-c/remove-replit-bob.sh
bash /var/www/html/adapter/public/bob-c/remove-replit-bob.sh /var/www/html/adapter
```

The script:

1. Does not touch `/var/www/html/adapter/public/bob-c/`
2. Moves old Replit Bob files out of the app
3. Removes old `/bob` sidebar links
4. Puts a **BOB C** link to `/bob-c/` in `layouts.adminpanel` if missing
5. Clears Laravel route/view/config cache

Old files go to:

```text
/var/www/html/adapter/_removed_replit_bob_YYYYMMDDHHMMSS/
```

After it looks good, you can delete that quarantine folder.

## Manual check

Search for leftover old Bob (must not match `bob-c`):

```bash
grep -RIn --exclude-dir=bob-c --exclude-dir=vendor --exclude-dir=_removed_replit_bob* \
  -e 'replit' -e 'href=.*/bob' /var/www/html/adapter/resources /var/www/html/adapter/routes
```

Open only:

```text
https://0609.readies.biz/bob-c/
```

The old Replit Bob URL should 404 or redirect nowhere.

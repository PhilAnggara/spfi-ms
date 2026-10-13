# HTTPS LAN (Caddy) — SPFI-MS

Enable camera / QR scan from phones and other PCs on the office LAN. No domain required.

## What changes for users

| URL | Purpose |
|-----|---------|
| `http://192.168.11.16:8000` | Production as before (camera blocked by browsers) |
| `https://192.168.11.16` | Production over HTTPS (camera allowed) |
| `http://192.168.11.16:8001` | Development — unchanged |

## One-time setup (server PC)

1. Install [Caddy](https://caddyserver.com/download) (or `winget install caddy`).
2. Confirm [`Caddyfile`](../Caddyfile) IP matches this PC (`192.168.11.16`).
3. Open Windows Firewall inbound **TCP 443**.
4. Run Caddy **as Administrator once** so `tls internal` can install the local CA:
   ```bat
   caddy run --config Caddyfile
   ```

## Daily startup

Same as usual:

```bat
php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:work
php artisan reverb:start
```

When camera / HTTPS is needed, add:

```bat
caddy run --config Caddyfile
```

Development on `:8001` does not need Caddy.

## Notes

- First HTTPS visit may show a certificate warning (internal CA). Clients can proceed once, or install Caddy’s root CA.
- Leave `SESSION_SECURE_COOKIE` unset so login still works on HTTP `:8000`.
- Echo/Reverb on HTTPS uses port **443** through Caddy (`/app/*`); on HTTP it still uses Reverb’s direct port (e.g. `8080`).
- Optional: set `APP_URL=https://192.168.11.16` if generated links should prefer HTTPS.

## Verification

1. Start Laravel on `:8000`, queue, Reverb, then Caddy.
2. From another PC or phone on the LAN, open `https://192.168.11.16` (accept the certificate warning if prompted).
3. Log in, open Count Tag Non-FG scan, start the camera → allow permission → QR scan works.
4. Confirm you stay logged in (no cookie/session break).
5. Confirm `http://192.168.11.16:8000` still works for normal use, and `:8001` is unchanged.

## Rollback

Stop Caddy. HTTP `:8000` / `:8001` keep working like before.

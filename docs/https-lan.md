# HTTPS LAN (Caddy) — SPFI-MS

Enable camera / QR scan from phones and other PCs on the office LAN. No domain required.

## Config per machine

`Caddyfile` is **gitignored** (each laptop/PC has a different LAN IP).

1. Copy the template once on this machine:
   ```bat
   copy Caddyfile.example Caddyfile
   ```
2. Edit `Caddyfile`: replace `YOUR_LAN_IP` with this machine’s IP (e.g. `192.168.11.15` or `192.168.11.16`).

## What changes for users

| URL | Purpose |
|-----|---------|
| `http://YOUR_LAN_IP:8000` | Production as before (camera blocked by browsers) |
| `https://YOUR_LAN_IP` | Production over HTTPS (camera allowed) |
| `http://YOUR_LAN_IP:8001` | Development — unchanged |

## One-time setup (this machine)

1. Install [Caddy](https://caddyserver.com/download) (or `winget install caddy`).
2. Create local `Caddyfile` from `Caddyfile.example` (see above).
3. Open Windows Firewall inbound **TCP 443**.
4. Run Caddy **as Administrator once** so `tls internal` can install the local CA:
   ```bat
   cd C:\xampp\htdocs\laravel\spfi-ms
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
- Keep `SESSION_SECURE_COOKIE=false` so login works on both `http://IP:8000` and `https://IP`. If this is missing/`null`, opening HTTPS first then `:8000` often shows **419 Page Expired** (Secure cookie not sent on HTTP).
- After changing session cookie settings, clear site cookies for that IP (or use a private window) once.
- Echo/Reverb on HTTPS uses port **443** through Caddy (`/app/*`); on HTTP it still uses Reverb’s direct port (e.g. `8080`).
- Optional: set `APP_URL=https://YOUR_LAN_IP` if generated links should prefer HTTPS.

## Verification

1. Start Laravel on `:8000`, queue, Reverb, then Caddy.
2. From another PC or phone on the LAN, open `https://YOUR_LAN_IP` (accept the certificate warning if prompted).
3. Log in, open Count Tag Non-FG scan, start the camera → allow permission → QR scan works.
4. Confirm you stay logged in (no cookie/session break).
5. Confirm `http://YOUR_LAN_IP:8000` still works for normal use, and `:8001` is unchanged.

## Rollback

Stop Caddy. HTTP `:8000` / `:8001` keep working like before.

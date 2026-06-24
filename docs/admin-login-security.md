# Admin login security

The admin area applies the following protections:

- Five failed sign-in attempts from the same IP address or for the same email address lock sign-in for 15 minutes.
- Failed and successful sign-ins are logged in `admin_login_attempts`. The table never contains passwords.
- Authenticated sessions expire after 30 minutes without activity or after 8 hours in total.
- Session cookies are `HttpOnly` and `SameSite=Strict`; they are marked `Secure` whenever HTTPS is detected.

## Existing installations

Run this once from the project directory after deployment to create the login-attempt table:

```sh
php database/setup_admin_security.php
```

Fresh installations receive the same table from `database/schema.sql`.

## HTTPS behind a reverse proxy

Most cPanel HTTPS installations set `HTTPS=on` automatically. If a trusted reverse proxy terminates TLS and supplies `X-Forwarded-Proto`, `X-Forwarded-SSL`, or `Forwarded`, set this server environment variable:

```text
DESIGN24_TRUST_PROXY_HEADERS=1
```

Set it only when the web server receives traffic exclusively from a trusted proxy; otherwise a direct client could forge forwarding headers. When enabled, the original client IP from `X-Forwarded-For` is also used for rate limiting.

# Production security headers

Security headers are sent centrally by `config/security-headers.php`, which is loaded by `config/database.php`. This covers public PHP pages and the admin panel before HTML is sent.

The policy includes CSP, `nosniff`, same-origin framing, a strict referrer policy, a restrictive permissions policy, and HSTS only when the request is confirmed as HTTPS.

The CSP permits the existing inline styles/scripts, same-origin assets, YouTube/map frames, external HTTPS images/media, and Cloudflare Turnstile when enabled. It does not allow plugins or `unsafe-eval`.

If TLS is terminated at a trusted reverse proxy, set:

```text
DESIGN24_TRUST_PROXY_HEADERS=1
```

This enables forwarded HTTPS detection for the Secure session cookie and HSTS. Set it only when the origin receives traffic from trusted proxies.

## Verify after deployment

```sh
curl -I https://your-domain.example/
curl -I https://your-domain.example/admin/login.php
```

Confirm that both responses contain `Content-Security-Policy`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy`. Confirm that `Strict-Transport-Security` appears only on HTTPS responses.

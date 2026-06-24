# Public form abuse protection

The public consultation and feedback forms share these protections:

- A hidden honeypot field blocks simple automated submissions.
- Limits: six attempts per IP address and three per browser session per hour, for each form.
- All attempts and abuse outcomes are recorded in `public_form_events`, without form values, passwords, or uploaded filenames.
- Consultation uploads allow at most five verified JPG, PNG, or WebP images; each is limited to 5 MB and all images together to 15 MB.
- Feedback uploads allow one verified image for each optional image field, up to 5 MB each.
- Successful feedback uses redirect-after-POST to prevent refresh/retry submission loops.

## Existing installations

Run once after deployment:

```sh
php database/setup_public_form_security.php
```

Fresh installations receive the table through `database/schema.sql`.

## Optional free CAPTCHA

The forms support [Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/) when both environment variables are set:

```text
TURNSTILE_SITE_KEY=your-site-key
TURNSTILE_SECRET_KEY=your-secret-key
```

Without those settings, the CAPTCHA is disabled and the other protections remain active. If a trusted reverse proxy passes the original visitor IP, set `DESIGN24_TRUST_PROXY_HEADERS=1` as documented for the admin login security.

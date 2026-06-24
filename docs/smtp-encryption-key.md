# SMTP encryption key

`CONSULTATION_SETTINGS_KEY` protects the stored Gmail/SMTP App Password. It is required whenever the application encrypts or decrypts SMTP credentials. There is no built-in fallback key.

## Set it in Namecheap/cPanel

1. Open cPanel **File Manager**.
2. Go to your account home directory, one level above `public_html`.
3. Create `design24-private` if it does not exist.
4. Create `/home/CPANEL_USERNAME/design24-private/.env`.
5. Copy the variable names from `.env.example` into that file.
6. Generate a value with:

   ```sh
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

7. Paste the result after `CONSULTATION_SETTINGS_KEY=`. Do not add quotes unless the value contains spaces.
8. Set the private `.env` file to permission `600` or `640`.

Hosting-provided environment variables take priority over the private file. If Namecheap support configures PHP-FPM environment variables for you, set the same name there instead.

## Existing SMTP settings

Changing the key makes old encrypted SMTP passwords unreadable by design. After setting or changing it, go to **Admin → Communication Settings**, enter the Gmail App Password again, and save. Do not leave the password field blank during this re-save.

## Verification

1. Confirm `APP_ENV=production` and `CONSULTATION_SETTINGS_KEY` are present in the private `.env` file.
2. Sign in to the admin panel and open **Communication Settings**.
3. Enter the Gmail App Password and save. A success message confirms encryption completed.
4. Submit a consultation using a test email address.
5. Confirm the customer/admin notification arrives, if SMTP is configured.
6. If it fails, check the private PHP error log; the public booking page will not expose key or SMTP details.

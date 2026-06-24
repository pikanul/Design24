# Namecheap/cPanel database setup for Design24 Studio

These settings keep production database credentials out of the website code.

## Recommended production option: MySQL

1. Open cPanel.
2. Go to **MySQL Databases**.
3. Create a database, for example `cpaneluser_design24`.
4. Create a database user, for example `cpaneluser_design24user`.
5. Generate a strong password and save it in your password manager.
6. Add the user to the database with the required privileges.
7. Import `database/schema.sql` through phpMyAdmin or cPanel's database tools.
8. Set these environment variables in your hosting configuration:

```text
APP_ENV=production
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cpaneluser_design24
DB_USER=cpaneluser_design24user
DB_PASSWORD=your-secure-password
```

Do not put the database password in PHP source files.

## Alternative: SQLite

SQLite is acceptable only if the database file is outside `public_html`.

1. Create a private folder outside `public_html`, for example:

```text
/home/cpaneluser/design24-private/
```

2. Move the SQLite database there:

```text
/home/cpaneluser/design24-private/design24.sqlite
```

3. Set these environment variables:

```text
APP_ENV=production
DB_DRIVER=sqlite
DATABASE_PATH=/home/cpaneluser/design24-private/design24.sqlite
```

The app will refuse to use a production SQLite database located inside the website folder.

## Files that should not be public

Keep these outside `public_html` when possible, or protected by `.htaccess` deny rules:

- `database/design24.sqlite`
- `backups/`
- `config/`
- `database/setup_*.php`
- `database/seed_team.php`
- `database/create_admin.php`
- `.env`
- `composer.json`
- `composer.lock`
- `php.ini`

## Notes about cPanel environment variables

Different Namecheap/cPanel plans expose PHP environment variables differently. Use the safest option available on your hosting account:

- cPanel **Setup Node.js/Python/Ruby App** style environment variable manager, if available for the app runtime.
- Apache/PHP-FPM pool environment variables, if your hosting support can set them.
- A private server-level include outside `public_html`, if your hosting support provides one.

Avoid hardcoding secrets in PHP files inside `public_html`.

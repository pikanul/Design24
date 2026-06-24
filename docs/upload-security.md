# Upload security

All upload directories now disable directory listing and script execution through their inherited `.htaccess` policy. PHP, scripts, HTML, SVG, PHAR, executable, and shell-file extensions are denied even if such a file is placed in an upload directory.

Uploads are accepted only when server-side MIME validation maps them to an allowed extension. Stored names are random and do not retain user-supplied filenames.

## Private consultation attachments

New consultation images are stored outside the web root by default at a sibling directory named `design24-private/consultations`. On production, set an absolute writable private path if needed:

```text
CONSULTATION_ATTACHMENT_DIR=/absolute/private/path/consultations
```

The directory must be outside the website root. Private files have no public URL. Administrators can retrieve an attachment through `admin/consultation-attachment.php?id=ATTACHMENT_ID` after authenticating.

Legacy files in `uploads/consultations` are blocked from direct access and can still be read through that authenticated handler until they are removed with their booking.

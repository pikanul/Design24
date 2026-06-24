# Consultation communications

## Admin settings

Open `/admin/communication-settings.php` to configure Gmail SMTP and WhatsApp. The page stores the SMTP host, port, Gmail address, encrypted Gmail App Password, sender name, administrator notification address, WhatsApp Business number, and message template in `consultation_settings`.

The SMTP password is never rendered after saving. It is AES-256-CBC encrypted before it is stored. Set a stable, private `CONSULTATION_SETTINGS_KEY` environment variable before production deployment; changing that key makes previously saved SMTP passwords unreadable.

## Gmail SMTP setup

1. Turn on 2-Step Verification for the Gmail account that will send messages.
2. In the Google Account security area, create an **App Password** for Mail (or a custom “Design24” entry).
3. Copy the 16-character app password. Do not use the normal Gmail password.
4. In Communication Settings, enter `smtp.gmail.com`, port `587`, TLS, the Gmail address as the username, and the App Password.
5. Set the admin notification email and save. New booking emails go to both the customer (when supplied) and this administrator.

The booking service sends responsive branded HTML messages for a new request and whenever an administrator changes its status, including Confirmed, Rescheduled, Cancelled, and Completed. Failed SMTP deliveries are logged server-side without exposing credentials to customers.

## WhatsApp message generation

Enter a WhatsApp Business number in international format, for example `8801711293205`. On a successful booking, the confirmation screen shows **Chat on WhatsApp**. It opens `https://wa.me/<number>?text=<encoded message>`.

The editable template accepts these placeholders:

- `{customer_name}`
- `{reference_number}`
- `{consultation_type}`
- `{consultation_date}`
- `{time_slot}`

## Architecture

`includes/consultation.php` is the communication boundary. `consultationNotify()` publishes booking events, `consultationSendEmail()` is the SMTP channel, and `consultationWhatsAppUrl()` is the click-to-chat channel. Official WhatsApp API, Calendar, SMS, Zoom, and Meet integrations can be added as additional channel functions without changing form or admin controllers.

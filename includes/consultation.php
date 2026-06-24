<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

const CONSULTATION_SLOTS = ['10:00 AM', '11:00 AM', '12:00 PM', '2:00 PM', '3:00 PM', '4:00 PM', '5:00 PM'];
const CONSULTATION_PROJECT_TYPES = ['Kitchen Design', 'Living Room Design', 'Bedroom Interior', 'Dining Space', 'Duplex Residence', 'Office Interior', 'Commercial Interior', 'Custom Furniture', 'Other'];
const CONSULTATION_TYPES = ['Phone Call', 'WhatsApp Call', 'Video Meeting', 'Office Visit', 'Site Visit'];
const CONSULTATION_STATUSES = ['Pending', 'Approved', 'Confirmed', 'Rescheduled', 'Completed', 'Cancelled'];

function consultationEnsureSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS consultation_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, business_days VARCHAR(60) NOT NULL DEFAULT '1,2,3,4,5,6', admin_email VARCHAR(190) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS consultation_bookings (id INTEGER PRIMARY KEY AUTOINCREMENT, reference_number VARCHAR(40) NOT NULL UNIQUE, full_name VARCHAR(180) NOT NULL, mobile_number VARCHAR(40) NOT NULL, email VARCHAR(190) NULL, project_type VARCHAR(80) NOT NULL, consultation_type VARCHAR(60) NOT NULL, consultation_date DATE NOT NULL, time_slot VARCHAR(20) NOT NULL, project_location VARCHAR(300) NULL, project_size VARCHAR(80) NULL, budget_range VARCHAR(120) NULL, project_details TEXT NULL, terms_accepted INTEGER NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'Pending', admin_notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(consultation_date, time_slot))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS consultation_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT, booking_id INTEGER NOT NULL, stored_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size INTEGER NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (booking_id) REFERENCES consultation_bookings(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_consultation_status_date ON consultation_bookings(status, consultation_date)");
    $columns = array_column($pdo->query('PRAGMA table_info(consultation_settings)')->fetchAll(), 'name');
    foreach (['smtp_host'=>'VARCHAR(190) NULL','smtp_port'=>'INTEGER NULL','smtp_username'=>'VARCHAR(190) NULL','smtp_password_encrypted'=>'TEXT NULL','smtp_encryption'=>'VARCHAR(20) NULL','sender_name'=>'VARCHAR(190) NULL','whatsapp_number'=>'VARCHAR(40) NULL','whatsapp_template'=>'TEXT NULL'] as $column => $type) {
        if (!in_array($column, $columns, true)) $pdo->exec("ALTER TABLE consultation_settings ADD COLUMN {$column} {$type}");
    }
    if (!(int) $pdo->query('SELECT COUNT(*) FROM consultation_settings')->fetchColumn()) {
        $pdo->prepare('INSERT INTO consultation_settings (business_days, admin_email, smtp_host, smtp_port, smtp_encryption, sender_name, whatsapp_template) VALUES (:days, :email, :host, :port, :encryption, :sender, :template)')->execute([':days' => '1,2,3,4,5,6', ':email' => '', ':host' => 'smtp.gmail.com', ':port' => 587, ':encryption' => 'tls', ':sender' => 'Design24 Studio', ':template' => 'Hello Design24 Studio, I am {customer_name}. My consultation reference is {reference_number} for a {consultation_type} on {consultation_date} at {time_slot}.']);
    }
}

function consultationSettings(PDO $pdo): array
{
    consultationEnsureSchema($pdo);
    return $pdo->query('SELECT * FROM consultation_settings ORDER BY id LIMIT 1')->fetch() ?: ['business_days' => '1,2,3,4,5,6', 'admin_email' => ''];
}

function consultationBusinessDays(array $settings): array { return array_map('intval', array_filter(explode(',', (string) $settings['business_days']), 'is_numeric')); }
function consultationDateIsAvailable(string $date, array $settings): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date && $date >= date('Y-m-d') && in_array((int) $parsed->format('N'), consultationBusinessDays($settings), true);
}
function consultationBookedSlots(PDO $pdo, string $date): array
{
    $q = $pdo->prepare("SELECT time_slot FROM consultation_bookings WHERE consultation_date=:date AND status <> 'Cancelled'");
    $q->execute([':date' => $date]); return $q->fetchAll(PDO::FETCH_COLUMN);
}
function consultationCsrf(): string { if (session_status() !== PHP_SESSION_ACTIVE) session_start(); if (empty($_SESSION['consultation_csrf'])) $_SESSION['consultation_csrf'] = bin2hex(random_bytes(32)); return $_SESSION['consultation_csrf']; }
function consultationCsrfValid(?string $value): bool { return is_string($value) && isset($_SESSION['consultation_csrf']) && hash_equals($_SESSION['consultation_csrf'], $value); }
function consultationAttachmentDirectory(): string
{
    $configured = trim((string) getenv('CONSULTATION_ATTACHMENT_DIR'));
    $projectRoot = dirname(__DIR__);
    $environment = strtolower(trim((string) (getenv('APP_ENV') ?: 'development')));
    if ($configured === '' && in_array($environment, ['production', 'prod'], true)) {
        error_log('CONSULTATION_ATTACHMENT_DIR is missing in production.');
        throw new RuntimeException('Private consultation attachment storage is not configured.');
    }
    $directory = $configured !== '' ? $configured : dirname($projectRoot) . '/design24-private/consultations';
    if ($directory === '' || $directory[0] !== DIRECTORY_SEPARATOR) throw new RuntimeException('Consultation attachment storage must be an absolute private path.');
    $directory = rtrim($directory, DIRECTORY_SEPARATOR);
    if (str_starts_with($directory . DIRECTORY_SEPARATOR, rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) throw new RuntimeException('Consultation attachment storage must be outside the web root.');
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('Private attachment storage is unavailable.');
    return $directory;
}
function consultationAttachmentPath(string $storedName): string
{
    $storedName = basename($storedName);
    if (!preg_match('/^[a-f0-9]{40}\.(?:jpg|png|webp)$/', $storedName)) return '';
    $privatePath = consultationAttachmentDirectory() . DIRECTORY_SEPARATOR . $storedName;
    if (is_file($privatePath)) return $privatePath;
    $legacyPath = dirname(__DIR__) . '/uploads/consultations/' . $storedName;
    return is_file($legacyPath) ? $legacyPath : '';
}
function consultationReference(PDO $pdo, string $date): string
{
    $prefix = 'CONS-' . str_replace('-', '', $date) . '-';
    $q = $pdo->prepare('SELECT COUNT(*) FROM consultation_bookings WHERE reference_number LIKE :prefix'); $q->execute([':prefix' => $prefix . '%']);
    return $prefix . str_pad((string) ((int) $q->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
}
function consultationSettingsKey(): string
{
    $key = trim((string) getenv('CONSULTATION_SETTINGS_KEY'));
    if ($key === '') {
        error_log('CONSULTATION_SETTINGS_KEY is missing; SMTP password encryption/decryption was blocked.');
        throw new RuntimeException('SMTP encryption is not configured.');
    }
    return hash('sha256', $key, true);
}
function consultationEncrypt(string $plain): string
{
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', consultationSettingsKey(), OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) throw new RuntimeException('SMTP password encryption failed.');
    return base64_encode($iv . $cipher);
}
function consultationDecrypt(string $cipher): string
{
    $raw = base64_decode($cipher, true);
    if ($raw === false || strlen($raw) < 17) throw new RuntimeException('Stored SMTP password is invalid.');
    $plain = openssl_decrypt(substr($raw, 16), 'AES-256-CBC', consultationSettingsKey(), OPENSSL_RAW_DATA, substr($raw, 0, 16));
    if (!is_string($plain) || $plain === '') throw new RuntimeException('Stored SMTP password could not be decrypted.');
    return $plain;
}
function consultationEmailHtml(array $booking, string $heading, string $intro): string
{
    $esc=static fn($value): string => htmlspecialchars(trim((string)$value) !== '' ? (string)$value : 'Not provided',ENT_QUOTES,'UTF-8');
    $row=static fn(string $label, $value, bool $last=false): string => '<tr><td style="width:38%;padding:11px 12px;font-weight:700;vertical-align:top;border-bottom:'.($last?'0':'1px solid #d9e2de').'">'.$label.'</td><td style="padding:11px 12px;vertical-align:top;border-bottom:'.($last?'0':'1px solid #d9e2de').'">'.$value.'</td></tr>';
    $details=trim((string)($booking['project_details']??''));
    $rows='';
    $rows.=$row('Reference ID','<strong>'.$esc($booking['reference_number']??'').'</strong>');
    $rows.=$row('Client name',$esc($booking['full_name']??''));
    $rows.=$row('Email',$esc($booking['email']??''));
    $rows.=$row('Phone number',$esc($booking['mobile_number']??''));
    $rows.=$row('Project type',$esc($booking['project_type']??''));
    $rows.=$row('Consultation type',$esc($booking['consultation_type']??''));
    $rows.=$row('Preferred date',$esc($booking['consultation_date']??''));
    $rows.=$row('Preferred time',$esc($booking['time_slot']??''));
    $rows.=$row('Project location',$esc($booking['project_location']??''));
    $rows.=$row('Approx. project size',$esc($booking['project_size']??''));
    $rows.=$row('Estimated budget',$esc($booking['budget_range']??''));
    $rows.=$row('Message / project details',$details!==''?nl2br($esc($details)):'Not provided');
    $rows.=$row('Reference images',isset($booking['attachment_count']) && (int)$booking['attachment_count'] > 0 ? (int)$booking['attachment_count'].' file(s) uploaded' : 'Not provided');
    $rows.=$row('Terms accepted',!empty($booking['terms_accepted'])?'Yes':'Not provided');
    $rows.=$row('Submitted at',$esc($booking['created_at']??''),true);
    return '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1.0"></head><body style="margin:0;background:#f7f3ec;font-family:Arial,sans-serif;color:#24312b"><div style="max-width:620px;margin:24px auto;background:#fff"><div style="padding:28px;background:#07553e;color:#fff"><strong style="font-size:22px">Design24 Studio</strong></div><div style="padding:30px"><h1 style="color:#07553e;font-size:24px">'.$esc($heading).'</h1><p style="line-height:1.6">'.$esc($intro).'</p><table role="presentation" style="width:100%;border-collapse:collapse;border:1px solid #d9e2de;font-size:14px">'.$rows.'</table><p style="margin-top:26px">Design24 Studio</p></div></div></body></html>';
}
function consultationSendEmail(array $settings, string $to, string $subject, string $html): bool
{
    if ($to==='' || preg_match('/[\r\n]/', $to) || preg_match('/[\r\n]/', $subject) || !filter_var($to,FILTER_VALIDATE_EMAIL) || empty($settings['smtp_host']) || empty($settings['smtp_username']) || empty($settings['smtp_password_encrypted'])) return false;
    $autoload=dirname(__DIR__).'/vendor/autoload.php'; if(!is_file($autoload)){error_log('PHPMailer is not installed.');return false;} require_once $autoload;
    try {$mail=new \PHPMailer\PHPMailer\PHPMailer(true);$mail->isSMTP();$mail->Host=$settings['smtp_host'];$mail->Port=(int)($settings['smtp_port']?:587);$mail->SMTPAuth=true;$mail->Username=$settings['smtp_username'];$mail->Password=consultationDecrypt($settings['smtp_password_encrypted']);$mail->SMTPSecure=($settings['smtp_encryption']??'tls')==='ssl'?\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS:\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;$mail->CharSet='UTF-8';$mail->setFrom($settings['smtp_username'],$settings['sender_name']?:'Design24 Studio');$mail->addAddress($to);$mail->isHTML(true);$mail->Subject=$subject;$mail->Body=$html;$mail->AltBody=strip_tags($html);$mail->send();return true;}catch(Throwable $e){error_log('Consultation email: '.$e->getMessage());return false;}
}
function consultationNotify(array $booking, array $settings, string $event): void
{
    $isNew=$event==='created';$label=$isNew?'Consultation request received':'Consultation update: '.$event;$intro=$isNew?'Thank you. We received your consultation request and will contact you shortly.':'Your consultation booking has been updated to '.$event.'.';
    $subject=$isNew?'New consultation · '.$booking['reference_number']:$label.' · '.$booking['reference_number'];
    if(!empty($booking['email'])) consultationSendEmail($settings,$booking['email'],$subject,consultationEmailHtml($booking,$label,$intro));
    if($isNew&&!empty($settings['admin_email'])) consultationSendEmail($settings,$settings['admin_email'],'New consultation · '.$booking['reference_number'],consultationEmailHtml($booking,'New consultation request','A new consultation request needs your review.'));
}
function consultationWhatsAppUrl(array $booking,array $settings): string
{
    $number=preg_replace('/\D+/','',(string)($settings['whatsapp_number']??''));if($number==='')return '';$template=(string)($settings['whatsapp_template']??'');$message=strtr($template,['{customer_name}'=>$booking['full_name']??'','{reference_number}'=>$booking['reference_number']??'','{consultation_type}'=>$booking['consultation_type']??'','{consultation_date}'=>$booking['consultation_date']??'','{time_slot}'=>$booking['time_slot']??'']);return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/site-settings.php';
require_once __DIR__ . '/includes/public-form-security.php';

publicFormStartSession();
if (empty($_SESSION['feedback_csrf'])) $_SESSION['feedback_csrf'] = bin2hex(random_bytes(32));

$pdo = db();
$errors = [];
$submitted = isset($_GET['submitted']) && $_GET['submitted'] === '1';
$form = ['company_name'=>'','person_name'=>'','designation'=>'','location'=>'','email'=>'','rating'=>'5','feedback'=>''];

function storeFeedbackImage(array $upload): string
{
    $directory = __DIR__ . '/public/uploads/testimonials';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The image directory is unavailable.');
    }
    $path = 'public/uploads/testimonials/' . bin2hex(random_bytes(16)) . '.' . $upload['extension'];
    if (!move_uploaded_file($upload['tmp_name'], __DIR__ . '/' . $path)) {
        throw new RuntimeException('The image could not be saved.');
    }
    return $path;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['feedback_csrf'], (string) $_POST['csrf_token'])) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($abuseError = publicFormAbuseError($pdo, 'feedback', $_POST['website_url'] ?? null)) {
        $errors[] = $abuseError;
    } else {
        foreach ($form as $key => $default) $form[$key] = trim((string) ($_POST[$key] ?? $default));
        if (mb_strlen($form['company_name']) < 2 || mb_strlen($form['company_name']) > 180) $errors[] = 'Enter your name or company name.';
        if ($form['email'] !== '' && !publicFormEmailIsSafe($form['email'])) $errors[] = 'Enter a valid email address.';
        if (mb_strlen($form['feedback']) < 10 || mb_strlen($form['feedback']) > 5000) $errors[] = 'Feedback must be between 10 and 5,000 characters.';
        $rating = filter_var($form['rating'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>5]]);
        if ($rating === false) $errors[] = 'Choose a rating.';
        $logo = publicFormValidateImageUploads($_FILES['client_logo'] ?? null, 1, PUBLIC_FORM_MAX_IMAGE_BYTES);
        $photo = publicFormValidateImageUploads($_FILES['client_image'] ?? null, 1, PUBLIC_FORM_MAX_IMAGE_BYTES);
        $errors = array_merge($errors, $logo['errors'], $photo['errors']);

        if ($errors) {
            publicFormLog($pdo, 'feedback', 'validation_failed');
        } else {
            $storedPaths = [];
            try {
                $logoPath = $logo['files'] ? storeFeedbackImage($logo['files'][0]) : '';
                if ($logoPath !== '') $storedPaths[] = $logoPath;
                $photoPath = $photo['files'] ? storeFeedbackImage($photo['files'][0]) : '';
                if ($photoPath !== '') $storedPaths[] = $photoPath;
                $short = mb_strimwidth(preg_replace('/\s+/', ' ', $form['feedback']), 0, 500, '…');
                $order = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM testimonials')->fetchColumn();
                $pdo->prepare('INSERT INTO testimonials(client_logo,client_image,company_name,person_name,designation,location,short_feedback,full_feedback,rating,sort_order,status) VALUES(?,?,?,?,?,?,?,?,?,?,0)')->execute([$logoPath,$photoPath,mb_substr($form['company_name'],0,180),mb_substr($form['person_name'],0,180),mb_substr($form['designation'],0,180),mb_substr($form['location'],0,180),$short,$form['feedback'],$rating,$order]);
                publicFormLog($pdo, 'feedback', 'accepted');
                $_SESSION['feedback_csrf'] = bin2hex(random_bytes(32));
                header('Location: /give-feedback.php?submitted=1');
                exit;
            } catch (Throwable $exception) {
                foreach ($storedPaths as $path) if (is_file(__DIR__ . '/' . $path)) unlink(__DIR__ . '/' . $path);
                publicFormLog($pdo, 'feedback', 'storage_failed');
                error_log($exception->getMessage());
                $errors[] = 'We could not save your feedback. Please try again.';
            }
        }
    }
}

$headerSettings = getHeaderSettings();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Give Feedback | Design24 Studio</title><link rel="stylesheet" href="assets/css/style.css"><style>.feedback-page{max-width:760px;margin:54px auto;padding:0 20px 70px}.feedback-card{padding:clamp(24px,5vw,48px);border-radius:12px;background:#fff;box-shadow:0 14px 40px rgba(6,61,46,.1)}.feedback-card h1{margin-top:0;color:#07553e}.feedback-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.feedback-card label{display:block;margin-bottom:7px;font-weight:700}.feedback-card input,.feedback-card select,.feedback-card textarea{width:100%;padding:12px;border:1px solid #b7c4bf;border-radius:5px;font:inherit}.feedback-card textarea{min-height:150px}.feedback-card button{margin-top:24px;padding:13px 24px;border:0;border-radius:5px;background:#07553e;color:#fff;font-weight:700;cursor:pointer}.feedback-card .wide{grid-column:1/-1}.feedback-message{padding:16px;border-radius:6px;background:#edf9f4;color:#07553e}.feedback-error{padding:12px;border-radius:6px;background:#fff0f0;color:#8b1e1e}@media(max-width:600px){.feedback-grid{grid-template-columns:1fr}.feedback-card .wide{grid-column:auto}}</style><?php if (publicFormCaptchaEnabled()): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?></head><body class="<?=settingEnabled($headerSettings,'show_top_bar')?'has-top-bar':'no-top-bar'?>"><?php require __DIR__.'/includes/site-header.php';?><main class="feedback-page"><section class="feedback-card"><?php if($submitted):?><div class="feedback-message"><h1>Thank you for your feedback</h1><p>Your feedback has been submitted for review. Once approved, it may appear on our website.</p><a href="/testimonials">View client feedback</a></div><?php else:?><h1>Give Your Valuable Feedback</h1><p>We appreciate your experience with Design24 Studio. Your submission is reviewed before publication.</p><?php if($errors):?><div class="feedback-error"><ul><?php foreach($errors as$e):?><li><?=siteEscape($e)?></li><?php endforeach?></ul></div><?php endif?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=siteEscape($_SESSION['feedback_csrf'])?>"><div aria-hidden="true" style="position:absolute;left:-9999px;overflow:hidden;width:1px;height:1px"><label for="website_url">Leave this field empty</label><input id="website_url" name="website_url" type="text" tabindex="-1" autocomplete="off"></div><div class="feedback-grid"><div><label>Name or Company *</label><input name="company_name" maxlength="180" value="<?=siteEscape($form['company_name'])?>" required></div><div><label>Your Name</label><input name="person_name" maxlength="180" value="<?=siteEscape($form['person_name'])?>"></div><div><label>Email Address</label><input name="email" type="email" maxlength="190" value="<?=siteEscape($form['email'])?>"></div><div><label>Location</label><input name="location" maxlength="180" value="<?=siteEscape($form['location'])?>"></div><div><label>Designation</label><input name="designation" maxlength="180" value="<?=siteEscape($form['designation'])?>"></div><div><label>Rating *</label><select name="rating"><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>" <?=$form['rating']===(string)$i?'selected':''?>><?=$i?> star<?=$i===1?'':'s'?></option><?php endfor?></select></div><div><label>Company Logo</label><input name="client_logo" type="file" accept="image/jpeg,image/png,image/webp"><small>Optional: JPG, PNG, or WebP, max 5 MB.</small></div><div><label>Your Photo</label><input name="client_image" type="file" accept="image/jpeg,image/png,image/webp"><small>Optional: JPG, PNG, or WebP, max 5 MB.</small></div><div class="wide"><label>Your Feedback *</label><textarea name="feedback" maxlength="5000" required><?=siteEscape($form['feedback'])?></textarea></div><?php if (publicFormCaptchaEnabled()): ?><div class="wide"><div class="cf-turnstile" data-sitekey="<?=siteEscape(publicFormCaptchaSiteKey())?>"></div></div><?php endif; ?></div><button>Submit feedback for review</button></form><?php endif?></section></main><?php require __DIR__.'/includes/site-footer.php';?><script src="assets/js/script.js"></script></body></html>

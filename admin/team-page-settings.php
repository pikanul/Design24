<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/site-settings.php';
requireAdmin();

function teamPageUploadedImage(string $path): bool
{
    return preg_match('#^uploads/site/team/page/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $path) === 1;
}

function prepareTeamPageImage(string $field): array
{
    $upload = $_FILES[$field] ?? null;
    $result = ['present' => false, 'error' => '', 'relative' => '', 'absolute' => '', 'temporary' => ''];
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $result;
    $result['present'] = true;
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || (int) ($upload['size'] ?? 0) > 5 * 1024 * 1024) {
        $result['error'] = 'must be 5 MB or smaller.';
        return $result;
    }
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
        $result['error'] = 'could not be verified.';
        return $result;
    }
    $mimeMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
    if (!is_string($mime) || !isset($mimeMap[$mime]) || @getimagesize((string) $upload['tmp_name']) === false) {
        $result['error'] = 'must be a valid JPG, JPEG, PNG, or WebP image.';
        return $result;
    }
    $result['relative'] = 'uploads/site/team/page/' . bin2hex(random_bytes(16)) . '.' . $mimeMap[$mime];
    $result['absolute'] = dirname(__DIR__) . '/' . $result['relative'];
    $result['temporary'] = (string) $upload['tmp_name'];
    return $result;
}

function upsertTeamPageSettings(PDO $pdo, array $settings, array $types): void
{
    $find = $pdo->prepare('SELECT id FROM site_settings WHERE setting_group = :group_name AND setting_key = :setting_key LIMIT 1');
    $update = $pdo->prepare('UPDATE site_settings SET setting_value=:value, setting_type=:type, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
    $insert = $pdo->prepare('INSERT INTO site_settings (setting_group,setting_key,setting_value,setting_type,created_at,updated_at) VALUES (:group_name,:setting_key,:value,:type,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
    foreach ($settings as $key => $value) {
        $find->execute([':group_name' => 'team_page', ':setting_key' => $key]);
        $id = $find->fetchColumn();
        if ($id !== false) $update->execute([':value' => $value, ':type' => $types[$key] ?? 'text', ':id' => (int) $id]);
        else $insert->execute([':group_name' => 'team_page', ':setting_key' => $key, ':value' => $value, ':type' => $types[$key] ?? 'text']);
    }
}

$defaults = teamPageSettingDefaults();
$saved = getTeamPageSettings();
$form = $saved;
$errors = [];
$success = isset($_GET['saved']) && $_GET['saved'] === '1';
$booleans = ['team_show_hero','team_show_leadership','team_show_filters','team_show_member_count','team_show_group_descriptions','team_show_social_icons','team_enable_profile_popup'];
$images = ['team_hero_desktop_image','team_hero_mobile_image','team_og_image'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($defaults) as $key) {
        if (in_array($key, $booleans, true)) $form[$key] = isset($_POST[$key]) ? '1' : '0';
        elseif (!in_array($key, $images, true)) $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    if (!csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) $errors[] = 'Your session expired. Refresh and try again.';
    foreach (['team_hero_label'=>[2,80],'team_hero_heading'=>[2,140],'team_hero_description'=>[10,500],'team_hero_image_alt'=>[2,180],'team_leadership_label'=>[2,80],'team_leadership_title'=>[2,140],'team_leadership_description'=>[10,500],'team_section_heading'=>[2,140],'team_section_description'=>[10,500],'team_meta_title'=>[5,180],'team_meta_description'=>[20,320]] as $key => [$min,$max]) {
        $length=mb_strlen($form[$key]); if($length<$min||$length>$max) $errors[] = str_replace('_',' ',ucfirst($key))." must be between {$min} and {$max} characters.";
    }
    if (!in_array($form['team_hero_alignment'], ['left','center','right'], true)) $errors[]='Select a valid hero alignment.';
    foreach (['team_featured_limit'=>[1,12],'team_desktop_columns'=>[2,6],'team_tablet_columns'=>[1,4],'team_mobile_columns'=>[1,2]] as $key=>[$min,$max]) {
        $value=filter_var($form[$key],FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]]); if($value===false)$errors[]=str_replace('_',' ',ucfirst($key))." must be between {$min} and {$max}.";else$form[$key]=(string)$value;
    }
    $uploads = [
        'team_hero_desktop_image'=>prepareTeamPageImage('team_hero_desktop_upload'),
        'team_hero_mobile_image'=>prepareTeamPageImage('team_hero_mobile_upload'),
        'team_og_image'=>prepareTeamPageImage('team_og_upload'),
    ];
    foreach ($uploads as $key=>$upload) if($upload['error']!=='') $errors[]=ucwords(str_replace('_',' ',$key)).' '.$upload['error'];
    $moved=[];
    if($errors===[]) {
        foreach($uploads as $key=>$upload) if($upload['present']) { if(!move_uploaded_file($upload['temporary'],$upload['absolute'])){$errors[]='An image could not be saved.';break;} $moved[]=$upload['absolute'];$form[$key]=$upload['relative']; }
    }
    if($errors===[]) {
        foreach($images as $key) if(!$uploads[$key]['present']) $form[$key]=isset($_POST['remove_'.$key])?'':$saved[$key];
        $types=array_fill_keys(array_keys($form),'text'); foreach($booleans as $key)$types[$key]='boolean';foreach($images as $key)$types[$key]='image';foreach(['team_featured_limit','team_desktop_columns','team_tablet_columns','team_mobile_columns'] as $key)$types[$key]='number';
        try{$pdo=db();$pdo->beginTransaction();upsertTeamPageSettings($pdo,$form,$types);$pdo->commit();foreach($images as $key){$old=$saved[$key];if($old!==$form[$key]&&teamPageUploadedImage($old)&&is_file(dirname(__DIR__).'/'.$old))unlink(dirname(__DIR__).'/'.$old);}header('Location: team-page-settings.php?saved=1');exit;}catch(Throwable $exception){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();foreach($moved as $file)if(is_file($file))unlink($file);error_log($exception->getMessage());$errors[]='Team Page settings could not be saved.';}
    } else foreach($moved as $file)if(is_file($file))unlink($file);
}

$pageTitle='Team Page Settings';require __DIR__.'/includes/header.php';
?>
<header class="admin-header"><a class="admin-brand" href="dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?=e(currentAdminName())?></span><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><button class="logout-button" type="submit">Logout</button></form></div></header>
<main class="admin-main"><div class="settings-toolbar"><a href="team-management.php">← Team Management</a><a href="../team.php" target="_blank" rel="noopener">View Team Page ↗</a></div><section class="panel"><h1>Team Page Settings</h1><p>Manage public Team Page content and display options.</p><?php if($success):?><p class="success">Team Page settings saved successfully.</p><?php endif;?><?php if($errors!==[]):?><div class="error"><ul class="error-list"><?php foreach($errors as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>">
<section class="settings-section"><h2>Hero Content</h2><div class="settings-grid"><div class="field"><label for="team_hero_label">Small label</label><input id="team_hero_label" name="team_hero_label" value="<?=e($form['team_hero_label'])?>" required></div><div class="field"><label for="team_hero_heading">Main heading</label><input id="team_hero_heading" name="team_hero_heading" value="<?=e($form['team_hero_heading'])?>" required></div></div><div class="field"><label for="team_hero_description">Description</label><textarea id="team_hero_description" name="team_hero_description" required><?=e($form['team_hero_description'])?></textarea></div><div class="settings-grid"><div class="field"><label for="team_hero_image_alt">Image alternative text</label><input id="team_hero_image_alt" name="team_hero_image_alt" value="<?=e($form['team_hero_image_alt'])?>" required></div><div class="field"><label for="team_hero_alignment">Text alignment</label><select id="team_hero_alignment" name="team_hero_alignment"><option value="left"<?=$form['team_hero_alignment']==='left'?' selected':''?>>Left</option><option value="center"<?=$form['team_hero_alignment']==='center'?' selected':''?>>Center</option><option value="right"<?=$form['team_hero_alignment']==='right'?' selected':''?>>Right</option></select></div><div class="field"><label for="team_hero_desktop_upload">Desktop hero image</label><input id="team_hero_desktop_upload" name="team_hero_desktop_upload" type="file" accept="image/jpeg,image/png,image/webp"><?php if($saved['team_hero_desktop_image']):?><img class="logo-preview" src="../<?=e($saved['team_hero_desktop_image'])?>" alt="Current desktop hero"><label><input type="checkbox" name="remove_team_hero_desktop_image"> Remove image</label><?php endif;?></div><div class="field"><label for="team_hero_mobile_upload">Mobile hero image</label><input id="team_hero_mobile_upload" name="team_hero_mobile_upload" type="file" accept="image/jpeg,image/png,image/webp"><?php if($saved['team_hero_mobile_image']):?><img class="logo-preview" src="../<?=e($saved['team_hero_mobile_image'])?>" alt="Current mobile hero"><label><input type="checkbox" name="remove_team_hero_mobile_image"> Remove image</label><?php endif;?></div></div><div class="checkbox-field"><input id="team_show_hero" name="team_show_hero" type="checkbox" value="1"<?=$form['team_show_hero']==='1'?' checked':''?>><label for="team_show_hero">Show hero</label></div></section>
<section class="settings-section"><h2>Leadership Section</h2><div class="settings-grid"><div class="field"><label>Section label</label><input name="team_leadership_label" value="<?=e($form['team_leadership_label'])?>" required></div><div class="field"><label>Section title</label><input name="team_leadership_title" value="<?=e($form['team_leadership_title'])?>" required></div><div class="field"><label>Maximum featured members</label><input name="team_featured_limit" type="number" min="1" max="12" value="<?=e($form['team_featured_limit'])?>"></div></div><div class="field"><label>Description</label><textarea name="team_leadership_description" required><?=e($form['team_leadership_description'])?></textarea></div><div class="checkbox-field"><input id="team_show_leadership" name="team_show_leadership" type="checkbox" value="1"<?=$form['team_show_leadership']==='1'?' checked':''?>><label for="team_show_leadership">Show leadership section</label></div></section>
<section class="settings-section"><h2>General Team Display</h2><div class="settings-grid"><div class="field"><label>Main section heading</label><input name="team_section_heading" value="<?=e($form['team_section_heading'])?>" required></div><div class="field"><label>Desktop columns</label><input name="team_desktop_columns" type="number" min="2" max="6" value="<?=e($form['team_desktop_columns'])?>"></div><div class="field"><label>Tablet columns</label><input name="team_tablet_columns" type="number" min="1" max="4" value="<?=e($form['team_tablet_columns'])?>"></div><div class="field"><label>Mobile columns</label><input name="team_mobile_columns" type="number" min="1" max="2" value="<?=e($form['team_mobile_columns'])?>"></div></div><div class="field"><label>Introductory text</label><textarea name="team_section_description" required><?=e($form['team_section_description'])?></textarea></div><?php foreach(['team_show_filters'=>'Show filter tabs','team_show_member_count'=>'Show member count','team_show_group_descriptions'=>'Show group descriptions','team_show_social_icons'=>'Show social icons','team_enable_profile_popup'=>'Enable member profile popup'] as $key=>$label):?><div class="checkbox-field"><input id="<?=$key?>" name="<?=$key?>" type="checkbox" value="1"<?=$form[$key]==='1'?' checked':''?>><label for="<?=$key?>"><?=$label?></label></div><?php endforeach;?></section>
<section class="settings-section"><h2>SEO</h2><div class="field"><label>Page title</label><input name="team_meta_title" value="<?=e($form['team_meta_title'])?>" required></div><div class="field"><label>Meta description</label><textarea name="team_meta_description" required><?=e($form['team_meta_description'])?></textarea></div><div class="field"><label>Open Graph image</label><input name="team_og_upload" type="file" accept="image/jpeg,image/png,image/webp"><?php if($saved['team_og_image']):?><img class="logo-preview" src="../<?=e($saved['team_og_image'])?>" alt="Current Open Graph image"><label><input type="checkbox" name="remove_team_og_image"> Remove image</label><?php endif;?></div></section>
<div class="form-actions"><button class="primary-button" type="submit">Save Team Page Settings</button><a class="secondary-admin-button" href="team-page-settings.php">Cancel / Reset</a></div></form></section></main><?php require __DIR__.'/includes/footer.php';?>

<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
$pdo = db();
$errors = [];

function testimonialImageIsSafe(string $path): bool
{
    return preg_match('#^public/uploads/testimonials/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $path) === 1;
}

function testimonialDeleteImage(string $path): void
{
    if (!testimonialImageIsSafe($path)) return;
    $absolute = dirname(__DIR__) . '/' . $path;
    if (is_file($absolute)) unlink($absolute);
}

function testimonialUpload(string $field): array
{
    $upload = $_FILES[$field] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['present'=>false,'path'=>'','error'=>''];
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)($upload['size'] ?? 0) > 5*1024*1024 || !is_uploaded_file((string)($upload['tmp_name'] ?? ''))) return ['present'=>true,'path'=>'','error'=>'Image must be a verified file of 5 MB or smaller.'];
    $map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file((string)$upload['tmp_name']);
    if(!is_string($mime)||!isset($map[$mime])||@getimagesize((string)$upload['tmp_name'])===false)return['present'=>true,'path'=>'','error'=>'Upload a valid JPG, JPEG, PNG, or WebP image.'];
    $path='public/uploads/testimonials/'.bin2hex(random_bytes(16)).'.'.$map[$mime];
    if(!move_uploaded_file((string)$upload['tmp_name'],dirname(__DIR__).'/'.$path))return['present'=>true,'path'=>'','error'=>'The image could not be saved.'];
    return['present'=>true,'path'=>$path,'error'=>''];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=(string)($_POST['action']??'');
    if(!csrfIsValid(isset($_POST['csrf_token'])?(string)$_POST['csrf_token']:null))$errors[]='Your session expired. Refresh and try again.';
    elseif($action==='save'){
        $id=max(0,(int)($_POST['id']??0));
        $company=trim((string)($_POST['company_name']??''));$person=trim((string)($_POST['person_name']??''));$designation=trim((string)($_POST['designation']??''));$location=trim((string)($_POST['location']??''));$short=trim((string)($_POST['short_feedback']??''));$full=trim((string)($_POST['full_feedback']??''));
        $rating=filter_var($_POST['rating']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>5]]);$order=filter_var($_POST['sort_order']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>9999]]);
        if($company===''||mb_strlen($company)>180)$errors[]='Company/client name is required and must not exceed 180 characters.';
        if(mb_strlen($person)>180||mb_strlen($designation)>180||mb_strlen($location)>180)$errors[]='Person, designation, and location must not exceed 180 characters each.';
        if(mb_strlen($short)<10||mb_strlen($short)>500)$errors[]='Short feedback must be between 10 and 500 characters.';
        if(mb_strlen($full)<10||mb_strlen($full)>5000)$errors[]='Full feedback must be between 10 and 5000 characters.';
        if($rating===false)$errors[]='Rating must be between 1 and 5.';if($order===false)$errors[]='Display order must be between 0 and 9999.';
        $old=['client_logo'=>'','client_image'=>''];
        if($id>0){$q=$pdo->prepare('SELECT client_logo,client_image FROM testimonials WHERE id=:id');$q->execute([':id'=>$id]);$found=$q->fetch();if(!is_array($found))$errors[]='Feedback record was not found.';else$old=$found;}
        $logo=testimonialUpload('client_logo');$personImage=testimonialUpload('client_image');
        if($logo['error'])$errors[]=$logo['error'];if($personImage['error'])$errors[]=$personImage['error'];
        $newFiles=[];if($logo['path'])$newFiles[]=$logo['path'];if($personImage['path'])$newFiles[]=$personImage['path'];
        if($errors===[]){
            $logoPath=$logo['present']?$logo['path']:(isset($_POST['remove_logo'])?'':$old['client_logo']);$personPath=$personImage['present']?$personImage['path']:(isset($_POST['remove_image'])?'':$old['client_image']);
            $data=[':client_logo'=>$logoPath,':client_image'=>$personPath,':company_name'=>$company,':person_name'=>$person,':designation'=>$designation,':location'=>$location,':short_feedback'=>$short,':full_feedback'=>$full,':rating'=>(int)$rating,':sort_order'=>(int)$order,':status'=>isset($_POST['status'])?1:0];
            try{if($id>0){$data[':id']=$id;$q=$pdo->prepare('UPDATE testimonials SET client_logo=:client_logo,client_image=:client_image,company_name=:company_name,person_name=:person_name,designation=:designation,location=:location,short_feedback=:short_feedback,full_feedback=:full_feedback,rating=:rating,sort_order=:sort_order,status=:status,updated_at=CURRENT_TIMESTAMP WHERE id=:id');}else$q=$pdo->prepare('INSERT INTO testimonials(client_logo,client_image,company_name,person_name,designation,location,short_feedback,full_feedback,rating,sort_order,status)VALUES(:client_logo,:client_image,:company_name,:person_name,:designation,:location,:short_feedback,:full_feedback,:rating,:sort_order,:status)');$q->execute($data);foreach(['client_logo'=>$logoPath,'client_image'=>$personPath]as$key=>$path)if($old[$key]!==''&&$old[$key]!==$path)testimonialDeleteImage($old[$key]);$_SESSION['testimonial_flash']='Client feedback saved successfully.';header('Location:testimonials.php');exit;}catch(Throwable$e){error_log($e->getMessage());foreach($newFiles as$file)testimonialDeleteImage($file);$errors[]='Feedback could not be saved.';}
        }else foreach($newFiles as$file)testimonialDeleteImage($file);
    }elseif($action==='approve'){
        $id=max(1,(int)($_POST['id']??0));$pdo->prepare('UPDATE testimonials SET status=1,updated_at=CURRENT_TIMESTAMP WHERE id=:id')->execute([':id'=>$id]);$_SESSION['testimonial_flash']='Feedback approved and published.';header('Location:testimonials.php');exit;
    }elseif($action==='delete'){
        $id=max(1,(int)($_POST['id']??0));$q=$pdo->prepare('SELECT client_logo,client_image FROM testimonials WHERE id=:id');$q->execute([':id'=>$id]);$record=$q->fetch();if(is_array($record)){$pdo->prepare('DELETE FROM testimonials WHERE id=:id')->execute([':id'=>$id]);testimonialDeleteImage((string)$record['client_logo']);testimonialDeleteImage((string)$record['client_image']);$_SESSION['testimonial_flash']='Client feedback deleted.';header('Location:testimonials.php');exit;}$errors[]='Feedback record was not found.';
    }
}

$edit=null;if(isset($_GET['edit'])){$q=$pdo->prepare('SELECT * FROM testimonials WHERE id=:id');$q->execute([':id'=>(int)$_GET['edit']]);$edit=$q->fetch();}
$records=$pdo->query('SELECT * FROM testimonials ORDER BY sort_order,id')->fetchAll();$flash=$_SESSION['testimonial_flash']??'';unset($_SESSION['testimonial_flash']);$form=$edit?:['id'=>'','client_logo'=>'','client_image'=>'','company_name'=>'','person_name'=>'','designation'=>'','location'=>'','short_feedback'=>'','full_feedback'=>'','rating'=>5,'sort_order'=>count($records)+1,'status'=>1];
$pageTitle='Client Feedback';require __DIR__.'/includes/header.php';
?>
<style>.testimonial-admin-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 20px}.testimonial-admin-list{display:grid;gap:14px;margin-top:22px}.testimonial-admin-item{display:grid;grid-template-columns:90px 1fr auto;gap:16px;padding:16px;border:1px solid var(--line);border-radius:7px;align-items:center}.testimonial-admin-logo{display:grid;width:90px;height:70px;place-items:center;overflow:hidden;border-radius:6px;background:#f2f5f3;color:var(--green);font-weight:800}.testimonial-admin-logo img{width:100%;height:100%;object-fit:contain}.testimonial-admin-actions{display:flex;gap:8px;flex-wrap:wrap}.testimonial-admin-actions a,.testimonial-admin-actions button{display:inline-flex;min-height:40px;padding:0 13px;align-items:center;border:1px solid var(--green);border-radius:4px;background:#fff;color:var(--green);font-weight:700;text-decoration:none;cursor:pointer}.testimonial-admin-actions button{border-color:#a12626;color:#a12626}.testimonial-preview{display:block;max-width:180px;max-height:110px;margin-top:10px;object-fit:contain;border:1px solid var(--line)}@media(max-width:700px){.testimonial-admin-grid,.testimonial-admin-item{grid-template-columns:1fr}.testimonial-admin-logo{width:100%;max-width:180px}}</style>
<header class="admin-header"><a class="admin-brand" href="dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?=e(currentAdminName())?></span><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><button class="logout-button">Logout</button></form></div></header>
<main class="admin-main"><div class="settings-toolbar"><a href="dashboard.php">← Dashboard</a><a href="../testimonials" target="_blank">View Full Feedback ↗</a></div><section class="panel"><h1>Client Feedback</h1><p>Add and manage testimonials shown below the About Us statistics.</p><?php if($flash):?><p class="success"><?=e($flash)?></p><?php endif;?><?php if($errors):?><div class="error"><ul><?php foreach($errors as$error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="settings-section"><h2><?=$edit?'Edit':'Add New'?> Feedback</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e((string)$form['id'])?>"><div class="testimonial-admin-grid"><div class="field"><label>Company/client name</label><input name="company_name" value="<?=e($form['company_name'])?>" maxlength="180" required></div><div class="field"><label>Location</label><input name="location" value="<?=e($form['location'])?>" maxlength="180"></div><div class="field"><label>Person name</label><input name="person_name" value="<?=e($form['person_name'])?>" maxlength="180"></div><div class="field"><label>Designation</label><input name="designation" value="<?=e($form['designation'])?>" maxlength="180"></div><div class="field"><label>Rating</label><select name="rating"><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>"<?=((int)$form['rating']===$i)?' selected':''?>><?=$i?> star<?=$i===1?'':'s'?></option><?php endfor;?></select></div><div class="field"><label>Display order</label><input name="sort_order" type="number" min="0" max="9999" value="<?=e((string)$form['sort_order'])?>"></div></div><div class="field"><label>Short feedback</label><textarea name="short_feedback" maxlength="500" required><?=e($form['short_feedback'])?></textarea><small class="help">Displayed on cards; keep it concise.</small></div><div class="field"><label>Full feedback</label><textarea name="full_feedback" maxlength="5000" required><?=e($form['full_feedback'])?></textarea></div><div class="testimonial-admin-grid"><div class="field"><label>Client logo</label><input name="client_logo" type="file" accept="image/jpeg,image/png,image/webp"><?php if($form['client_logo']):?><img class="testimonial-preview" src="../<?=e($form['client_logo'])?>" alt=""><label><input name="remove_logo" type="checkbox"> Remove logo</label><?php endif;?></div><div class="field"><label>Person image</label><input name="client_image" type="file" accept="image/jpeg,image/png,image/webp"><?php if($form['client_image']):?><img class="testimonial-preview" src="../<?=e($form['client_image'])?>" alt=""><label><input name="remove_image" type="checkbox"> Remove person image</label><?php endif;?></div></div><small class="help">JPG, PNG or WebP, maximum 5 MB each.</small><div class="checkbox-field"><input id="testimonial_status" name="status" type="checkbox" value="1"<?=((int)$form['status']===1)?' checked':''?>><label for="testimonial_status">Active</label></div><div class="form-actions"><button class="primary-button">Save Feedback</button><?php if($edit):?><a class="secondary-admin-button" href="testimonials.php">Cancel</a><?php endif;?></div></form></section>
<section class="settings-section"><h2>All Feedback</h2><div class="testimonial-admin-list"><?php foreach($records as$r):?><article class="testimonial-admin-item"><div class="testimonial-admin-logo"><?php if(testimonialImageIsSafe((string)$r['client_logo'])):?><img src="../<?=e($r['client_logo'])?>" alt=""><?php else:?><?=e(mb_strtoupper(mb_substr($r['company_name'],0,2)))?><?php endif;?></div><div><strong><?=e($r['company_name'])?></strong><p><?=e(mb_strimwidth($r['short_feedback'],0,125,'…'))?></p><small><?=$r['status']?'Active':'Inactive'?> · <?=$r['rating']?>/5 stars · Order <?=$r['sort_order']?></small></div><div class="testimonial-admin-actions"><a href="?edit=<?=$r['id']?>">Edit</a><form method="post" onsubmit="return confirm('Delete this feedback permanently?');"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button>Delete</button></form></div></article><?php endforeach;?></div></section></section></main>
<?php require __DIR__.'/includes/footer.php';?>
<script>
document.querySelectorAll('.testimonial-admin-item').forEach((item) => {
    if (!item.textContent.includes('Inactive')) return;
    const edit = item.querySelector('a[href*="?edit="]');
    const token = document.querySelector('input[name="csrf_token"]');
    if (!edit || !token) return;
    const id = new URL(edit.href).searchParams.get('edit');
    if (!id) return;
    const form = document.createElement('form'); form.method = 'post';
    form.innerHTML = `<input type="hidden" name="csrf_token" value="${token.value}"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="${id}"><button type="submit">Approve</button>`;
    item.querySelector('.testimonial-admin-actions').prepend(form);
});
</script>

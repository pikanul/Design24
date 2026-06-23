<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/site-settings.php';
$headerSettings = getHeaderSettings();

function testimonialArchiveImage(string $path): bool
{
    return preg_match('#^public/uploads/testimonials/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $path) === 1 && is_file(__DIR__ . '/' . $path);
}

$statement = db()->prepare('SELECT * FROM testimonials WHERE status=1 ORDER BY sort_order,id');
$statement->execute();
$testimonials = $statement->fetchAll();
$selected = null;
if (isset($_GET['id'])) {
    $find = db()->prepare('SELECT * FROM testimonials WHERE id=:id AND status=1 LIMIT 1');
    $find->execute([':id' => max(1, (int) $_GET['id'])]);
    $selected = $find->fetch() ?: null;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><meta name="description" content="Read client feedback and testimonials for Design24 Studio."><title>Client Feedback | Design24 Studio</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="testimonial-page-body<?=settingEnabled($headerSettings,'show_top_bar')?' has-top-bar':' no-top-bar'?>"><?php require __DIR__.'/includes/site-header.php';?>
<main class="testimonial-archive"><section class="testimonial-archive-hero"><div class="testimonial-container"><p>CLIENT FEEDBACK</p><h1>What Our Clients Say About Us</h1><span></span><p>Real experiences from clients who trusted Design24 Studio with their spaces.</p></div></section>
<?php if(is_array($selected)):?><section class="testimonial-featured-detail"><div class="testimonial-container"><a class="testimonial-back-link" href="testimonials">← All feedback</a><article><div class="testimonial-detail-brand"><div class="testimonial-logo"><?php if(testimonialArchiveImage((string)$selected['client_logo'])):?><img src="<?=siteEscape($selected['client_logo'])?>" alt="<?=siteEscape($selected['company_name'])?> logo"><?php else:?><span><?=siteEscape(mb_strtoupper(mb_substr($selected['company_name'],0,2)))?></span><?php endif;?></div><div><div class="testimonial-stars"><?php for($i=1;$i<=5;$i++):?><span class="<?=$i<=(int)$selected['rating']?'filled':''?>">★</span><?php endfor;?></div><h2><?=siteEscape($selected['company_name'])?></h2><?php if($selected['location']):?><p><?=siteEscape($selected['location'])?></p><?php endif;?></div></div><blockquote><?=nl2br(siteEscape($selected['full_feedback']))?></blockquote><?php if($selected['person_name']):?><div class="testimonial-detail-person"><?php if(testimonialArchiveImage((string)$selected['client_image'])):?><img src="<?=siteEscape($selected['client_image'])?>" alt="<?=siteEscape($selected['person_name'])?>"><?php endif;?><div><strong><?=siteEscape($selected['person_name'])?></strong><?php if($selected['designation']):?><span><?=siteEscape($selected['designation'])?></span><?php endif;?></div></div><?php endif;?></article></div></section><?php endif;?>
<section class="testimonial-archive-list"><div class="testimonial-container"><div class="testimonial-archive-grid"><?php foreach($testimonials as$item):?><article class="testimonial-full-card"><div class="testimonial-logo"><?php if(testimonialArchiveImage((string)$item['client_logo'])):?><img src="<?=siteEscape($item['client_logo'])?>" alt="<?=siteEscape($item['company_name'])?> logo" loading="lazy"><?php else:?><span><?=siteEscape(mb_strtoupper(mb_substr($item['company_name'],0,2)))?></span><?php endif;?></div><div class="testimonial-stars"><?php for($i=1;$i<=5;$i++):?><span class="<?=$i<=(int)$item['rating']?'filled':''?>">★</span><?php endfor;?></div><h2><?=siteEscape($item['company_name'])?></h2><?php if($item['location']):?><small><?=siteEscape($item['location'])?></small><?php endif;?><p><?=siteEscape($item['full_feedback'])?></p><?php if($item['person_name']):?><div class="testimonial-detail-person"><?php if(testimonialArchiveImage((string)$item['client_image'])):?><img src="<?=siteEscape($item['client_image'])?>" alt="<?=siteEscape($item['person_name'])?>" loading="lazy"><?php endif;?><div><strong><?=siteEscape($item['person_name'])?></strong><?php if($item['designation']):?><span><?=siteEscape($item['designation'])?></span><?php endif;?></div></div><?php endif;?><a href="testimonials?id=<?=$item['id']?>">View Details</a></article><?php endforeach;?><?php if($testimonials===[]):?><p>No client feedback is available yet.</p><?php endif;?></div></div></section></main>
<?php require __DIR__.'/includes/site-footer.php';?><script src="assets/js/script.js"></script></body></html>

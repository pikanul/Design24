<?php

declare(strict_types=1);

function testimonialPublicImage(string $path): bool
{
    return preg_match('#^public/uploads/testimonials/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $path) === 1
        && is_file(dirname(__DIR__) . '/' . $path);
}

try {
    $testimonialStatement = db()->prepare('SELECT * FROM testimonials WHERE status=1 ORDER BY sort_order,id');
    $testimonialStatement->execute();
    $publicTestimonials = $testimonialStatement->fetchAll();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $publicTestimonials = [];
}

if ($publicTestimonials !== []):
?>
<section class="testimonial-section testimonial-about-section" aria-labelledby="testimonial-heading">
    <div class="testimonial-container">
        <header class="testimonial-section-header"><p>CLIENT FEEDBACK</p><h2 id="testimonial-heading">What Our Clients Say About Us</h2></header>
        <div class="testimonial-carousel" data-testimonial-slider>
            <div class="testimonial-viewport"><div class="testimonial-track">
                <?php foreach($publicTestimonials as$testimonial): ?><article class="testimonial-card" data-testimonial-card>
                    <div class="testimonial-card-top"><div class="testimonial-logo"><?php if(testimonialPublicImage((string)$testimonial['client_logo'])):?><img src="<?=siteEscape($testimonial['client_logo'])?>" alt="<?=siteEscape($testimonial['company_name'])?> logo" loading="lazy"><?php else:?><span><?=siteEscape(mb_strtoupper(mb_substr($testimonial['company_name'],0,2)))?></span><?php endif;?></div><div class="testimonial-card-copy"><div class="testimonial-stars" aria-label="<?= (int)$testimonial['rating'] ?> out of 5 stars"><?php for($i=1;$i<=5;$i++):?><span class="<?=$i<=(int)$testimonial['rating']?'filled':''?>">★</span><?php endfor;?></div><p class="testimonial-excerpt"><?=siteEscape($testimonial['short_feedback'])?></p></div></div>
                    <div class="testimonial-card-footer"><div><strong><?=siteEscape($testimonial['company_name'])?></strong><?php if($testimonial['location']):?><small><?=siteEscape($testimonial['location'])?></small><?php endif;?></div><?php if(testimonialPublicImage((string)$testimonial['client_image'])):?><img class="testimonial-person-photo" src="<?=siteEscape($testimonial['client_image'])?>" alt="<?=siteEscape($testimonial['person_name']?:$testimonial['company_name'])?>" loading="lazy"><?php endif;?></div>
                    <?php if($testimonial['person_name']):?><p class="testimonial-person"><strong><?=siteEscape($testimonial['person_name'])?></strong><?php if($testimonial['designation']):?><span><?=siteEscape($testimonial['designation'])?></span><?php endif;?></p><?php endif;?><a class="testimonial-read-more" href="testimonials?id=<?=(int)$testimonial['id']?>">View Details</a>
                </article><?php endforeach; ?>
            </div></div>
            <?php if(count($publicTestimonials)>1):?><button class="testimonial-arrow testimonial-prev" type="button" aria-label="Previous testimonials">←</button><button class="testimonial-arrow testimonial-next" type="button" aria-label="Next testimonials">→</button><div class="testimonial-dots" role="group" aria-label="Choose testimonial"><?php foreach($publicTestimonials as$i=>$testimonial):?><button class="testimonial-dot<?=$i===0?' active':''?>" type="button" data-testimonial-index="<?=$i?>" aria-label="Show testimonial <?=($i+1)?>" aria-current="<?=$i===0?'true':'false'?>"></button><?php endforeach;?></div><?php endif;?>
        </div>
        <div class="testimonial-all-action"><a href="testimonials">View Full Feedback</a></div>
    </div>
</section>
<?php endif; ?>

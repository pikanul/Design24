// Find the mobile menu elements on the page.
const menuButton = document.querySelector('.menu-toggle');
const navigation = document.querySelector('.header-navigation');
const menuLinks = document.querySelectorAll('.main-menu a');
const siteHeader = document.querySelector('.site-header');
const portfolioItem = document.querySelector('.menu-item-has-children');
const portfolioButton = document.querySelector('.portfolio-toggle');

// Keep the mobile menu directly below the header, even when the header shrinks.
function updateMobileMenuPosition() {
    if (!siteHeader || !navigation) return;
    navigation.style.setProperty('--mobile-menu-top', `${siteHeader.getBoundingClientRect().bottom}px`);
}

// Open or close the menu when the hamburger button is clicked.
function toggleMenu() {
    if (!menuButton || !navigation) return;
    const isOpen = navigation.classList.toggle('open');

    menuButton.classList.toggle('active', isOpen);
    menuButton.setAttribute('aria-expanded', isOpen);
    menuButton.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
    document.body.classList.toggle('menu-open', isOpen);
}

if (menuButton) menuButton.addEventListener('click', toggleMenu);

// Portfolio opens by hover/focus on desktop and by tap on mobile.
if (portfolioButton && portfolioItem) {
    portfolioButton.addEventListener('click', () => {
        const isOpen = portfolioItem.classList.toggle('submenu-open');
        portfolioButton.setAttribute('aria-expanded', String(isOpen));
    });
}

// Close the mobile menu after any menu link is selected.
menuLinks.forEach((link) => {
    link.addEventListener('click', () => {
        if (navigation.classList.contains('open')) {
            toggleMenu();
        }
        if (portfolioItem && portfolioButton) {
            portfolioItem.classList.remove('submenu-open');
            portfolioButton.setAttribute('aria-expanded', 'false');
        }
    });
});

// Close the mobile menu if the screen is resized to desktop width.
window.addEventListener('resize', () => {
    updateMobileMenuPosition();
    if (navigation && window.innerWidth > 820 && navigation.classList.contains('open')) {
        toggleMenu();
    }
});

// Add the compact/shadow state only after the visitor starts scrolling.
function updateScrolledHeader() {
    if (!siteHeader) return;
    siteHeader.classList.toggle('is-scrolled', window.scrollY > 24);
    updateMobileMenuPosition();
}

window.addEventListener('scroll', updateScrolledHeader, { passive: true });
updateScrolledHeader();

// Show the footer back-to-top control only after the visitor scrolls down.
const backToTopButton = document.querySelector('.back-to-top');

// index.php#about acts as a focused About view without changing the normal homepage.
function syncAboutAnchorView() {
    document.body.classList.toggle('about-anchor-view', window.location.hash.toLowerCase() === '#about');
}

syncAboutAnchorView();
window.addEventListener('hashchange', syncAboutAnchorView);

function updateBackToTopButton() {
    if (!backToTopButton) return;
    backToTopButton.classList.toggle('visible', window.scrollY > 500);
}

if (backToTopButton) {
    backToTopButton.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    window.addEventListener('scroll', updateBackToTopButton, { passive: true });
    updateBackToTopButton();
}

// Optional low-volume background audio. Playback failures stay silent.
const siteAudioControl = document.querySelector('[data-site-audio]');

if (siteAudioControl) {
    const siteAudio = siteAudioControl.querySelector('#siteBgAudio');
    const audioToggleButton = siteAudioControl.querySelector('#audioToggleBtn');
    const audioIcon = siteAudioControl.querySelector('#audioIcon');
    const audioText = siteAudioControl.querySelector('#audioText');
    const configuredVolume = Number(siteAudioControl.dataset.volume);
    const safeVolume = Number.isFinite(configuredVolume) ? Math.min(0.3, Math.max(0, configuredVolume)) : 0.15;

    function readSoundPreference() {
        try { return window.localStorage.getItem('soundStatus'); } catch (error) { return null; }
    }

    function saveSoundPreference(value) {
        try { window.localStorage.setItem('soundStatus', value); } catch (error) { /* Storage may be unavailable. */ }
    }

    function updateAudioUI(isPlaying) {
        if (!audioToggleButton) return;
        if (audioIcon) audioIcon.textContent = isPlaying ? '🔊' : '🔇';
        if (audioText) audioText.textContent = isPlaying ? 'Sound On' : 'Sound Off';
        const label = isPlaying ? 'Turn background music off' : 'Turn background music on';
        audioToggleButton.setAttribute('aria-label', label);
        audioToggleButton.setAttribute('title', label);
        audioToggleButton.setAttribute('aria-pressed', String(isPlaying));
    }

    async function playSiteAudio() {
        if (!siteAudio) return false;
        siteAudio.volume = safeVolume;
        try {
            await siteAudio.play();
            saveSoundPreference('on');
            updateAudioUI(true);
            return true;
        } catch (error) {
            siteAudio.pause();
            saveSoundPreference('off');
            updateAudioUI(false);
            return false;
        }
    }

    function pauseSiteAudio() {
        if (siteAudio) siteAudio.pause();
        saveSoundPreference('off');
        updateAudioUI(false);
    }

    if (siteAudio) {
        siteAudio.loop = true;
        siteAudio.volume = safeVolume;
        updateAudioUI(false);
        const savedSoundStatus = readSoundPreference();
        if (savedSoundStatus === 'on' || (savedSoundStatus === null && siteAudioControl.dataset.autoplayAttempt === '1')) {
            playSiteAudio();
        }
        siteAudio.addEventListener('error', () => pauseSiteAudio());
    }

    if (audioToggleButton) {
        audioToggleButton.addEventListener('click', () => {
            if (!siteAudio || siteAudio.paused) playSiteAudio();
            else pauseSiteAudio();
        });
    }
}

// Homepage mixed image/video hero slider.
const heroSlider = document.querySelector('[data-hero-slider]');

if (heroSlider) {
    const slides = Array.from(heroSlider.querySelectorAll('.hero-slide'));
    const previousButton = heroSlider.querySelector('.hero-slider-prev');
    const nextButton = heroSlider.querySelector('.hero-slider-next');
    const dots = Array.from(heroSlider.querySelectorAll('.hero-slider-dot'));
    const listeners = new AbortController();
    const listenerOptions = { signal: listeners.signal };
    const pauseReasons = new Set();
    let activeIndex = 0;
    let timer = null;
    let touchStartX = 0;

    function clearHeroTimer() {
        if (timer !== null) {
            window.clearTimeout(timer);
            timer = null;
        }
    }

    function currentVideo() {
        return slides[activeIndex] ? slides[activeIndex].querySelector('video') : null;
    }

    function scheduleImageAdvance() {
        clearHeroTimer();
        if (slides.length > 1 && pauseReasons.size === 0) {
            timer = window.setTimeout(() => showHeroSlide(activeIndex + 1), 6000);
        }
    }

    function startActiveMedia(restartVideo = false) {
        if (slides.length === 0 || pauseReasons.size > 0) return;
        const video = currentVideo();
        if (!video) {
            scheduleImageAdvance();
            return;
        }

        // Video slides deliberately have no slider timer: they advance on `ended`.
        clearHeroTimer();
        video.muted = true;
        video.loop = false;
        video.autoplay = true;
        video.playsInline = true;
        if (restartVideo) {
            try { video.currentTime = 0; } catch (_) { /* Metadata may still be loading. */ }
        }
        const playback = video.play();
        if (playback && typeof playback.catch === 'function') {
            playback.catch(() => {
                // Muted inline playback is expected to work; keep the slider usable if a browser blocks it.
                if (slides.length > 1 && pauseReasons.size === 0) {
                    timer = window.setTimeout(() => showHeroSlide(activeIndex + 1), 6000);
                }
            });
        }
    }

    function stopVideo(video) {
        if (!video) return;
        video.pause();
        try { video.currentTime = 0; } catch (_) { /* Safe before metadata is available. */ }
    }

    function showHeroSlide(index) {
        if (slides.length === 0) return;
        clearHeroTimer();
        const oldVideo = currentVideo();
        if (oldVideo) stopVideo(oldVideo);

        activeIndex = (index + slides.length) % slides.length;
        const activeRatio = slides[activeIndex].dataset.aspectRatio;
        if (activeRatio) heroSlider.style.setProperty('--hero-aspect-ratio', activeRatio);
        slides.forEach((slide, slideIndex) => {
            const isActive = slideIndex === activeIndex;
            slide.classList.toggle('active', isActive);
            slide.setAttribute('aria-hidden', String(!isActive));
        });
        dots.forEach((dot, dotIndex) => {
            const isActive = dotIndex === activeIndex;
            dot.classList.toggle('active', isActive);
            dot.setAttribute('aria-current', String(isActive));
        });
        startActiveMedia(true);
    }

    function pauseHero(reason) {
        pauseReasons.add(reason);
        clearHeroTimer();
        const video = currentVideo();
        if (video) video.pause();
    }

    function resumeHero(reason) {
        pauseReasons.delete(reason);
        if (pauseReasons.size === 0) startActiveMedia(false);
    }

    slides.forEach((slide, index) => {
        const video = slide.querySelector('video');
        if (video) {
            video.muted = true;
            video.loop = false;
            video.autoplay = true;
            video.playsInline = true;
            video.addEventListener('loadedmetadata', () => {
                if (video.videoWidth > 0 && video.videoHeight > 0) {
                    slide.dataset.aspectRatio = `${video.videoWidth} / ${video.videoHeight}`;
                    if (index === activeIndex) {
                        heroSlider.style.setProperty('--hero-aspect-ratio', slide.dataset.aspectRatio);
                    }
                }
            }, listenerOptions);
            video.addEventListener('ended', () => {
                if (index === activeIndex && slides.length > 1 && pauseReasons.size === 0) {
                    showHeroSlide(activeIndex + 1);
                }
            }, listenerOptions);
        }
    });

    if (slides.length > 1) {
        previousButton.addEventListener('click', () => showHeroSlide(activeIndex - 1), listenerOptions);
        nextButton.addEventListener('click', () => showHeroSlide(activeIndex + 1), listenerOptions);
        dots.forEach((dot) => dot.addEventListener('click', () => showHeroSlide(Number(dot.dataset.slideIndex)), listenerOptions));
        heroSlider.addEventListener('touchstart', (event) => { touchStartX = event.changedTouches[0].clientX; }, { passive: true, signal: listeners.signal });
        heroSlider.addEventListener('touchend', (event) => {
            const distance = event.changedTouches[0].clientX - touchStartX;
            if (Math.abs(distance) >= 50) showHeroSlide(activeIndex + (distance < 0 ? 1 : -1));
        }, { passive: true, signal: listeners.signal });
    }

    // Home navigation always returns to and restarts the first hero video.
    document.querySelectorAll('a[href="#home"], a[href="index.php#home"]').forEach((homeLink) => {
        homeLink.addEventListener('click', () => showHeroSlide(0), listenerOptions);
    });
    window.addEventListener('hashchange', () => { if (window.location.hash === '#home') showHeroSlide(0); }, listenerOptions);

    document.addEventListener('visibilitychange', () => { if (document.hidden) pauseHero('hidden'); else resumeHero('hidden'); }, listenerOptions);
    window.addEventListener('pagehide', () => { clearHeroTimer(); listeners.abort(); }, { once: true });
    startActiveMedia(false);
}

// About Us image carousel: lightweight, automatic, and keyboard friendly.
const aboutSlider = document.querySelector('[data-about-slider]');

if (aboutSlider) {
    const slides = Array.from(aboutSlider.querySelectorAll('.about-carousel-slide'));
    const dots = Array.from(aboutSlider.querySelectorAll('.about-carousel-dot'));
    const previousButton = aboutSlider.querySelector('.about-carousel-prev');
    const nextButton = aboutSlider.querySelector('.about-carousel-next');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let activeIndex = 0;
    let timer = null;
    let paused = false;
    let touchStartX = 0;

    function clearAboutTimer() {
        if (timer !== null) window.clearTimeout(timer);
        timer = null;
    }

    function scheduleAboutSlide() {
        clearAboutTimer();
        if (slides.length > 1 && !paused && !document.hidden && !reducedMotion.matches) {
            timer = window.setTimeout(() => showAboutSlide(activeIndex + 1), 5000);
        }
    }

    function showAboutSlide(index) {
        if (slides.length === 0) return;
        activeIndex = (index + slides.length) % slides.length;
        slides.forEach((slide, slideIndex) => {
            const active = slideIndex === activeIndex;
            slide.classList.toggle('active', active);
            slide.setAttribute('aria-hidden', String(!active));
        });
        dots.forEach((dot, dotIndex) => {
            const active = dotIndex === activeIndex;
            dot.classList.toggle('active', active);
            dot.setAttribute('aria-current', String(active));
        });
        scheduleAboutSlide();
    }

    if (slides.length > 1) {
        previousButton.addEventListener('click', () => showAboutSlide(activeIndex - 1));
        nextButton.addEventListener('click', () => showAboutSlide(activeIndex + 1));
        dots.forEach((dot) => dot.addEventListener('click', () => showAboutSlide(Number(dot.dataset.aboutIndex))));
        aboutSlider.addEventListener('mouseenter', () => { paused = true; clearAboutTimer(); });
        aboutSlider.addEventListener('mouseleave', () => { paused = false; scheduleAboutSlide(); });
        aboutSlider.addEventListener('focusin', () => { paused = true; clearAboutTimer(); });
        aboutSlider.addEventListener('focusout', (event) => {
            if (!aboutSlider.contains(event.relatedTarget)) { paused = false; scheduleAboutSlide(); }
        });
        aboutSlider.addEventListener('touchstart', (event) => { touchStartX = event.changedTouches[0].clientX; }, { passive: true });
        aboutSlider.addEventListener('touchend', (event) => {
            const distance = event.changedTouches[0].clientX - touchStartX;
            if (Math.abs(distance) >= 50) showAboutSlide(activeIndex + (distance < 0 ? 1 : -1));
        }, { passive: true });
        document.addEventListener('visibilitychange', scheduleAboutSlide);
        scheduleAboutSlide();
    }
}

// Infinite Client Feedback carousel. Autoplay moves visually from left to right.
const testimonialSlider = document.querySelector('[data-testimonial-slider]');

if (testimonialSlider) {
    const track = testimonialSlider.querySelector('.testimonial-track');
    const originalCards = Array.from(track.querySelectorAll('[data-testimonial-card]'));
    const previousButton = testimonialSlider.querySelector('.testimonial-prev');
    const nextButton = testimonialSlider.querySelector('.testimonial-next');
    const dots = Array.from(testimonialSlider.querySelectorAll('.testimonial-dot'));
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let cloneCount = 0;
    let currentIndex = 0;
    let timer = null;
    let paused = false;
    let resizeTimer = null;

    function testimonialVisibleCount() {
        if (window.innerWidth <= 700) return 1;
        if (window.innerWidth <= 1120) return 2;
        return 4;
    }

    function testimonialStep() {
        if (!originalCards[0]) return 0;
        const gap = parseFloat(window.getComputedStyle(track).columnGap) || 0;
        return originalCards[0].getBoundingClientRect().width + gap;
    }

    function testimonialOriginalIndex() {
        return ((currentIndex - cloneCount) % originalCards.length + originalCards.length) % originalCards.length;
    }

    function updateTestimonialDots() {
        const activeIndex = testimonialOriginalIndex();
        dots.forEach((dot, index) => {
            const active = index === activeIndex;
            dot.classList.toggle('active', active);
            dot.setAttribute('aria-current', String(active));
        });
    }

    function positionTestimonials(animate = true) {
        track.style.transition = animate ? '' : 'none';
        track.style.transform = `translate3d(${-currentIndex * testimonialStep()}px,0,0)`;
        updateTestimonialDots();
        if (!animate) window.requestAnimationFrame(() => { track.style.transition = ''; });
    }

    function clearTestimonialTimer() {
        if (timer !== null) window.clearTimeout(timer);
        timer = null;
    }

    function scheduleTestimonials() {
        clearTestimonialTimer();
        if (originalCards.length > 1 && !paused && !document.hidden && !reducedMotion.matches) {
            timer = window.setTimeout(() => moveTestimonials(-1), 4500);
        }
    }

    function moveTestimonials(direction) {
        currentIndex += direction;
        positionTestimonials(true);
        scheduleTestimonials();
    }

    function buildTestimonialLoop() {
        track.querySelectorAll('[data-testimonial-clone]').forEach((clone) => clone.remove());
        cloneCount = Math.min(testimonialVisibleCount(), originalCards.length);
        const beginning = document.createDocumentFragment();
        originalCards.slice(-cloneCount).forEach((card) => {
            const clone = card.cloneNode(true);
            clone.dataset.testimonialClone = '1';
            clone.setAttribute('aria-hidden', 'true');
            clone.querySelectorAll('a,button').forEach((control) => control.setAttribute('tabindex', '-1'));
            beginning.appendChild(clone);
        });
        track.insertBefore(beginning, track.firstChild);
        originalCards.slice(0, cloneCount).forEach((card) => {
            const clone = card.cloneNode(true);
            clone.dataset.testimonialClone = '1';
            clone.setAttribute('aria-hidden', 'true');
            clone.querySelectorAll('a,button').forEach((control) => control.setAttribute('tabindex', '-1'));
            track.appendChild(clone);
        });
        currentIndex = cloneCount;
        positionTestimonials(false);
    }

    if (originalCards.length > 1) {
        buildTestimonialLoop();
        track.addEventListener('transitionend', () => {
            if (currentIndex < cloneCount) {
                currentIndex += originalCards.length;
                positionTestimonials(false);
            } else if (currentIndex >= cloneCount + originalCards.length) {
                currentIndex -= originalCards.length;
                positionTestimonials(false);
            }
        });
        previousButton.addEventListener('click', () => moveTestimonials(-1));
        nextButton.addEventListener('click', () => moveTestimonials(1));
        dots.forEach((dot) => dot.addEventListener('click', () => {
            currentIndex = cloneCount + Number(dot.dataset.testimonialIndex);
            positionTestimonials(true);
            scheduleTestimonials();
        }));
        testimonialSlider.addEventListener('mouseenter', () => { paused = true; clearTestimonialTimer(); });
        testimonialSlider.addEventListener('mouseleave', () => { paused = false; scheduleTestimonials(); });
        testimonialSlider.addEventListener('focusin', () => { paused = true; clearTestimonialTimer(); });
        testimonialSlider.addEventListener('focusout', (event) => {
            if (!testimonialSlider.contains(event.relatedTarget)) { paused = false; scheduleTestimonials(); }
        });
        window.addEventListener('resize', () => {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(buildTestimonialLoop, 180);
        });
        document.addEventListener('visibilitychange', scheduleTestimonials);
        scheduleTestimonials();
    }
}

// Reusable one-time animated counters, triggered when scrolled into view.
const counterNumbers = document.querySelectorAll('.counter-number');

if (counterNumbers.length > 0) {
    const animateCounter = (counter) => {
        if (counter.dataset.animated === 'true') return;
        counter.dataset.animated = 'true';
        const target = Math.max(0, Number(counter.dataset.target) || 0);
        const duration = Math.max(1, Number(counter.dataset.duration) || 3000);
        const suffix = counter.dataset.suffix || '';
        const startTime = performance.now();

        const update = (now) => {
            const progress = Math.min((now - startTime) / duration, 1);
            const easedProgress = 1 - Math.pow(1 - progress, 3);
            counter.textContent = Math.round(target * easedProgress).toLocaleString() + (progress === 1 ? suffix : '');
            if (progress < 1) window.requestAnimationFrame(update);
        };
        window.requestAnimationFrame(update);
    };

    if ('IntersectionObserver' in window) {
        const counterSection = document.querySelector('.about-counter-section');
        const counterObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    counterNumbers.forEach(animateCounter);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.2 });
        if (counterSection) counterObserver.observe(counterSection);
        else counterNumbers.forEach(animateCounter);
    } else {
        counterNumbers.forEach(animateCounter);
    }
}

// Public Team Page filters and accessible member profile modal.
const teamFilters = Array.from(document.querySelectorAll('.team-filter'));
const teamGroups = Array.from(document.querySelectorAll('.team-group-section'));

teamFilters.forEach((filterButton) => {
    filterButton.addEventListener('click', () => {
        const selectedGroup = filterButton.dataset.teamFilter;
        teamFilters.forEach((button) => {
            const isActive = button === filterButton;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', String(isActive));
        });
        teamGroups.forEach((group) => {
            group.hidden = selectedGroup !== 'all' && group.dataset.teamGroup !== selectedGroup;
        });
    });
});

const teamModal = document.querySelector('#team-profile-modal');
const teamModalContent = document.querySelector('#team-modal-content');
const teamProfileTriggers = Array.from(document.querySelectorAll('.team-profile-trigger'));
let lastTeamModalTrigger = null;

function closeTeamModal() {
    if (!teamModal) return;
    teamModal.hidden = true;
    document.body.classList.remove('team-modal-open');
    teamModalContent.innerHTML = '';
    if (lastTeamModalTrigger) lastTeamModalTrigger.focus();
}

function openTeamModal(trigger) {
    if (!teamModal) return;
    const template = document.querySelector(`#team-profile-${trigger.dataset.memberId}`);
    if (!template) return;
    lastTeamModalTrigger = trigger;
    teamModalContent.innerHTML = '';
    teamModalContent.appendChild(template.content.cloneNode(true));
    teamModal.hidden = false;
    document.body.classList.add('team-modal-open');
    teamModal.querySelector('.team-modal-close').focus();
}

teamProfileTriggers.forEach((trigger) => trigger.addEventListener('click', () => openTeamModal(trigger)));
document.querySelectorAll('[data-team-modal-close]').forEach((button) => button.addEventListener('click', closeTeamModal));

if (teamModal) {
    teamModal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeTeamModal();
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = Array.from(teamModal.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), [tabindex]:not([tabindex="-1"])'));
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
}

// Portfolio filters, live search, sorting, and progressive loading.
const portfolioGrid = document.querySelector('[data-portfolio-grid]');
if (portfolioGrid) {
    const cards = Array.from(portfolioGrid.querySelectorAll('.portfolio-card'));
    const filterBar = document.querySelector('[data-portfolio-filter]');
    const searchInput = document.querySelector('[data-portfolio-search]');
    const sortInput = document.querySelector('[data-portfolio-sort]');
    const loadMoreButton = document.querySelector('[data-portfolio-load-more]');
    const emptyMessage = document.querySelector('[data-portfolio-empty]');
    let activeCategory = portfolioGrid.dataset.initialFilter || 'all';
    let activeStatus = '';
    let activeType = '';
    let shownCards = 6;

    if (activeCategory !== 'all' && filterBar) {
        filterBar.querySelectorAll('button').forEach((button) => {
            button.classList.toggle('active', button.dataset.filter === activeCategory);
        });
    }

    const applyPortfolioFilters = () => {
        const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
        let visibleCount = 0;
        cards.forEach((card) => {
            const categories = (card.dataset.category || '').split(/\s+/);
            const status = card.dataset.status || '';
            const type = card.dataset.type || 'image';
            const search = card.dataset.search || '';
            const categoryMatches = activeCategory === 'all' || categories.includes(activeCategory);
            const statusMatches = activeStatus === '' || status === activeStatus;
            const typeMatches = activeType === '' || type === activeType;
            const searchMatches = query === '' || search.includes(query);
            const visible = categoryMatches && statusMatches && typeMatches && searchMatches;
            if (visible) visibleCount += 1;
            card.hidden = !visible || visibleCount > shownCards;
        });
        if (emptyMessage) emptyMessage.hidden = visibleCount !== 0;
        if (loadMoreButton) loadMoreButton.hidden = visibleCount <= shownCards;
    };

    const sortPortfolioCards = () => {
        if (!sortInput) return;
        const selected = sortInput.value;
        const ordered = [...cards].sort((a, b) => {
            if (selected === 'completed' || selected === 'ongoing') {
                const preferred = selected === 'completed' ? 'Completed' : 'Ongoing';
                return (b.dataset.status === preferred) - (a.dataset.status === preferred);
            }
            const orderA = Number(a.dataset.order || 0), orderB = Number(b.dataset.order || 0);
            return selected === 'oldest' ? orderA - orderB : orderB - orderA;
        });
        ordered.forEach((card) => portfolioGrid.appendChild(card));
    };

    if (filterBar) {
        filterBar.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            filterBar.querySelectorAll('button').forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
            activeCategory = button.dataset.filter || 'all';
            activeStatus = button.dataset.status || '';
            activeType = button.dataset.type || '';
            if (button.dataset.status) activeCategory = 'all';
            if (button.dataset.type) activeCategory = 'all';
            shownCards = 6;
            applyPortfolioFilters();
        });
    }

    if (searchInput) searchInput.addEventListener('input', applyPortfolioFilters);
    if (sortInput) sortInput.addEventListener('change', () => { sortPortfolioCards(); applyPortfolioFilters(); });
    if (loadMoreButton) loadMoreButton.addEventListener('click', () => { shownCards += 6; applyPortfolioFilters(); });
    sortPortfolioCards();
    applyPortfolioFilters();
}

const portfolioVideoModal = document.querySelector('[data-portfolio-video-modal]');
if (portfolioVideoModal) {
    const frame = portfolioVideoModal.querySelector('iframe');
    const closeVideo = () => { portfolioVideoModal.hidden = true; frame.src = ''; };
    document.querySelectorAll('.portfolio-video-trigger').forEach((trigger) => trigger.addEventListener('click', () => {
        const url = trigger.dataset.videoUrl || '';
        const id = url.match(/(?:youtu\.be\/|[?&]v=|\/embed\/)([A-Za-z0-9_-]{6,})/)?.[1];
        if (!id) return;
        frame.src = `https://www.youtube-nocookie.com/embed/${id}?autoplay=1`;
        portfolioVideoModal.hidden = false;
        portfolioVideoModal.querySelector('button').focus();
    }));
    portfolioVideoModal.addEventListener('click', (event) => { if (event.target === portfolioVideoModal || event.target.closest('[data-close-portfolio-video]')) closeVideo(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !portfolioVideoModal.hidden) closeVideo(); });
}

const portfolioLightbox = document.querySelector('[data-portfolio-lightbox]');
const portfolioGallery = document.querySelector('[data-lightbox-gallery]');
if (portfolioLightbox && portfolioGallery) {
    const lightboxImage = portfolioLightbox.querySelector('img');
    const lightboxCaption = portfolioLightbox.querySelector('p');
    const closeButton = portfolioLightbox.querySelector('button');
    const closeLightbox = () => {
        portfolioLightbox.hidden = true;
        lightboxImage.src = '';
        lightboxCaption.textContent = '';
    };
    portfolioGallery.addEventListener('click', (event) => {
        const trigger = event.target.closest('button[data-full]');
        if (!trigger) return;
        lightboxImage.src = trigger.dataset.full;
        lightboxCaption.textContent = trigger.dataset.caption || '';
        portfolioLightbox.hidden = false;
        closeButton.focus();
    });
    closeButton.addEventListener('click', closeLightbox);
    portfolioLightbox.addEventListener('click', (event) => {
        if (event.target === portfolioLightbox) closeLightbox();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !portfolioLightbox.hidden) closeLightbox();
    });
}

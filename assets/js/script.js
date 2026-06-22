const menuButton = document.querySelector('.menu-toggle');
const menu = document.querySelector('#main-menu');
const menuLinks = document.querySelectorAll('.main-nav a');
const contactForm = document.querySelector('#contact-form');
const formStatus = document.querySelector('#form-status');

// Open or close the navigation on small screens.
function toggleMenu() {
    const isOpen = menu.classList.toggle('open');

    menuButton.classList.toggle('active', isOpen);
    menuButton.setAttribute('aria-expanded', isOpen);
    menuButton.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
    document.body.classList.toggle('menu-open', isOpen);
}

menuButton.addEventListener('click', toggleMenu);

// Close the mobile menu after a navigation link is selected.
menuLinks.forEach((link) => {
    link.addEventListener('click', () => {
        if (menu.classList.contains('open')) {
            toggleMenu();
        }
    });
});

// Update the highlighted navigation link as the page is scrolled.
const sections = document.querySelectorAll('header[id], section[id]');

const sectionObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            menuLinks.forEach((link) => {
                link.classList.toggle('active', link.getAttribute('href') === `#${entry.target.id}`);
            });
        }
    });
}, { rootMargin: '-35% 0px -60% 0px' });

sections.forEach((section) => sectionObserver.observe(section));

// This is a front-end demo. It confirms the form without sending data anywhere.
contactForm.addEventListener('submit', (event) => {
    event.preventDefault();

    const name = document.querySelector('#name').value.trim();
    formStatus.textContent = `Thanks, ${name}! Your message is ready. Form delivery can be added later.`;
    contactForm.reset();
});

// Find the mobile menu elements on the page.
const menuButton = document.querySelector('.menu-toggle');
const navigation = document.querySelector('.header-navigation');
const menuLinks = document.querySelectorAll('.main-menu a');

// Open or close the menu when the hamburger button is clicked.
function toggleMenu() {
    const isOpen = navigation.classList.toggle('open');

    menuButton.classList.toggle('active', isOpen);
    menuButton.setAttribute('aria-expanded', isOpen);
    menuButton.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
    document.body.classList.toggle('menu-open', isOpen);
}

menuButton.addEventListener('click', toggleMenu);

// Close the mobile menu after any menu link is selected.
menuLinks.forEach((link) => {
    link.addEventListener('click', () => {
        if (navigation.classList.contains('open')) {
            toggleMenu();
        }
    });
});

// Close the mobile menu if the screen is resized to desktop width.
window.addEventListener('resize', () => {
    if (window.innerWidth > 780 && navigation.classList.contains('open')) {
        toggleMenu();
    }
});

<?php
$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Design24 creates thoughtful digital experiences for modern brands.">
    <title>Design24 | Creative Digital Studio</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%237756ff'/%3E%3Ccircle cx='49' cy='15' r='8' fill='%23c9f65b'/%3E%3Ctext x='32' y='42' text-anchor='middle' font-family='Arial,sans-serif' font-size='25' font-weight='700' fill='white'%3ED24%3C/text%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header" id="home">
        <div class="container nav-wrapper">
            <a class="logo" href="#home" aria-label="Design24 home">Design<span>24</span></a>

            <button class="menu-toggle" type="button" aria-label="Open navigation" aria-controls="main-menu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="main-nav" aria-label="Main navigation">
                <ul id="main-menu">
                    <li><a class="active" href="#home">Home</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-shape hero-shape-one"></div>
            <div class="hero-shape hero-shape-two"></div>
            <div class="container hero-content">
                <div class="hero-copy">
                    <p class="eyebrow"><span aria-hidden="true"></span> Independent creative studio</p>
                    <h1 id="hero-title">Digital design that turns <span>ideas into impact.</span></h1>
                    <p class="hero-subtitle">We build distinctive brands and thoughtful websites that help growing businesses earn attention, trust, and results.</p>
                    <div class="hero-actions">
                        <a class="button button-primary" href="#contact">Start a project <span aria-hidden="true">→</span></a>
                        <a class="button button-secondary" href="#services">View our services</a>
                    </div>
                    <div class="hero-stats" aria-label="Design24 highlights">
                        <div>
                            <strong>50+</strong>
                            <span>Projects delivered</span>
                        </div>
                        <div>
                            <strong>8 yrs</strong>
                            <span>Creative experience</span>
                        </div>
                        <div>
                            <strong>98%</strong>
                            <span>Happy clients</span>
                        </div>
                    </div>
                </div>

                <div class="hero-visual" aria-hidden="true">
                    <div class="visual-card main-card">
                        <div class="card-top">
                            <div><span></span><span></span><span></span></div>
                            <small>design24 / studio</small>
                        </div>
                        <div class="mockup-label">FEATURED PROJECT</div>
                        <div class="mockup-title">Bold ideas.<br>Clear direction.</div>
                        <div class="mockup-dashboard">
                            <span></span><span></span><span></span><span></span><span></span>
                        </div>
                        <div class="mockup-meta">
                            <span>Strategy</span><span>Identity</span><span>Digital</span>
                        </div>
                    </div>
                    <div class="visual-card floating-card">
                        <span class="spark">↗</span>
                        <strong>+42% growth</strong>
                        <small>After the brand refresh</small>
                    </div>
                    <div class="visual-badge">✦ Made to stand out</div>
                    <div class="dot-grid"></div>
                </div>
            </div>
        </section>

        <section class="about section" id="about" aria-labelledby="about-title">
            <div class="container about-grid">
                <div>
                    <p class="section-label">About us</p>
                    <h2 id="about-title">Small studio.<br>Big creative energy.</h2>
                </div>
                <div class="about-copy">
                    <p>We are a creative digital studio focused on clear ideas and thoughtful design. We believe the best work is simple, useful, and unmistakably yours.</p>
                    <p>From your first idea to the final launch, we work closely with you to create a digital presence that feels right and works hard.</p>
                    <div class="stats">
                        <div><strong>24/7</strong><span>Creative thinking</span></div>
                        <div><strong>100%</strong><span>Made with care</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="services section" id="services" aria-labelledby="services-title">
            <div class="container">
                <div class="section-heading">
                    <div>
                        <p class="section-label">What we do</p>
                        <h2 id="services-title">Services built around your goals.</h2>
                    </div>
                    <p>Practical creative support to help your business stand out in a busy digital world.</p>
                </div>

                <div class="service-grid">
                    <article class="service-card">
                        <div class="service-number">01</div>
                        <div class="service-icon" aria-hidden="true">✦</div>
                        <h3>Brand Design</h3>
                        <p>Memorable visual identities that tell your story with clarity and personality.</p>
                        <span class="card-link">Identity · Logo · Guidelines</span>
                    </article>

                    <article class="service-card featured">
                        <div class="service-number">02</div>
                        <div class="service-icon" aria-hidden="true">⌁</div>
                        <h3>Web Design</h3>
                        <p>Responsive, easy-to-use websites that look polished on every screen.</p>
                        <span class="card-link">Design · Development · Support</span>
                    </article>

                    <article class="service-card">
                        <div class="service-number">03</div>
                        <div class="service-icon" aria-hidden="true">↗</div>
                        <h3>Digital Strategy</h3>
                        <p>Focused plans that turn creative ideas into meaningful business results.</p>
                        <span class="card-link">Research · Content · Growth</span>
                    </article>
                </div>
            </div>
        </section>

        <section class="contact section" id="contact" aria-labelledby="contact-title">
            <div class="container contact-box">
                <div class="contact-copy">
                    <p class="section-label">Let's work together</p>
                    <h2 id="contact-title">Have a project in mind?</h2>
                    <p>Tell us a little about your idea. We would love to hear what you are building.</p>
                    <a href="mailto:hello@design24.com">hello@design24.com</a>
                </div>

                <form class="contact-form" id="contact-form">
                    <div class="form-row">
                        <label for="name">Your name</label>
                        <input type="text" id="name" name="name" placeholder="Jane Smith" required>
                    </div>
                    <div class="form-row">
                        <label for="email">Email address</label>
                        <input type="email" id="email" name="email" placeholder="jane@example.com" required>
                    </div>
                    <div class="form-row">
                        <label for="message">Tell us about your project</label>
                        <textarea id="message" name="message" rows="4" placeholder="A few details about your idea..." required></textarea>
                    </div>
                    <button class="button button-dark" type="submit">Send message <span aria-hidden="true">→</span></button>
                    <p class="form-status" id="form-status" role="status"></p>
                </form>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-content">
            <a class="logo logo-light" href="#home">Design<span>24</span></a>
            <p>&copy; <?= $currentYear ?> Design24. All rights reserved.</p>
            <a href="#home">Back to top ↑</a>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>

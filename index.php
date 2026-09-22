<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Grease N' Resin &mdash; urban-modern motorcycles with original fiberglass craftsmanship."
    >

    <title>Grease N' Resin | Custom Motorcycles &amp; Fiberglass Craftsmanship</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Main CSS -->
    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link rel="stylesheet" href="css/home.css">
    <link rel="stylesheet" href="css/products.css">
    <link rel="stylesheet" href="css/about.css">
    <link rel="stylesheet" href="css/services.css">
    <link rel="stylesheet" href="css/contact.css">
    <link rel="stylesheet" href="css/quote.css">

</head>

<body>

    <!-- ==========================================
         HEADER / NAVIGATION
         ========================================== -->

    <header class="site-header">

        <div class="container nav-container">

            <!-- LOGO -->

            <a
                href="#home"
                class="brand-logo"
                aria-label="Grease N' Resin Home"
            >

                <img
                    src="assets/images/GREASE &lsquo;N RESIN.png"
                    alt="Grease N' Resin logo"
                >

                <span class="brand-logo-text">
                    GREASE 'N <strong>RESIN</strong>
                </span>

            </a>


            <!-- DESKTOP NAVIGATION -->

            <nav
                class="main-navigation"
                aria-label="Main Navigation"
            >

                <a href="#home" class="active">
                    Home
                </a>

                <a href="#products">
                    Products
                </a>

                <a href="#about">
                    About Us
                </a>

                <a href="#services">
                    Services
                </a>

                <a href="#contact">
                    Contact Us
                </a>

                <a
                    href="#quote"
                    class="nav-quote-button"
                >
                    Get a Quote
                </a>

            </nav>


            <!-- MOBILE MENU BUTTON -->

            <button
                class="mobile-menu-button"
                type="button"
                aria-label="Open navigation menu"
                aria-expanded="false"
            >

                <span></span>
                <span></span>
                <span></span>

            </button>

        </div>

    </header>


    <!-- ==========================================
         GLOBAL PAGE NAVIGATION
         ========================================== -->

    <nav
        class="global-page-navigation"
        id="globalPageNavigation"
        aria-label="Page section navigation"
    >

        <a
            href="#home"
            class="global-page-dot active"
            data-section="home"
            aria-label="Go to Home"
            title="Go to Home"
            aria-current="page"
        ></a>

        <a
            href="#products"
            class="global-page-dot"
            data-section="products"
            aria-label="Go to Products"
            title="Go to Products"
        ></a>

        <a
            href="#about"
            class="global-page-dot"
            data-section="about"
            aria-label="Go to About Us"
            title="Go to About Us"
        ></a>

        <a
            href="#services"
            class="global-page-dot"
            data-section="services"
        ></a>

        <a
            href="#contact"
            class="global-page-dot"
            data-section="contact"
            aria-label="Go to Contact Us"
            title="Go to Contact Us"
        ></a>

        <a
            href="#quote"
            class="global-page-dot"
            data-section="quote"
            aria-label="Go to Get a Quote"
            title="Go to Get a Quote"
        ></a>

    </nav>


    <main>

        <?php include 'pages/home.php'; ?>
        <?php include 'pages/products.php'; ?>
        <?php include 'pages/about.php'; ?>
        <?php include 'pages/services.php'; ?>
        <?php include 'pages/contact.php'; ?>
        <?php include 'pages/quote.php'; ?>

    </main>

    <!-- ==========================================
         JAVASCRIPT
         ========================================== -->

    <script
        src="js/script.js"
    ></script>

    <footer class="site-footer">

    <div class="footer-container">

        <div class="footer-main">

            <div class="footer-brand">

                <a href="#home" class="footer-logo">
                    GREASE N' <span>RESIN</span>
                </a>

                <p>
                    Custom motorcycle design, fiberglass fabrication,
                    bodywork, and builds made with precision and attitude.
                </p>

            </div>

            <div class="footer-links">

                <div class="footer-column">

                    <h4>EXPLORE</h4>

                    <a href="#home">Home</a>
                    <a href="#products">Products</a>
                    <a href="#about">About Us</a>
                    <a href="#services">Services</a>
                    <a href="#contact">Contact</a>

                </div>

                <div class="footer-column">

                    <h4>START A PROJECT</h4>

                    <a href="#quote">Get a Quote</a>
                    <a href="#quote">Custom Build</a>
                    <a href="#quote">Product Inquiry</a>

                </div>

                <div class="footer-column">

                    <h4>CONNECT</h4>

                    <a href="#" target="_blank">Facebook</a>
                    <a href="#" target="_blank">Instagram</a>
                    <a href="#">Email</a>
                    <a href="#">Phone</a>

                </div>

            </div>

        </div>

        <div class="footer-bottom">

            <span>
                &copy; 2026 GREASE N' RESIN. ALL RIGHTS RESERVED.
            </span>

            <span>
                BUILT FOR THE BUILDERS.
            </span>

        </div>

    </div>

</footer>

<script>
document.addEventListener("DOMContentLoaded", function () {

    /* =========================================================
       GLOBAL PAGE NAVIGATION DOTS
       ========================================================= */

    const pageSections = [
        {
            id: "home",
            label: "Home"
        },
        {
            id: "products",
            label: "Products"
        },
        {
            id: "about",
            label: "About Us"
        },
        {
            id: "services",
            label: "Services"
        },
        {
            id: "contact",
            label: "Contact Us"
        },
        {
            id: "quote",
            label: "Get a Quote"
        }
    ];

    const pageNavDots = Array.from(
        document.querySelectorAll(".global-page-dot")
    );

    const mainNavigationLinks = Array.from(
        document.querySelectorAll(".main-navigation a[href^='#']")
    );

    const pageSectionElements = pageSections
        .map(function (section) {
            return document.getElementById(section.id);
        })
        .filter(Boolean);

    if (
        pageNavDots.length === pageSections.length &&
        pageSectionElements.length === pageSections.length
    ) {

        let activeSectionIndex = 0;
        let scrollTicking = false;

        function setActivePageDot(index) {

            index = Math.max(
                0,
                Math.min(index, pageNavDots.length - 1)
            );

            if (index === activeSectionIndex &&
                pageNavDots[index].classList.contains("active")) {
                return;
            }

            activeSectionIndex = index;

            pageNavDots.forEach(function (dot, dotIndex) {

                const isActive =
                    dotIndex === activeSectionIndex;

                dot.classList.toggle(
                    "active",
                    isActive
                );

                if (isActive) {
                    dot.setAttribute(
                        "aria-current",
                        "page"
                    );
                } else {
                    dot.removeAttribute(
                        "aria-current"
                    );
                }

            });

            const activeSectionId =
                pageSections[activeSectionIndex].id;

            mainNavigationLinks.forEach(function (link) {

                const isActive =
                    link.getAttribute("href") ===
                    "#" + activeSectionId;

                link.classList.toggle(
                    "active",
                    isActive
                );

                if (isActive) {
                    link.setAttribute(
                        "aria-current",
                        "page"
                    );
                } else {
                    link.removeAttribute(
                        "aria-current"
                    );
                }

            });
        }


        function getActiveSectionIndex() {

            const scrollPosition =
                window.scrollY;

            const documentHeight =
                document.documentElement.scrollHeight;

            const viewportHeight =
                window.innerHeight;

            const maxScroll =
                Math.max(
                    0,
                    documentHeight - viewportHeight
                );

            /*
             * Always make the final section active
             * when the user reaches the bottom of
             * the page, including the footer area.
             */
            if (
                maxScroll > 0 &&
                scrollPosition >= maxScroll - 8
            ) {
                return pageSectionElements.length - 1;
            }


            /*
             * Use a stable reference point inside
             * the viewport instead of intersection
             * percentages. This guarantees a strict
             * 1-to-1 relationship:
             *
             * Section 1 = Dot 1
             * Section 2 = Dot 2
             * etc.
             */
            const referencePoint =
                scrollPosition +
                (viewportHeight * 0.40);


            let detectedIndex = 0;

            pageSectionElements.forEach(
                function (section, index) {

                    const sectionRect =
                        section.getBoundingClientRect();

                    const sectionTop =
                        sectionRect.top +
                        window.scrollY;

                    const sectionBottom =
                        sectionTop +
                        sectionRect.height;

                    if (
                        referencePoint >= sectionTop &&
                        referencePoint < sectionBottom
                    ) {
                        detectedIndex = index;
                    }

                }
            );


            /*
             * Safety fallback:
             * if the reference point is between sections
             * because of unusual layout spacing, select
             * the nearest section above it.
             */
            const firstSectionTop =
                pageSectionElements[0].getBoundingClientRect().top +
                window.scrollY;

            if (referencePoint < firstSectionTop) {
                detectedIndex = 0;
            }


            return Math.max(
                0,
                Math.min(
                    detectedIndex,
                    pageSectionElements.length - 1
                )
            );
        }


        function updatePageNavigation() {

            setActivePageDot(
                getActiveSectionIndex()
            );

            scrollTicking = false;
        }


        function requestPageNavigationUpdate() {

            if (scrollTicking) {
                return;
            }

            scrollTicking = true;

            window.requestAnimationFrame(
                updatePageNavigation
            );
        }


        pageNavDots.forEach(
            function (dot, index) {

                dot.addEventListener(
                    "click",
                    function (event) {

                        event.preventDefault();

                        const targetSection =
                            pageSectionElements[index];

                        if (!targetSection) {
                            return;
                        }

                        /*
                         * Immediately reflect the
                         * clicked destination.
                         */
                        setActivePageDot(index);

                        targetSection.scrollIntoView({
                            behavior: "smooth",
                            block: "start"
                        });

                    }
                );

            }
        );


        window.addEventListener(
            "scroll",
            requestPageNavigationUpdate,
            {
                passive: true
            }
        );


        window.addEventListener(
            "resize",
            requestPageNavigationUpdate,
            {
                passive: true
            }
        );


        /*
         * Correct state on initial page load,
         * including page refresh while scrolled.
         */
        updatePageNavigation();

    }

});


document.addEventListener("DOMContentLoaded", function () {

    const cards = document.querySelectorAll(".product-card");
    const dots = document.querySelectorAll(".products-dot");
    const previousButton = document.querySelector(".products-arrow-prev");
    const nextButton = document.querySelector(".products-arrow-next");

    if (!cards.length) {
        return;
    }

    let activeIndex = 0;

    function updateCarousel() {

        cards.forEach(function (card, index) {

            card.classList.remove(
                "active",
                "prev",
                "next"
            );

            const previousIndex =
                (activeIndex - 1 + cards.length) % cards.length;

            const nextIndex =
                (activeIndex + 1) % cards.length;

            if (index === activeIndex) {

                card.classList.add("active");

            } else if (index === previousIndex) {

                card.classList.add("prev");

            } else if (index === nextIndex) {

                card.classList.add("next");

            }

        });


        dots.forEach(function (dot, index) {

            dot.classList.toggle(
                "active",
                index === activeIndex
            );

        });

    }


    function goToSlide(index) {

        activeIndex =
            (index + cards.length) % cards.length;

        updateCarousel();

    }


    previousButton.addEventListener(
        "click",
        function () {

            goToSlide(activeIndex - 1);

        }
    );


    nextButton.addEventListener(
        "click",
        function () {

            goToSlide(activeIndex + 1);

        }
    );


    dots.forEach(function (dot, index) {

        dot.addEventListener(
            "click",
            function () {

                goToSlide(index);

            }
        );

    });


    updateCarousel();

});

document.addEventListener("DOMContentLoaded", function () {

    const productCards =
        document.querySelectorAll(".product-card");

    const productModal =
        document.getElementById("productModal");

    const productModalOverlay =
        document.querySelector(".product-modal-overlay");

    const productModalClose =
        document.getElementById("productModalClose");

    const productModalImage =
        document.getElementById("productModalImage");

    const productModalNumber =
        document.getElementById("productModalNumber");

    const productModalTitle =
        document.getElementById("productModalTitle");

    const productModalDescription =
        document.getElementById("productModalDescription");

    const productModalBuy =
        document.getElementById("productModalBuy");


    function openProductModal(card) {

        const image =
            card.querySelector(".product-image-asset");

        const number =
            card.querySelector(".product-number");

        const title =
            card.querySelector("h3");

        const description =
            card.querySelector(".product-content p");

        const productId =
            card.dataset.productId;


        if (!image || !number || !title || !description) {
            return;
        }


        productModalImage.src =
            image.src;

        productModalImage.alt =
            image.alt;

        productModalNumber.textContent =
            number.textContent.trim();

        productModalTitle.textContent =
            title.textContent.trim();

        productModalDescription.textContent =
            description.textContent.trim();


        productModalBuy.onclick = function () {

            productModal.classList.remove("active");

            document.body.classList.remove(
                "product-modal-open"
            );

            setTimeout(function () {

                selectProduct(productId);

            }, 200);

        };


        productModal.classList.add("active");

        document.body.classList.add(
            "product-modal-open"
        );

    }


    function closeProductModal() {

        productModal.classList.remove(
            "active"
        );

        document.body.classList.remove(
            "product-modal-open"
        );

    }


    productCards.forEach(function (card) {

        card.addEventListener(
            "click",
            function (event) {

                if (
                    event.target.closest(
                        ".product-buy-button"
                    )
                ) {
                    return;
                }

                openProductModal(card);

            }
        );

    });


    productModalClose.addEventListener(
        "click",
        closeProductModal
    );


    productModalOverlay.addEventListener(
        "click",
        closeProductModal
    );


    document.addEventListener(
        "keydown",
        function (event) {

            if (
                event.key === "Escape" &&
                productModal.classList.contains("active")
            ) {
                closeProductModal();
            }

        }
    );

});
</script>

</body>

</html>

<?php

require_once __DIR__ . "/config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$quoteMessage = $_SESSION["quote_message"] ?? "";
$quoteMessageType = $_SESSION["quote_message_type"] ?? "";
unset($_SESSION["quote_message"], $_SESSION["quote_message_type"]);

if (empty($_SESSION["quote_form_token"])) {
    $_SESSION["quote_form_token"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["form-name"] ?? "") === "quote") {
    $submittedToken = $_POST["quote_form_token"] ?? "";
    $validToken = !empty($_SESSION["quote_form_token"])
        && is_string($submittedToken)
        && hash_equals($_SESSION["quote_form_token"], $submittedToken);

    if (!$validToken) {
        header("Location: index.php");
        exit;
    }

    $customerName = trim($_POST["customer_name"] ?? "");
    $customerPhone = trim($_POST["customer_phone"] ?? "");
    $customerEmail = trim($_POST["customer_email"] ?? "");
    $socialPlatform = trim($_POST["social_platform"] ?? "");
    $socialLink = trim($_POST["social_link"] ?? "");
    $brand = trim($_POST["motorcycle_brand"] ?? "");
    $model = trim($_POST["motorcycle_model"] ?? "");
    $yearModel = trim($_POST["motorcycle_year"] ?? "");
    $plateNo = trim($_POST["plate_no"] ?? "");
    $engineNo = trim($_POST["engine_no"] ?? "");
    $chassisNo = trim($_POST["chassis_no"] ?? "");
    $color = trim($_POST["color"] ?? "");
    $serviceType = trim($_POST["service_type"] ?? "");
    $projectDescription = trim($_POST["project_description"] ?? "");
    $preferredDate = trim($_POST["preferred_date"] ?? "");
    $budgetRange = trim($_POST["budget_range"] ?? "");

    if ($customerName === "" || $customerPhone === "" || $serviceType === "" || $projectDescription === "") {
        $quoteMessage = "Please complete the required customer and project fields.";
        $quoteMessageType = "error";
    } elseif ($customerEmail !== "" && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        $quoteMessage = "Please enter a valid email address.";
        $quoteMessageType = "error";
    } elseif ($socialLink !== "" && (!filter_var($socialLink, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $socialLink))) {
        $quoteMessage = "Please enter a valid Facebook or Instagram link.";
        $quoteMessageType = "error";
    } else {
        try {
            $pdo->beginTransaction();

            $customerStmt = $pdo->prepare("SELECT id FROM customers WHERE contact_no = ? AND fullname = ? LIMIT 1");
            $customerStmt->execute([$customerPhone, $customerName]);
            $customerId = $customerStmt->fetchColumn();

            if (!$customerId) {
                $customerNo = "CUS-" . date("YmdHis") . random_int(10, 99);
                $customerStmt = $pdo->prepare("INSERT INTO customers (customer_no, fullname, contact_no, facebook, social_platform, social_link, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $customerStmt->execute([$customerNo, $customerName, $customerPhone, $socialPlatform === "Facebook" ? $socialLink : null, $socialPlatform ?: null, $socialLink ?: null, $customerEmail !== "" ? "Email: " . $customerEmail : null]);
                $customerId = $pdo->lastInsertId();
            } elseif ($socialLink !== "") {
                $customerUpdate = $pdo->prepare("UPDATE customers SET social_platform = ?, social_link = ?, facebook = CASE WHEN ? = 'Facebook' THEN ? ELSE facebook END WHERE id = ?");
                $customerUpdate->execute([$socialPlatform ?: null, $socialLink, $socialPlatform, $socialLink, $customerId]);
            }

            $vehicleId = null;
            if ($brand !== "" || $model !== "" || $yearModel !== "" || $plateNo !== "" || $engineNo !== "" || $chassisNo !== "" || $color !== "") {
                $vehicleStmt = $pdo->prepare("INSERT INTO vehicles (customer_id, brand, model, year_model, plate_no, engine_no, chassis_no, color) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $vehicleStmt->execute([$customerId, $brand ?: null, $model ?: null, $yearModel ?: null, $plateNo ?: null, $engineNo ?: null, $chassisNo ?: null, $color ?: null]);
                $vehicleId = $pdo->lastInsertId();
            }

            $inquiryNo = "INQ-" . date("YmdHis") . random_int(10, 99);
            $description = $projectDescription;
            $details = array_filter([
                $preferredDate !== "" ? "Preferred date: " . $preferredDate : null,
                $budgetRange !== "" ? "Budget: " . $budgetRange : null,
                !empty($_POST["preferred_contact"]) ? "Preferred contact: " . $_POST["preferred_contact"] : null
            ]);
            if ($details) {
                $description .= "\n\n" . implode("\n", $details);
            }

            $inquiryStmt = $pdo->prepare("INSERT INTO inquiries (inquiry_no, customer_id, vehicle_id, inquiry_type, description, status) VALUES (?, ?, ?, ?, ?, 'NEW')");
            $inquiryStmt->execute([$inquiryNo, $customerId, $vehicleId, $serviceType, $description]);

            $inquiryId = $pdo->lastInsertId();
            $uploadDirectory = __DIR__ . "/uploads/inquiries";
            $allowedMimeTypes = ["image/jpeg", "image/png", "image/webp"];
            $uploadedFiles = $_FILES["reference_images"] ?? null;

            if ($uploadedFiles && is_array($uploadedFiles["error"])) {
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
                    throw new RuntimeException("Unable to create upload directory.");
                }

                $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                foreach ($uploadedFiles["error"] as $index => $uploadError) {
                    if ($uploadError === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ($uploadError !== UPLOAD_ERR_OK || (int) $uploadedFiles["size"][$index] > 5 * 1024 * 1024) {
                        throw new RuntimeException("Each image must be a valid upload up to 5 MB.");
                    }

                    $temporaryPath = $uploadedFiles["tmp_name"][$index];
                    $mimeType = $fileInfo->file($temporaryPath);
                    if (!in_array($mimeType, $allowedMimeTypes, true) || @getimagesize($temporaryPath) === false) {
                        throw new RuntimeException("Only valid JPG, PNG, or WEBP images are allowed.");
                    }

                    $extension = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"][$mimeType];
                    $storedName = bin2hex(random_bytes(16)) . "." . $extension;
                    if (!move_uploaded_file($temporaryPath, $uploadDirectory . "/" . $storedName)) {
                        throw new RuntimeException("Unable to save the uploaded image.");
                    }

                    $attachmentStmt = $pdo->prepare("INSERT INTO inquiry_attachments (inquiry_id, original_name, stored_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?)");
                    $attachmentStmt->execute([$inquiryId, basename($uploadedFiles["name"][$index]), $storedName, $mimeType, (int) $uploadedFiles["size"][$index]]);
                }
            }

            $pdo->commit();
            unset($_SESSION["quote_form_token"]);
            $_SESSION["quote_message"] = "Your inquiry has been submitted. Reference: " . $inquiryNo;
            $_SESSION["quote_message_type"] = "success";
            header("Location: index.php");
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $quoteMessage = "We could not submit your inquiry right now. Please try again.";
            $quoteMessageType = "error";
        }
    }
}

?>
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

<?php if ($quoteMessage && $quoteMessageType === "success"): ?>
    <div class="quote-success-overlay" data-quote-success-modal role="dialog" aria-modal="true" aria-labelledby="quote-success-title">
        <div class="quote-success-backdrop" data-quote-success-close></div>
        <div class="quote-success-modal">
            <button class="quote-success-close" type="button" data-quote-success-close aria-label="Close success message">&times;</button>
            <div class="quote-success-icon">&#10003;</div>
            <strong id="quote-success-title">Inquiry submitted</strong>
            <span><?= htmlspecialchars($quoteMessage) ?></span>
            <button class="quote-success-button" type="button" data-quote-success-close>Okay</button>
        </div>
    </div>
<?php endif; ?>

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

    const productModalPrice =
        document.getElementById("productModalPrice");

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

        if (productModalPrice) {
            productModalPrice.textContent =
                card.dataset.productPrice || "CUSTOM QUOTE";
        }


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

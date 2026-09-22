document.addEventListener("DOMContentLoaded", function () {
	const menuButton = document.querySelector(".mobile-menu-button");
	const navigation = document.querySelector(".main-navigation");
	const navigationLinks = navigation
		? navigation.querySelectorAll("a[href^='#']")
		: [];

	navigationLinks.forEach(function (link) {
		link.addEventListener("click", function () {
			navigationLinks.forEach(function (navigationLink) {
				navigationLink.classList.remove("active");
			});

			link.classList.add("active");
		});
	});

	if (menuButton && navigation) {
		menuButton.addEventListener("click", function () {
			const isOpen = navigation.classList.toggle("is-open");

			menuButton.setAttribute("aria-expanded", String(isOpen));
			menuButton.setAttribute(
				"aria-label",
				isOpen ? "Close navigation menu" : "Open navigation menu"
			);
		});

		navigation.querySelectorAll("a").forEach(function (link) {
			link.addEventListener("click", function () {
				navigation.classList.remove("is-open");
				menuButton.setAttribute("aria-expanded", "false");
				menuButton.setAttribute("aria-label", "Open navigation menu");
			});
		});
	}
});

const productNames = {
	1: "Barako Cowling",
	2: "MDL Lights Cover",
    3: "Bobber Tank-Cover",
    4: "Front Fender",
    5: "Engine Cowl"
};

const serviceNames = {
	1: "Custom Fiberglass",
	2: "Custom Builds",
	3: "Design & Fabrication",
	4: "Custom Modification"
};

document.addEventListener("DOMContentLoaded", function () {
    const successModal = document.querySelector("[data-quote-success-modal]");

    if (successModal) {
        document.body.classList.add("quote-success-open");

        function closeSuccessModal() {
            successModal.classList.add("is-closing");
            document.body.classList.remove("quote-success-open");
            window.setTimeout(function () {
                successModal.remove();
            }, 180);
        }

        successModal.querySelectorAll("[data-quote-success-close]").forEach(function (control) {
            control.addEventListener("click", closeSuccessModal);
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && document.body.contains(successModal)) closeSuccessModal();
        });
    }

    const imageInput = document.getElementById("reference_images");
    const selectedText = document.querySelector("[data-upload-selected]");
    const preview = document.querySelector("[data-upload-preview]");
    const socialPlatform = document.getElementById("social_platform");
    const socialLink = document.getElementById("social_link");

    if (!imageInput || !selectedText || !preview) {
        if (!socialPlatform || !socialLink) return;
    }

    function updateSocialPlaceholder() {
        if (!socialPlatform || !socialLink) return;

        const placeholders = {
            Facebook: "https://facebook.com/yourprofile",
            Instagram: "https://instagram.com/yourprofile"
        };

        socialLink.placeholder = placeholders[socialPlatform.value] || "https://facebook.com/yourprofile";
    }

    if (socialPlatform && socialLink) {
        socialPlatform.addEventListener("change", updateSocialPlaceholder);
        updateSocialPlaceholder();
    }

    if (!imageInput || !selectedText || !preview) return;

    imageInput.addEventListener("change", function () {
        preview.innerHTML = "";

        if (!imageInput.files.length) {
            selectedText.textContent = "No images selected yet.";
            return;
        }

        const fileCount = imageInput.files.length;
        selectedText.textContent = fileCount + (fileCount === 1 ? " image selected" : " images selected");

        Array.from(imageInput.files).forEach(function (file) {
            const item = document.createElement("div");
            item.className = "quote-upload-preview-item";

            const image = document.createElement("img");
            image.alt = file.name;
            image.src = URL.createObjectURL(file);

            const name = document.createElement("span");
            name.textContent = file.name;

            item.append(image, name);
            preview.appendChild(item);
        });
    });
});

function selectProduct(productId) {
	const serviceType = document.getElementById("service_type");
	const description = document.getElementById("project_description");
	const productName = productNames[productId] || "Custom Product";

	if (serviceType) {
		serviceType.value = "product_purchase";
	}

	if (description && !description.value.trim()) {
		description.value = "I am interested in the " + productName + ".";
	}

	document.getElementById("quote")?.scrollIntoView({ behavior: "smooth" });
}

function selectService(serviceId) {
	const serviceType = document.getElementById("service_type");
	const description = document.getElementById("project_description");
	const serviceName = serviceNames[serviceId] || "Custom Project";

	if (serviceType) {
		serviceType.value = serviceId === 1 ? "custom_fiberglass" : "other";
	}

	if (description && !description.value.trim()) {
		description.value = "I am interested in " + serviceName + ".";
	}

	document.getElementById("quote")?.scrollIntoView({ behavior: "smooth" });
}

/* =========================================================
   GET A QUOTE — ACCORDION
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    const accordionSections =
        document.querySelectorAll(".quote-form-section");

    if (!accordionSections.length) {
        return;
    }

    const triggers =
        document.querySelectorAll(
            "[data-quote-accordion-trigger]"
        );


    /* =====================================================
       OPEN SECTION
       ===================================================== */

    function openSection(section, shouldScroll = false) {

        const currentOpen =
            document.querySelector(
                ".quote-form-section.is-open"
            );

        /*
         * Close any other open section.
         * This keeps only ONE accordion open at a time.
         */
        if (
            currentOpen &&
            currentOpen !== section
        ) {
            closeSection(currentOpen);
        }


        const trigger =
            section.querySelector(
                "[data-quote-accordion-trigger]"
            );

        const panel =
            section.querySelector(
                ".quote-accordion-content"
            );


        if (!trigger || !panel) {
            return;
        }


        section.classList.add("is-open");

        trigger.setAttribute(
            "aria-expanded",
            "true"
        );

        panel.setAttribute(
            "aria-hidden",
            "false"
        );


        /*
         * When a closed section is clicked,
         * smoothly bring it into view.
         */
        if (shouldScroll) {

            window.setTimeout(function () {

                const headerOffset = 110;

                const sectionTop =
                    section.getBoundingClientRect().top +
                    window.scrollY -
                    headerOffset;

                window.scrollTo({
                    top: Math.max(
                        0,
                        sectionTop
                    ),
                    behavior: "smooth"
                });

            }, 120);

        }

    }


    /* =====================================================
       CLOSE SECTION
       ===================================================== */

    function closeSection(section) {

        const trigger =
            section.querySelector(
                "[data-quote-accordion-trigger]"
            );

        const panel =
            section.querySelector(
                ".quote-accordion-content"
            );


        if (!trigger || !panel) {
            return;
        }


        section.classList.remove("is-open");

        trigger.setAttribute(
            "aria-expanded",
            "false"
        );

        panel.setAttribute(
            "aria-hidden",
            "true"
        );

    }


    /* =====================================================
       CLICK + KEYBOARD EVENTS
       ===================================================== */

    triggers.forEach(function (trigger) {

        function toggleAccordion() {

            const section =
                trigger.closest(
                    ".quote-form-section"
                );


            if (!section) {
                return;
            }


            const isOpen =
                section.classList.contains(
                    "is-open"
                );


            if (isOpen) {

                /*
                 * Clicking an already-open section
                 * will close it.
                 */
                closeSection(section);

            } else {

                /*
                 * Open the selected section
                 * and close the previous one.
                 */
                openSection(
                    section,
                    true
                );

            }

        }


        /*
         * Mouse click
         */
        trigger.addEventListener(
            "click",
            toggleAccordion
        );


        /*
         * Keyboard support:
         * ENTER or SPACE
         */
        trigger.addEventListener(
            "keydown",
            function (event) {

                if (
                    event.key === "Enter" ||
                    event.key === " "
                ) {

                    event.preventDefault();

                    toggleAccordion();

                }

            }
        );

    });


    /* =====================================================
       FORM VALIDATION SUPPORT
       ===================================================== */

    const quoteForm =
        document.querySelector(
            ".quote-form"
        );


    if (quoteForm) {


        /*
         * If a required field inside a CLOSED
         * accordion section is invalid,
         * automatically open that section.
         */
        quoteForm.addEventListener(
            "invalid",
            function (event) {

                const field =
                    event.target;

                const firstInvalidField =
                    quoteForm.querySelector(
                        ":invalid"
                    );


                if (field !== firstInvalidField) {
                    return;
                }


                const section =
                    field.closest(
                        ".quote-form-section"
                    );


                if (!section) {
                    return;
                }


                openSection(
                    section,
                    true
                );

            },
            true
        );


        /*
         * Extra submit validation.
         *
         * If the form contains an invalid field,
         * find which accordion section contains it,
         * open that section,
         * then focus the invalid field.
         */
        quoteForm.addEventListener(
            "submit",
            function (event) {

                const invalidField =
                    quoteForm.querySelector(
                        ":invalid"
                    );


                if (!invalidField) {
                    return;
                }


                const section =
                    invalidField.closest(
                        ".quote-form-section"
                    );


                if (!section) {
                    return;
                }


                event.preventDefault();


                openSection(
                    section,
                    true
                );


                window.setTimeout(
                    function () {

                        invalidField.focus();

                        invalidField.reportValidity();

                    },
                    180
                );

            }
        );

    }

});
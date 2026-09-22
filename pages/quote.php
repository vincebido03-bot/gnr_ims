<section id="quote" class="quote-section">

```
<div class="quote-container">

    <!-- ==========================================
         HEADER
         ========================================== -->

    <div class="quote-header">

        <div class="quote-eyebrow">
            <span class="eyebrow-line"></span>
            <span>05 / START A PROJECT</span>
        </div>

        <h2 class="quote-title">
            GET A
            <span>QUOTE.</span>
        </h2>

        <p class="quote-description">
            Tell us what you have in mind. Fill out the form below
            and give us a few details about your motorcycle and
            the project you want to build.
        </p>

    </div>


    <!-- ==========================================
         QUOTE FORM
         ========================================== -->

    <form
        class="quote-form"
        name="quote"
        method="POST"
        action="index.php#quote"
        enctype="multipart/form-data"
    >

        <input type="hidden" name="form-name" value="quote">
        <input type="hidden" name="quote_form_token" value="<?= htmlspecialchars($_SESSION["quote_form_token"] ?? "", ENT_QUOTES) ?>">

        <?php if (!empty($quoteMessage) && $quoteMessageType !== "success"): ?>
            <div class="quote-form-message <?= htmlspecialchars($quoteMessageType) ?>">
                <?= htmlspecialchars($quoteMessage) ?>
            </div>
        <?php endif; ?>

        <p hidden>
            <label>Do not fill this out: <input name="bot-field"></label>
        </p>

        <!-- ==========================================
             CUSTOMER INFORMATION
             ========================================== -->

        <div class="quote-form-section">

            <div
                class="quote-form-heading quote-accordion-trigger"
                role="button"
                tabindex="0"
                aria-expanded="false"
                aria-controls="quote-panel-1"
                data-quote-accordion-trigger
            >

                <span class="quote-form-number">
                    01
                </span>

                <span class="quote-form-heading-copy">
                    <span class="quote-form-label">
                        CUSTOMER INFORMATION
                    </span>

                    <span class="quote-form-question">
                        WHO ARE WE BUILDING FOR?
                    </span>
                </span>

                <span class="quote-accordion-arrow" aria-hidden="true">&#8250;</span>

            </div>


<div class="quote-accordion-content" id="quote-panel-1" aria-hidden="true">
                <div class="quote-accordion-content-inner">
            <div class="quote-form-grid">

                <div class="quote-field quote-field-full">

                    <label for="building_for">
                        Who Are We Building For?
                    </label>

                    <select
                        id="building_for"
                        name="building_for"
                        required
                    >

                        <option value="">
                            Select one
                        </option>

                        <option value="myself">
                            Myself
                        </option>

                        <option value="someone_else">
                            Someone Else
                        </option>

                        <option value="client">
                            A Client
                        </option>

                        <option value="business">
                            A Business / Organization
                        </option>

                    </select>

                </div>

                <div class="quote-field">

                    <label for="customer_name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="customer_name"
                        name="customer_name"
                        placeholder="Juan Dela Cruz"
                        required
                    >

                </div>

                <div class="quote-field">

                    <label for="customer_phone">
                        Contact Number
                    </label>

                    <input
                        type="tel"
                        id="customer_phone"
                        name="customer_phone"
                        placeholder="+63 9XX XXX XXXX"
                        required
                    >

                </div>


                <div class="quote-field">

                    <label for="customer_email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="customer_email"
                        name="customer_email"
                        placeholder="you@example.com"
                    >

                </div>


                <div class="quote-field">

                    <label for="preferred_contact">
                        Preferred Contact
                    </label>

                    <select
                        id="preferred_contact"
                        name="preferred_contact"
                    >

                        <option value="">
                            Select one
                        </option>

                        <option value="phone">
                            Phone / Text
                        </option>

                        <option value="email">
                            Email
                        </option>

                        <option value="facebook">
                            Facebook
                        </option>

                        <option value="instagram">
                            Instagram
                        </option>

                    </select>

                </div>

                <div class="quote-field quote-field-full">
                    <label for="social_link">
                        Social Media Link <span class="quote-optional">(Optional)</span>
                    </label>
                    <div class="quote-social-fields">
                        <select id="social_platform" name="social_platform">
                            <option value="">Select platform</option>
                            <option value="Facebook">Facebook</option>
                            <option value="Instagram">Instagram</option>
                        </select>
                        <input type="url" id="social_link" name="social_link" placeholder="https://facebook.com/yourprofile">
                    </div>
                </div>

            </div>
                </div>
            </div>

        </div>


        <!-- ==========================================
             MOTORCYCLE INFORMATION
             ========================================== -->

        <div class="quote-form-section">

            <div
                class="quote-form-heading quote-accordion-trigger"
                role="button"
                tabindex="0"
                aria-expanded="false"
                aria-controls="quote-panel-2"
                data-quote-accordion-trigger
            >

                <span class="quote-form-number">
                    02
                </span>

                <span class="quote-form-heading-copy">
                    <span class="quote-form-label">
                        MOTORCYCLE INFORMATION
                    </span>

                    <span class="quote-form-question">
                        WHAT ARE WE WORKING ON?
                    </span>
                </span>

                <span class="quote-accordion-arrow" aria-hidden="true">&#8250;</span>

            </div>


<div class="quote-accordion-content" id="quote-panel-2" aria-hidden="true">
                <div class="quote-accordion-content-inner">
            <div class="quote-form-grid">

                <div class="quote-field">

                    <label for="motorcycle_brand">
                        Motorcycle Brand
                    </label>

                    <input
                        type="text"
                        id="motorcycle_brand"
                        name="motorcycle_brand"
                        placeholder="e.g. Yamaha"
                        required
                    >

                </div>


                <div class="quote-field">

                    <label for="motorcycle_model">
                        Motorcycle Model
                    </label>

                    <input
                        type="text"
                        id="motorcycle_model"
                        name="motorcycle_model"
                        placeholder="e.g. XSR 155"
                        required
                    >

                </div>


                <div class="quote-field">

                    <label for="motorcycle_year">
                        Year Model
                    </label>

                    <input
                        type="number"
                        id="motorcycle_year"
                        name="motorcycle_year"
                        placeholder="2026"
                        min="1900"
                        max="2100"
                    >

                </div>


                <div class="quote-field">

                    <label for="motorcycle_type">
                        Motorcycle Type
                    </label>

                    <select
                        id="motorcycle_type"
                        name="motorcycle_type"
                    >

                        <option value="">
                            Select type
                        </option>

                        <option value="underbone">
                            Underbone
                        </option>

                        <option value="scooter">
                            Scooter
                        </option>

                        <option value="standard">
                            Standard
                        </option>

                        <option value="naked">
                            Naked
                        </option>

                        <option value="sport">
                            Sport
                        </option>

                        <option value="cruiser">
                            Cruiser
                        </option>

                        <option value="classic">
                            Classic
                        </option>

                        <option value="other">
                            Other
                        </option>

                    </select>

                </div>

                <div class="quote-field">
                    <label for="plate_no">Plate Number <span class="quote-optional">(Optional)</span></label>
                    <input type="text" id="plate_no" name="plate_no" placeholder="e.g. ABC 1234">
                </div>

                <div class="quote-field">
                    <label for="engine_no">Engine Number <span class="quote-optional">(Optional)</span></label>
                    <input type="text" id="engine_no" name="engine_no" placeholder="Enter engine number">
                </div>

                <div class="quote-field">
                    <label for="chassis_no">Chassis Number <span class="quote-optional">(Optional)</span></label>
                    <input type="text" id="chassis_no" name="chassis_no" placeholder="Enter chassis number">
                </div>

                <div class="quote-field">
                    <label for="color">Motorcycle Color <span class="quote-optional">(Optional)</span></label>
                    <input type="text" id="color" name="color" placeholder="e.g. Matte Black">
                </div>

            </div>
                </div>
            </div>

        </div>


        <!-- ==========================================
             PROJECT INFORMATION
             ========================================== -->

        <div class="quote-form-section">

            <div
                class="quote-form-heading quote-accordion-trigger"
                role="button"
                tabindex="0"
                aria-expanded="false"
                aria-controls="quote-panel-3"
                data-quote-accordion-trigger
            >

                <span class="quote-form-number">
                    03
                </span>

                <span class="quote-form-heading-copy">
                    <span class="quote-form-label">
                        PROJECT INFORMATION
                    </span>

                    <span class="quote-form-question">
                        WHAT DO YOU WANT TO BUILD?
                    </span>
                </span>

                <span class="quote-accordion-arrow" aria-hidden="true">&#8250;</span>

            </div>


<div class="quote-accordion-content" id="quote-panel-3" aria-hidden="true">
                <div class="quote-accordion-content-inner">
            <div class="quote-form-grid">

                <div class="quote-field quote-field-full">

                    <label for="service_type">
                        Service / Project Type
                    </label>

                    <select
                        id="service_type"
                        name="service_type"
                        required
                    >

                        <option value="">
                            Select a service
                        </option>

                        <option value="custom_fiberglass">
                            Custom Fiberglass
                        </option>

                        <option value="bodywork">
                            Bodywork / Modification
                        </option>

                        <option value="custom_design">
                            Custom Design
                        </option>

                        <option value="restoration">
                            Restoration
                        </option>

                        <option value="product_purchase">
                            Product Purchase
                        </option>

                        <option value="other">
                            Other
                        </option>

                    </select>

                </div>


                <div class="quote-field quote-field-full">

                    <label for="project_description">
                        Tell Us About Your Project
                    </label>

                    <textarea
                        id="project_description"
                        name="project_description"
                        rows="6"
                        placeholder="Describe the parts, modifications, style, or concept you have in mind..."
                        required
                    ></textarea>

                </div>

            </div>
                </div>
            </div>

        </div>


        <!-- ==========================================
             SCHEDULE & BUDGET
             ========================================== -->

        <div class="quote-form-section">

            <div
                class="quote-form-heading quote-accordion-trigger"
                role="button"
                tabindex="0"
                aria-expanded="false"
                aria-controls="quote-panel-4"
                data-quote-accordion-trigger
            >

                <span class="quote-form-number">
                    04
                </span>

                <span class="quote-form-heading-copy">
                    <span class="quote-form-label">
                        PROJECT PLANNING
                    </span>

                    <span class="quote-form-question">
                        WHEN & HOW MUCH?
                    </span>
                </span>

                <span class="quote-accordion-arrow" aria-hidden="true">&#8250;</span>

            </div>


<div class="quote-accordion-content" id="quote-panel-4" aria-hidden="true">
                <div class="quote-accordion-content-inner">
            <div class="quote-form-grid">

                <div class="quote-field">

                    <label for="preferred_date">
                        Preferred Schedule
                    </label>

                    <input
                        type="date"
                        id="preferred_date"
                        name="preferred_date"
                    >

                </div>


                <div class="quote-field">

                    <label for="budget_range">
                        Budget Range
                    </label>

                    <select
                        id="budget_range"
                        name="budget_range"
                    >

                        <option value="">
                            Select budget
                        </option>

                        <option value="below_5k">
                            Below &#8369;5,000
                        </option>

                        <option value="5k_10k">
                            &#8369;5,000 &mdash; &#8369;10,000
                        </option>

                        <option value="10k_25k">
                            &#8369;10,000 &mdash; &#8369;25,000
                        </option>

                        <option value="25k_50k">
                            &#8369;25,000 &mdash; &#8369;50,000
                        </option>

                        <option value="50k_plus">
                            &#8369;50,000+
                        </option>

                        <option value="discuss">
                            Let's Discuss
                        </option>

                    </select>

                </div>

            </div>
                </div>
            </div>

        </div>


        <!-- ==========================================
             REFERENCE IMAGES
             ========================================== -->

        <div class="quote-form-section">

            <div
                class="quote-form-heading quote-accordion-trigger"
                role="button"
                tabindex="0"
                aria-expanded="false"
                aria-controls="quote-panel-5"
                data-quote-accordion-trigger
            >

                <span class="quote-form-number">
                    05
                </span>

                <span class="quote-form-heading-copy">
                    <span class="quote-form-label">
                        REFERENCE MATERIAL
                    </span>

                    <span class="quote-form-question">
                        SHOW US THE IDEA.
                    </span>
                </span>

                <span class="quote-accordion-arrow" aria-hidden="true">&#8250;</span>

            </div>


<div class="quote-accordion-content" id="quote-panel-5" aria-hidden="true">
                <div class="quote-accordion-content-inner">
            <div class="quote-upload">

                <input
                    type="file"
                    id="reference_images"
                    name="reference_images[]"
                    accept="image/png,image/jpeg,image/webp"
                    multiple
                >

                <label for="reference_images">

                    <span class="quote-upload-icon">
                        +
                    </span>

                    <span class="quote-upload-title">
                        UPLOAD REFERENCE IMAGES
                    </span>

                    <span class="quote-upload-text">
                        JPG, PNG or WEBP &mdash; you may upload multiple images.
                    </span>

                    <span class="quote-upload-selected" data-upload-selected>
                        No images selected yet.
                    </span>

                </label>

                <div class="quote-upload-preview" data-upload-preview aria-live="polite"></div>

            </div>
                </div>
            </div>

        </div>


        <!-- ==========================================
             SUBMIT
             ========================================== -->

        <div class="quote-submit">

            <div class="quote-submit-copy">

                <span>
                    READY WHEN YOU ARE.
                </span>

                <p>
                    We'll review your project details and
                    get back to you with the next steps.
                </p>

            </div>


            <button
                type="submit"
                class="button button-primary quote-submit-button"
            >

                Submit Inquiry

                <span class="button-arrow">
                    &#8599;
                </span>

            </button>

        </div>

    </form>

</div>

</section>



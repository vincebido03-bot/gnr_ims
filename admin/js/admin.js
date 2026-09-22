
// GNR IMS - ADMIN JAVASCRIPT

document.addEventListener("DOMContentLoaded", function () {

    console.log("GNR IMS Admin loaded.");

    const displayInput = document.getElementById("hire_date_display");
    const hiddenInput = document.getElementById("hire_date");
    const calendar = document.getElementById("calendar-popup");
    const openButton = document.getElementById("open-calendar");
    const previousButton = document.getElementById("calendar-prev");
    const nextButton = document.getElementById("calendar-next");
    const todayButton = document.getElementById("calendar-today");
    const monthYear = document.getElementById("calendar-month-year");
    const daysContainer = document.getElementById("calendar-days");
    const calendarView = document.getElementById("calendar-view");
    const calendarSelector = document.getElementById("calendar-selector");
    const selectorYear = document.getElementById("selector-year");
    const previousYearButton = document.getElementById("selector-prev-year");
    const nextYearButton = document.getElementById("selector-next-year");
    const monthGrid = document.getElementById("calendar-month-grid");
    const selectorBackButton = document.getElementById("calendar-selector-back");

    if (
        !displayInput ||
        !hiddenInput ||
        !calendar ||
        !openButton ||
        !previousButton ||
        !nextButton ||
        !todayButton ||
        !monthYear ||
        !daysContainer ||
        !calendarView ||
        !calendarSelector ||
        !selectorYear ||
        !previousYearButton ||
        !nextYearButton ||
        !monthGrid ||
        !selectorBackButton
    ) {
        console.log("Calendar elements not found.");
        return;
    }

    let currentDate = new Date();
    let selectedDate = null;
    let selectorYearValue = currentDate.getFullYear();

    const monthNames = [
        "Jan", "Feb", "Mar", "Apr", "May", "Jun",
        "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
    ];


    // =====================================================
    // FORMAT DATABASE DATE
    // =====================================================

    function formatDatabaseDate(date) {

        const year = date.getFullYear();

        const month = String(
            date.getMonth() + 1
        ).padStart(2, "0");

        const day = String(
            date.getDate()
        ).padStart(2, "0");

        return year + "-" + month + "-" + day;
    }


    // =====================================================
    // FORMAT DISPLAY DATE
    // =====================================================

    function formatDisplayDate(date) {

        return date.toLocaleDateString(
            "en-US",
            {
                month: "long",
                day: "numeric",
                year: "numeric"
            }
        );
    }


    // =====================================================
    // SELECT DATE
    // =====================================================

    function selectDate(date) {

        selectedDate = new Date(
            date.getFullYear(),
            date.getMonth(),
            date.getDate()
        );

        displayInput.value =
            formatDisplayDate(selectedDate);

        hiddenInput.value =
            formatDatabaseDate(selectedDate);

        calendar.classList.remove("active");

        renderCalendar();
    }


    // =====================================================
    // RENDER CALENDAR
    // =====================================================

    function renderCalendar() {

        const year =
            currentDate.getFullYear();

        const month =
            currentDate.getMonth();


        monthYear.textContent =
            currentDate.toLocaleDateString(
                "en-US",
                {
                    month: "long",
                    year: "numeric"
                }
            );


        daysContainer.innerHTML = "";


        const firstDay =
            new Date(
                year,
                month,
                1
            ).getDay();


        const daysInMonth =
            new Date(
                year,
                month + 1,
                0
            ).getDate();


        // =================================================
        // EMPTY DAYS BEFORE MONTH START
        // =================================================

        for (
            let i = 0;
            i < firstDay;
            i++
        ) {

            const emptyButton =
                document.createElement("div");

            emptyButton.className =
                "calendar-empty";

            daysContainer.appendChild(
                emptyButton
            );
        }


        // =================================================
        // MONTH DAYS
        // =================================================

        const today =
            new Date();


        for (
            let day = 1;
            day <= daysInMonth;
            day++
        ) {

            const button =
                document.createElement("button");


            button.type = "button";

            button.textContent = day;


            const date =
                new Date(
                    year,
                    month,
                    day
                );


            // TODAY

            if (
                date.getFullYear() === today.getFullYear() &&
                date.getMonth() === today.getMonth() &&
                date.getDate() === today.getDate()
            ) {

                button.classList.add("today");
            }


            // SELECTED

            if (
                selectedDate &&
                date.getFullYear() === selectedDate.getFullYear() &&
                date.getMonth() === selectedDate.getMonth() &&
                date.getDate() === selectedDate.getDate()
            ) {

                button.classList.add("selected");
            }


            // CLICK

            button.addEventListener(
                "click",
                function (event) {

                    event.preventDefault();

                    event.stopPropagation();

                    selectDate(date);
                }
            );


            daysContainer.appendChild(
                button
            );
        }
    }


    // =====================================================
    // MONTH / YEAR SELECTOR
    // =====================================================

    function renderMonthSelector() {

        selectorYear.textContent = selectorYearValue;
        monthGrid.innerHTML = "";

        monthNames.forEach(function (name, monthIndex) {

            const button = document.createElement("button");

            button.type = "button";
            button.textContent = name;

            if (
                currentDate.getFullYear() === selectorYearValue &&
                currentDate.getMonth() === monthIndex
            ) {
                button.classList.add("selected");
            }

            button.addEventListener("click", function (event) {

                event.preventDefault();
                event.stopPropagation();

                currentDate = new Date(selectorYearValue, monthIndex, 1);
                calendarSelector.classList.remove("active");
                calendarView.classList.add("active");
                renderCalendar();
            });

            monthGrid.appendChild(button);
        });
    }


    function openMonthSelector(event) {

        event.preventDefault();
        event.stopPropagation();

        selectorYearValue = currentDate.getFullYear();
        calendarView.classList.remove("active");
        calendarSelector.classList.add("active");
        renderMonthSelector();
    }


    // =====================================================
    // OPEN CALENDAR
    // =====================================================

    function openCalendar(event) {

        if (event) {

            event.preventDefault();

            event.stopPropagation();
        }

        calendarSelector.classList.remove("active");
        calendarView.classList.add("active");
        calendar.classList.add("active");

        renderCalendar();
    }


    // =====================================================
    // OPEN BUTTON
    // =====================================================

    openButton.addEventListener(
        "click",
        openCalendar
    );


    // =====================================================
    // INPUT
    // =====================================================

    displayInput.addEventListener(
        "click",
        openCalendar
    );


    // =====================================================
    // PREVIOUS MONTH
    // =====================================================

    previousButton.addEventListener(
        "click",
        function (event) {

            event.preventDefault();

            event.stopPropagation();

            currentDate = new Date(
                currentDate.getFullYear(),
                currentDate.getMonth() - 1,
                1
            );

            renderCalendar();
        }
    );


    // =====================================================
    // NEXT MONTH
    // =====================================================

    nextButton.addEventListener(
        "click",
        function (event) {

            event.preventDefault();

            event.stopPropagation();

            currentDate = new Date(
                currentDate.getFullYear(),
                currentDate.getMonth() + 1,
                1
            );

            renderCalendar();
        }
    );


    monthYear.addEventListener(
        "click",
        openMonthSelector
    );


    previousYearButton.addEventListener(
        "click",
        function (event) {

            event.preventDefault();
            event.stopPropagation();

            selectorYearValue -= 1;
            renderMonthSelector();
        }
    );


    nextYearButton.addEventListener(
        "click",
        function (event) {

            event.preventDefault();
            event.stopPropagation();

            selectorYearValue += 1;
            renderMonthSelector();
        }
    );


    selectorBackButton.addEventListener(
        "click",
        function (event) {

            event.preventDefault();
            event.stopPropagation();

            calendarSelector.classList.remove("active");
            calendarView.classList.add("active");
        }
    );


    // =====================================================
    // TODAY
    // =====================================================

    todayButton.addEventListener(
        "click",
        function (event) {

            event.preventDefault();

            event.stopPropagation();


            const today =
                new Date();


            currentDate =
                new Date(today);


            selectDate(today);
        }
    );


    // =====================================================
    // CLOSE OUTSIDE
    // =====================================================

    document.addEventListener(
        "click",
        function (event) {

            if (
                !event.target.closest(
                    ".date-picker-wrapper"
                )
            ) {

                calendar.classList.remove(
                    "active"
                );
            }
        }
    );


    // =====================================================
    // INITIAL RENDER
    // =====================================================

    renderCalendar();

});


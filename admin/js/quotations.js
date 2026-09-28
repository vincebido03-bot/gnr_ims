document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector(".quotation-form");
    const itemRows = document.getElementById("quotation-item-rows");
    const itemTemplate = document.getElementById("quotation-item-template");
    const addItemButton = document.getElementById("add-quotation-item");
    const subtotalInput = document.getElementById("subtotal");
    const discountInput = document.getElementById("discount");
    const totalInput = document.getElementById("total_amount");

    if (!form || !itemRows || !itemTemplate || !addItemButton || !subtotalInput || !discountInput || !totalInput) {
        return;
    }

    function updateTotals() {
        let subtotal = 0;

        itemRows.querySelectorAll(".quotation-item-row").forEach(function (row) {
            const quantity = Number(row.querySelector('[name="item_quantity[]"]').value) || 0;
            const unitPrice = Number(row.querySelector('[name="item_unit_price[]"]').value) || 0;
            const amount = Math.round((quantity * unitPrice + Number.EPSILON) * 100) / 100;
            subtotal += amount;
            row.querySelector(".quotation-item-amount").textContent = "PHP " + amount.toFixed(2);
        });

        subtotal = Math.round((subtotal + Number.EPSILON) * 100) / 100;
        const discount = Number(discountInput.value) || 0;
        subtotalInput.value = subtotal.toFixed(2);
        totalInput.value = Math.max(0, subtotal - discount).toFixed(2);
    }

    addItemButton.addEventListener("click", function () {
        itemRows.appendChild(itemTemplate.content.cloneNode(true));
    });

    itemRows.addEventListener("click", function (event) {
        const removeButton = event.target.closest(".remove-quotation-item");
        if (!removeButton) {
            return;
        }

        const row = removeButton.closest(".quotation-item-row");
        if (itemRows.querySelectorAll(".quotation-item-row").length === 1) {
            row.querySelector('[name="item_description[]"]').value = "";
            row.querySelector('[name="item_quantity[]"]').value = "1";
            row.querySelector('[name="item_unit_price[]"]').value = "";
            row.querySelector('[name="item_type[]"]').value = "SERVICE";
        } else {
            row.remove();
        }
        updateTotals();
    });

    form.addEventListener("input", updateTotals);
    form.addEventListener("change", updateTotals);
    updateTotals();
});
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("trusted-form"); // Corrected form ID
    const contactsList = document.getElementById("trusted-contacts-list"); // Matches HTML
    const noContactsText = document.querySelector(".trusted-no-contacts");

    // Add contact
    form.addEventListener("submit", (e) => {
        e.preventDefault();

        // Get input values
        const name = document.getElementById("trusted-name").value.trim();
        const phone = document.getElementById("trusted-phone").value.trim();

        // Validate inputs
        if (!name || !phone) {
            alert("Please fill out both fields.");
            return;
        }

        // Create contact item
        const contactItem = document.createElement("div");
        contactItem.classList.add("trusted-contact-item");
        contactItem.innerHTML = `
            <span>${name} - ${phone}</span>
            <button class="remove-btn">Remove</button>
        `;

        // Append to list
        contactsList.appendChild(contactItem);

        // Remove "no contacts" text
        noContactsText.style.display = "none";

        // Clear form fields
        form.reset();
    });

    // Remove contact
    contactsList.addEventListener("click", (e) => {
        if (e.target.classList.contains("remove-btn")) {
            e.target.parentElement.remove();

            // Show "no contacts" text if list is empty
            if (contactsList.children.length === 0) {
                noContactsText.style.display = "block";
            }
        }
    });
});

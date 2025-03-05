document.addEventListener("DOMContentLoaded", () => {
    const applyButtons = document.querySelectorAll(".apply-btn");

    applyButtons.forEach((button) => {
        button.addEventListener("click", () => {
            button.textContent = "Applied";
            button.style.backgroundColor = "#888"; // Change color to indicate applied status
            button.style.cursor = "not-allowed"; // Change cursor to indicate non-clickable
            button.disabled = true; // Disable the button
        });
    });
});

document.addEventListener("DOMContentLoaded", () => {
    const filterSelect = document.getElementById("filter-select");
    const resourceCards = document.querySelectorAll(".resource-card");

    filterSelect.addEventListener("change", () => {
        const selectedCategory = filterSelect.value;

        resourceCards.forEach((card) => {
            if (selectedCategory === "all" || card.getAttribute("data-category") === selectedCategory) {
                card.style.display = "block";
            } else {
                card.style.display = "none";
            }
        });
    });
});

document.addEventListener("DOMContentLoaded", () => {
    const filterSelect = document.getElementById("filter-select");
    const resourceSections = document.querySelectorAll(".resource-section");

    filterSelect.addEventListener("change", () => {
        const selectedCategory = filterSelect.value;

        resourceSections.forEach((section) => {
            const category = section.getAttribute("data-category");
            if (selectedCategory === "all" || selectedCategory === category) {
                section.style.display = "block";
            } else {
                section.style.display = "none";
            }
        });
    });
});

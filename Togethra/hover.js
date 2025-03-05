document.addEventListener("DOMContentLoaded", () => {
    const featureItems = document.querySelectorAll(".feature-item");

    featureItems.forEach((item) => {
        item.addEventListener("mouseenter", () => {
            const description = item.querySelector(".feature-description");
            description.style.opacity = "1";
        });

        item.addEventListener("mouseleave", () => {
            const description = item.querySelector(".feature-description");
            description.style.opacity = "0";
        });
    });
});

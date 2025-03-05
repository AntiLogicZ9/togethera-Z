// Add an event listener to the form submission
document.getElementById("loginForm").addEventListener("submit", function (e) {
    e.preventDefault(); // Prevent the form from submitting traditionally

    // Here you can validate the input fields or authenticate the user

    // Redirect to the homepage
    window.location.href = "homepage.html";
});

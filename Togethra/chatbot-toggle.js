// Enhanced Chatbot Toggle Logic

const chatbotToggle = document.getElementById("chatbot-toggle");
const chatbotBubble = document.getElementById("chatbot-bubble");
const closeChatbot = document.getElementById("close-chatbot");

// Function to toggle chatbot visibility
const toggleChatbot = () => {
    chatbotBubble.classList.toggle("hidden");
    chatbotToggle.setAttribute(
        "aria-expanded",
        chatbotBubble.classList.contains("hidden") ? "false" : "true"
    );
};

// Event listener for the toggle button
chatbotToggle.addEventListener("click", toggleChatbot);

// Event listener for the close button
closeChatbot.addEventListener("click", () => {
    chatbotBubble.classList.add("hidden");
    chatbotToggle.setAttribute("aria-expanded", "false");
});

// Close chatbot when clicking outside of it
document.addEventListener("click", (event) => {
    if (!chatbotBubble.contains(event.target) && !chatbotToggle.contains(event.target)) {
        chatbotBubble.classList.add("hidden");
        chatbotToggle.setAttribute("aria-expanded", "false");
    }
});

// Keyboard Accessibility
chatbotToggle.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") {
        toggleChatbot();
    }
});

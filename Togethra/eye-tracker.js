window.onload = async () => {
    const gazePoint = document.getElementById('gaze-point');

    // Initialize WebGazer
    await webgazer.setGazeListener((data, elapsedTime) => {
        if (data) {
            const { x, y } = data; // x and y are gaze coordinates on the screen
            moveGazePoint(x, y);
        }
    }).begin();

    // Move the red dot to the detected gaze point
    function moveGazePoint(x, y) {
        const trackerArea = document.getElementById('tracker-area');
        const rect = trackerArea.getBoundingClientRect();

        // Ensure gaze point is within the tracking area
        if (x > rect.left && x < rect.right && y > rect.top && y < rect.bottom) {
            gazePoint.style.left = `${x - rect.left}px`;
            gazePoint.style.top = `${y - rect.top}px`;
        }
    }

    // Optional: Show webcam feed for calibration
    webgazer.showVideoPreview(true).showPredictionPoints(true);

    // Calibration settings (Optional)
    webgazer.setRegression('ridge'); // Default regression model
    webgazer.setTracker('clmtrackr'); // Face tracking model
};

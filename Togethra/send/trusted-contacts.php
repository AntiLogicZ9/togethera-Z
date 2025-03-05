<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Error: You must log in to access this page.");
}

$user_id = $_SESSION['user_id']; // Get logged-in user's ID

// Database connection
$conn = new mysqli("localhost", "root", "", "togetheraplus");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize response messages
$error_message = "";
$success_message = "";

// Handle adding a trusted contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = $conn->real_escape_string($_POST['name']);
    $phone_number = $conn->real_escape_string($_POST['phone_number']);
    $relationship = $conn->real_escape_string($_POST['relationship']);

    $sql = "INSERT INTO trusted_contacts (user_id, name, phone_number, relationship) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $user_id, $name, $phone_number, $relationship);

    if ($stmt->execute()) {
        $success_message = "Trusted contact added successfully.";
    } else {
        $error_message = "Error adding trusted contact: " . $stmt->error;
    }
    $stmt->close();
}

// Handle deleting a trusted contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $contact_id = (int)$_POST['contact_id'];

    $sql = "DELETE FROM trusted_contacts WHERE contact_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $contact_id, $user_id);

    if ($stmt->execute()) {
        $success_message = "Trusted contact deleted successfully.";
    } else {
        $error_message = "Error deleting trusted contact: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch all trusted contacts for the logged-in user
$sql = "SELECT * FROM trusted_contacts WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$trusted_contacts = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trusted Contacts</title>
    <link rel="stylesheet" href="trusted-contacts.css">
</head>

  <!-- Header -->
  <header>
        <div class="logo">
            <img src="img/logo.png" alt="TogetherA+">
        </div>
        <nav>
            <ul>
                <li><a href="homepage.html">Home</a></li>
                <li><a href="about.html">About Us</a></li>
                <li><a href="tasks.html">Tasks</a></li>
                <li><a href="resources.html">Resources</a></li>
                <li><a href="profile.html">Profile</a></li>
                <li><a href="trusted-contacts.html" class="active">Trusted Contacts</a></li>
                <li><a href="contact.html">Contact</a></li>
            </ul>
        </nav>
    </header>

<body>
    <div class="trusted-container">
        <h1>Trusted Contacts</h1>

        <!-- Display success or error messages -->
        <?php if (!empty($error_message)): ?>
            <div class="error-message" style="color: red;">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="success-message" style="color: green;">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <!-- Add Trusted Contact Form -->
        <form method="POST" action="trusted-contacts.php">
            <input type="hidden" name="action" value="add">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name" required><br>

            <label for="phone_number">Phone Number:</label>
            <input type="tel" id="phone_number" name="phone_number" required><br>

            <label for="relationship">Relationship:</label>
            <input type="text" id="relationship" name="relationship"><br>

            <button type="submit">Add Contact</button>
        </form>

        <!-- Display List of Trusted Contacts -->
        <h2>Your Trusted Contacts</h2>
        <ul>
            <?php if (empty($trusted_contacts)): ?>
                <li>No trusted contacts found.</li>
            <?php else: ?>
                <?php foreach ($trusted_contacts as $contact): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($contact['name']); ?></strong> - 
                        <?php echo htmlspecialchars($contact['phone_number']); ?> 
                        (<?php echo htmlspecialchars($contact['relationship']); ?>)
                        <form method="POST" action="trusted-contacts.php" style="display: inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="contact_id" value="<?php echo $contact['contact_id']; ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</body>
</html>

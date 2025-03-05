<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "togetheraplus");

/// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and validate user input
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $user_type = trim($_POST['user_type']);

    // Validate required fields
    if (empty($name) || empty($email) || empty($password) || empty($user_type)) {
        die("Please fill in all required fields.");
    }

    // Validate user type
    $allowed_user_types = ['disabled_individual', 'family_member', 'caretaker', 'admin'];
    if (!in_array($user_type, $allowed_user_types)) {
        die("Invalid user type!");
    }

    // Check for duplicate email
    $email_check = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $email_check->bind_param("s", $email);
    $email_check->execute();
    $email_check->store_result();

    if ($email_check->num_rows > 0) {
        $error_message = "Email already exists!";
    } else {
        // Hash the password securely
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user data
    $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, phone_number, address, user_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $name, $email, $password_hash, $phone_number, $address, $user_type);
    

    if ($stmt->execute()) {
        // Redirect to homepage after successful registration
        header("Location: login.php");
        exit();
    } else {
        $error_message = "Error: " . $stmt->error;
    }
    $stmt->close();
}
$email_check->close();
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TogetherA+</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="login.css"> <!-- You can use login.css for the registration page as well -->
</head>

<body>
    <div class="container">
        <h1>Register</h1>
        <!-- Display error message -->
        <?php if (!empty($error_message)) : ?>
            <div class="error-message" style="color: red; margin-bottom: 15px;">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">

    <!-- Name Field -->
    <div class="form-group">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" placeholder="Enter your full name" required />
    </div>

    <!-- Email Field -->
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="Enter your email" required />
    </div>

    <!-- Password Field -->
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" required />
    </div>

    <!-- Phone Number Field -->
    <label for="phone_number">Phone Number:</label>
    <input type="text" id="phone_number" name="phone_number" required><br><br>

    <!-- Address Field -->
    <label for="address">Address:</label>
    <textarea id="address" name="address" required></textarea><br><br>

    <!-- User Type -->
    <label for="user_type">User Type:</label>
    <select id="user_type" name="user_type" required>
        <option value="disabled_individual">Disabled Individual</option>
        <option value="family_member">Family Member</option>
        <option value="caretaker">Caretaker</option>
        <option value="admin">Admin</option>
    </select><br><br>

    <!-- Submit Button -->
    <button type="submit">Register</button>
</form>

        <div class="link">
            <p>Already have an account? <a href="login.php">Login</a></p>
        </div>
    </div>
</body>
</html>



<?php
/***********************************************
 * task_management.php
 ***********************************************/

/** 1) Database Connection **/
$host     = 'localhost';
$db       = 'togetheraplus';
$user     = 'root';
$password = '';

$conn = new mysqli("localhost", "root", "", "togetheraplus");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Get all users for the dropdown
$users_query = "SELECT user_id, name FROM users ORDER BY name";
$users_result = $conn->query($users_query);
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Get all helpers for the dropdown
$helpers_query = "SELECT helper_id, name FROM helpers ORDER BY name";
$helpers_result = $conn->query($helpers_query);
$helpers = [];
while ($row = $helpers_result->fetch_assoc()) {
    $helpers[] = $row;
}

// Get all tasks for the dropdown
$tasks_query = "SELECT task_id, title FROM tasks ORDER BY task_id DESC";
$tasks_result = $conn->query($tasks_query);
$tasks = [];
while ($row = $tasks_result->fetch_assoc()) {
    $tasks[] = $row;
}

/***********************************************
 * 2. Process Form Submissions
 ***********************************************/
$message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // (A) CREATE A NEW TASK
    if (isset($_POST['create_task'])) {
        $user_id     = (int)$_POST['user_id'];
        $title       = $_POST['title'];
        $description = $_POST['description'];
        $location    = $_POST['location'];
        $urgency     = $_POST['urgency'];
        $hourly_rate = (float)$_POST['hourly_rate'];

        // Validate user_id exists
        $user_check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $user_check->bind_param('i', $user_id);
        $user_check->execute();
        $user_result = $user_check->get_result();

        if ($user_result->num_rows === 0) {
            $message = "Error: Selected user does not exist.";
        } else {
            $sql = "
                INSERT INTO tasks (user_id, title, description, location, urgency, hourly_rate, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssd", $user_id, $title, $description, $location, $urgency, $hourly_rate);

            if ($stmt->execute()) {
                $message = "Task request created successfully!";
            } else {
                $message = "Error creating task: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    // (B) MATCH REQUESTS WITH HELPERS
    if (isset($_POST['match_helpers'])) {
        $task_id  = (int)$_POST['task_id_match'];
        $skill    = $_POST['skill'];
        $location = $_POST['location_match'];

        $sql = "
            SELECT helpers.helper_id,
                   helpers.name,
                   helpers.skills,
                   helpers.address,
                   helpers.rating
            FROM helpers
            WHERE helpers.skills  LIKE CONCAT('%', ?, '%')
              AND helpers.address LIKE CONCAT('%', ?, '%')
              AND helpers.helper_id NOT IN (
                  SELECT helper_id FROM hired WHERE task_id = ?
              )
            ORDER BY helpers.rating DESC
            LIMIT 3
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $skill, $location, $task_id);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $helpers = [];
            while ($row = $result->fetch_assoc()) {
                $helpers[] = $row;
            }

            if (!empty($helpers)) {
                // Display matched helpers
                $message = "Matched Helpers (Top 3 by rating):<br>";
                foreach ($helpers as $helper) {
                    $message .= "Helper ID: {$helper['helper_id']}, "
                              . "Name: {$helper['name']}, "
                              . "Skills: {$helper['skills']}, "
                              . "Location: {$helper['address']}, "
                              . "Rating: {$helper['rating']}<br>";
                }
            } else {
                $message = "No helpers matched your criteria.";
            }
        } else {
            $message = "Error matching helpers: " . $stmt->error;
        }
        $stmt->close();
    }

    // (C) TASK STATUS UPDATES & HELPER ASSIGNMENT
    if (isset($_POST['update_task_status'])) {
        $task_id   = (int)$_POST['task_id_status'];
        $status    = $_POST['status'];
        $helper_id = isset($_POST['helper_id_assign']) ? (int)$_POST['helper_id_assign'] : null;
        $user_id   = isset($_POST['user_id_assign'])   ? (int)$_POST['user_id_assign']   : null;

        // Update task status in tasks table
        $sql_update = "UPDATE tasks SET status = ? WHERE task_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $status, $task_id);

        if ($stmt_update->execute()) {

            // If setting status to 'in_progress', we can assign a helper
            if ($status === "in_progress" && $helper_id && $user_id) {
                $sql_hired = "
                    INSERT INTO hired (task_id, user_id, helper_id, start_time)
                    VALUES (?, ?, ?, NOW())
                ";
                $stmt_hired = $conn->prepare($sql_hired);
                $stmt_hired->bind_param("iii", $task_id, $user_id, $helper_id);

                if ($stmt_hired->execute()) {
                    $message = "Task status updated to 'in_progress' and helper assigned!";
                } else {
                    $message = "Error in hiring helper: " . $stmt_hired->error;
                }
                $stmt_hired->close();
            } else {
                $message = "Task status updated!";
            }
        } else {
            $message = "Error updating task status: " . $stmt_update->error;
        }
        $stmt_update->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Task Request & Matching</title>
    <style>
        body {
            font-family: Arial, sans-serif; 
            margin: 20px;
            line-height: 1.6;
        }
        fieldset {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        legend {
            font-weight: bold;
            padding: 0 10px;
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .optional-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
        }
    </style>
</head>
<body>

    <h1>Task Request & Matching</h1>

    <?php if ($message): ?>
        <div class="message <?= strpos($message, 'Error') !== false ? 'error' : 'success' ?>">
            <?= nl2br(htmlspecialchars($message)) ?>
        </div>
    <?php endif; ?>

    <!-- (A) CREATE A NEW TASK FORM -->
    <fieldset>
        <legend>Create a New Task</legend>
        <form method="post">
            <label for="user_id">Select User:</label>
            <select name="user_id" id="user_id" required>
                <option value="">-- Select User --</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= htmlspecialchars($user['user_id']) ?>">
                        <?= htmlspecialchars($user['name']) ?> (ID: <?= htmlspecialchars($user['user_id']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="title">Title:</label>
            <input type="text" name="title" id="title" required>

            <label for="description">Description:</label>
            <textarea name="description" id="description" rows="4" required></textarea>

            <label for="location">Location:</label>
            <input type="text" name="location" id="location" required>

            <label for="urgency">Urgency:</label>
            <select name="urgency" id="urgency" required>
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
            </select>

            <label for="hourly_rate">Hourly Rate:</label>
            <input type="number" step="0.01" name="hourly_rate" id="hourly_rate" required>

            <button type="submit" name="create_task">Create Task</button>
        </form>
    </fieldset>

    <!-- (B) MATCH REQUESTS WITH HELPERS FORM -->
    <fieldset>
        <legend>Match Helpers to a Task</legend>
        <form method="post">
            <label for="task_id_match">Select Task:</label>
            <select name="task_id_match" id="task_id_match" required>
                <option value="">-- Select Task --</option>
                <?php foreach ($tasks as $task): ?>
                    <option value="<?= htmlspecialchars($task['task_id']) ?>">
                        <?= htmlspecialchars($task['title']) ?> (ID: <?= htmlspecialchars($task['task_id']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="skill">Required Skill (keyword):</label>
            <input type="text" name="skill" id="skill" required>

            <label for="location_match">Location (keyword):</label>
            <input type="text" name="location_match" id="location_match" required>

            <button type="submit" name="match_helpers">Find Helpers</button>
        </form>
    </fieldset>

    <!-- (C) UPDATE TASK STATUS & ASSIGN HELPER -->
    <fieldset>
        <legend>Update Task Status & Assign Helper</legend>
        <form method="post">
            <label for="task_id_status">Select Task:</label>
            <select name="task_id_status" id="task_id_status" required>
                <option value="">-- Select Task --</option>
                <?php foreach ($tasks as $task): ?>
                    <option value="<?= htmlspecialchars($task['task_id']) ?>">
                        <?= htmlspecialchars($task['title']) ?> (ID: <?= htmlspecialchars($task['task_id']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="status">New Status:</label>
            <select name="status" id="status" required>
                <option value="pending">Pending</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="canceled">Canceled</option>
            </select>

            <div class="optional-section">
                <em>Optional (for assigning helper when task status is set to "in_progress"):</em>
                
                <label for="helper_id_assign">Select Helper:</label>
                <select name="helper_id_assign" id="helper_id_assign">
                    <option value="">-- Select Helper --</option>
                    <?php foreach ($helpers as $helper): ?>
                        <option value="<?= htmlspecialchars($helper['helper_id']) ?>">
                            <?= htmlspecialchars($helper['name']) ?> (ID: <?= htmlspecialchars($helper['helper_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="user_id_assign">Select User:</label>
                <select name="user_id_assign" id="user_id_assign">
                    <option value="">-- Select User --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= htmlspecialchars($user['user_id']) ?>">
                            <?= htmlspecialchars($user['name']) ?> (ID: <?= htmlspecialchars($user['user_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" name="update_task_status">Update Task</button>
        </form>
    </fieldset>

</body>
</html>
<?php
/*****************************************
 *  manage_hiring_records.php
 *****************************************/

/** 1) Database Connection **/
$host     = 'localhost';
$db       = 'togetheraplus';
$user     = 'root';
$password = '';

$conn = new mysqli("localhost", "root", "", "togetheraplus");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

/*****************************************
 * 2) Define Helper Functions
 *****************************************/

// Function to validate IDs exist in their respective tables
function validateIDs($user_id, $helper_id, $task_id, $conn) {
    // Check user exists
    $user_check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $user_check->bind_param('i', $user_id);
    $user_check->execute();
    $user_result = $user_check->get_result();
    if ($user_result->num_rows === 0) {
        return "Error: User ID $user_id does not exist";
    }

    // Check helper exists
    $helper_check = $conn->prepare("SELECT helper_id FROM helpers WHERE helper_id = ?");
    $helper_check->bind_param('i', $helper_id);
    $helper_check->execute();
    $helper_result = $helper_check->get_result();
    if ($helper_result->num_rows === 0) {
        return "Error: Helper ID $helper_id does not exist";
    }

    // Check task exists
    $task_check = $conn->prepare("SELECT task_id FROM tasks WHERE task_id = ?");
    $task_check->bind_param('i', $task_id);
    $task_check->execute();
    $task_result = $task_check->get_result();
    if ($task_result->num_rows === 0) {
        return "Error: Task ID $task_id does not exist";
    }

    return true;
}

// (a) Update Hourly Rate in Hiring Records or Create New
function updateHelperHourlyRate($helper_id, $user_id, $task_id, $hourly_rate, $conn) {
    // First validate all IDs
    $validation_result = validateIDs($user_id, $helper_id, $task_id, $conn);
    if ($validation_result !== true) {
        return $validation_result;
    }

    // First try to update existing pending records
    $update_sql = "
        UPDATE hiring_records 
        SET hourly_rate = ?
        WHERE helper_id = ? AND status = 'pending'
    ";
    $update_stmt = $conn->prepare($update_sql);
    if (!$update_stmt) {
        return "Error preparing update statement: " . $conn->error;
    }
    
    $update_stmt->bind_param('di', $hourly_rate, $helper_id);
    $update_stmt->execute();

    if ($update_stmt->affected_rows > 0) {
        return "Hourly rate updated successfully for pending records.";
    } else {
        // If no records were updated, create a new record
        $insert_sql = "
            INSERT INTO hiring_records (
                helper_id, 
                user_id,
                task_id,
                start_time,
                hourly_rate,
                status
            ) VALUES (?, ?, ?, NOW(), ?, 'pending')
        ";
        
        $insert_stmt = $conn->prepare($insert_sql);
        if (!$insert_stmt) {
            return "Error preparing insert statement: " . $conn->error;
        }
        
        $insert_stmt->bind_param('iiid', $helper_id, $user_id, $task_id, $hourly_rate);
        
        try {
            $insert_stmt->execute();
            if ($insert_stmt->affected_rows > 0) {
                return "New hiring record created with specified hourly rate.";
            } else {
                return "Error creating new hiring record: " . $conn->error;
            }
        } catch (mysqli_sql_exception $e) {
            return "Database error: " . $e->getMessage();
        }
    }
}

// (b) Add End Time (Hours and Cost are calculated automatically by the table)
function logTaskHours($hiring_id, $end_time, $conn) {
    $query = "
        UPDATE hiring_records
        SET end_time = ?,
            status = 'completed'
        WHERE hiring_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $end_time, $hiring_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        return "Hours logged successfully. Cost is automatically calculated.";
    } else {
        return "Error logging hours or no changes made.";
    }
}

// (c) Handle Mutual Confirmation
function confirmHours($hiring_id, $type, $conn) {
    $column = ($type === 'user') ? 'user_confirmation' : 'helper_confirmation';
    $query = "
        UPDATE hiring_records 
        SET $column = 'confirmed'
        WHERE hiring_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $hiring_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Check if both parties have confirmed
        $check_query = "
            SELECT user_confirmation, helper_confirmation 
            FROM hiring_records 
            WHERE hiring_id = ? AND 
                  user_confirmation = 'confirmed' AND 
                  helper_confirmation = 'confirmed'
        ";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('i', $hiring_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Both parties confirmed - update status
            $update_query = "
                UPDATE hiring_records 
                SET status = 'completed' 
                WHERE hiring_id = ?
            ";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param('i', $hiring_id);
            $update_stmt->execute();
            return "Both parties have confirmed. Record marked as completed.";
        }
        return ucfirst($type) . " confirmed hours successfully.";
    } else {
        return "Error confirming hours or no changes made.";
    }
}

// (d) Admin Arbitration
function resolveDispute($hiring_id, $conn) {
    $query = "
        UPDATE hiring_records
        SET admin_decision = 'resolved',
            status = 'completed'
        WHERE hiring_id = ?
          AND status = 'disputed'
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $hiring_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        return "Dispute resolved by admin.";
    } else {
        return "Error resolving dispute or conditions not met.";
    }
}

/*****************************************
 * 3) Process Form Submissions
 *****************************************/
$message = '';

// Get available users, helpers, and tasks for dropdowns
$users = $conn->query("SELECT user_id, name FROM users ORDER BY user_id");
$helpers = $conn->query("SELECT helper_id, name FROM helpers ORDER BY helper_id");
$tasks = $conn->query("SELECT task_id, title FROM tasks ORDER BY task_id");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_hourly_rate'])) {
        $helper_id = (int) $_POST['helper_id'];
        $user_id = (int) $_POST['user_id'];
        $task_id = (int) $_POST['task_id'];
        $hourly_rate = (float) $_POST['hourly_rate'];

        if ($user_id === 0 || $task_id === 0) {
            $message = "Error: User ID and Task ID are required.";
        } else {
            $message = updateHelperHourlyRate($helper_id, $user_id, $task_id, $hourly_rate, $conn);
        }
    }
}

    /*****************************************
     * (b) Log Task Hours
     *****************************************/
    if (isset($_POST['log_hours'])) {
        $hiring_id = (int) $_POST['hiring_id'];
        $end_time = $_POST['end_time'];  // e.g. '2025-01-17 14:00:00'

        $message = logTaskHours($hiring_id, $end_time, $conn);
    }

    /*****************************************
     * (c) Confirm Hours (User or Helper)
     *****************************************/
    if (isset($_POST['confirm_hours'])) {
        $hiring_id = (int) $_POST['hiring_id'];
        $type = $_POST['type']; // 'user' or 'helper'

        $message = confirmHours($hiring_id, $type, $conn);
    }

    /*****************************************
     * (d) Resolve Dispute by Admin
     *****************************************/
    if (isset($_POST['resolve_dispute'])) {
        $hiring_id = (int) $_POST['hiring_id'];

        $message = resolveDispute($hiring_id, $conn);
    }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Hiring Records</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        fieldset {
            margin-bottom: 20px;
            padding: 15px;
        }
        legend {
            font-weight: bold;
        }
        .message {
            margin: 10px 0;
            color: #2e6da4;
        }
        .error {
            color: #dc3545;
        }
        select, input {
            width: 200px;
            padding: 5px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<h1>Manage Hiring Records</h1>

<!-- Display any feedback messages -->
<?php if (!empty($message)) : ?>
    <div class="message <?= strpos($message, 'Error') !== false ? 'error' : '' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- (a) Update Hourly Rate -->
<fieldset>
    <legend>Update Helper Hourly Rate</legend>
    <form method="post">
        <label for="helper_id">Helper:</label>
        <select name="helper_id" id="helper_id" required>
            <option value="">Select Helper</option>
            <?php while ($helper = $helpers->fetch_assoc()): ?>
                <option value="<?= $helper['helper_id'] ?>">
                    <?= htmlspecialchars($helper['helper_id'] . ' - ' . $helper['name']) ?>
                </option>
            <?php endwhile; ?>
        </select><br>

        <label for="user_id">User:</label>
        <select name="user_id" id="user_id" required>
            <option value="">Select User</option>
            <?php while ($user = $users->fetch_assoc()): ?>
                <option value="<?= $user['user_id'] ?>">
                    <?= htmlspecialchars($user['user_id'] . ' - ' . $user['name']) ?>
                </option>
            <?php endwhile; ?>
        </select><br>

        <label for="task_id">Task:</label>
        <select name="task_id" id="task_id" required>
            <option value="">Select Task</option>
            <?php while ($task = $tasks->fetch_assoc()): ?>
                <option value="<?= $task['task_id'] ?>">
                    <?= htmlspecialchars($task['task_id'] . ' - ' . $task['title']) ?>
                </option>
            <?php endwhile; ?>
        </select><br>

        <label for="hourly_rate">Hourly Rate:</label>
        <input type="number" step="0.01" name="hourly_rate" id="hourly_rate" required><br><br>

        <button type="submit" name="update_hourly_rate">Update Rate</button>
    </form>
</fieldset>
<!-- (b) Log Task Hours -->
<fieldset>
    <legend>Log Task End Time</legend>
    <form method="post">
        <label for="hiring_id">Hiring ID:</label>
        <input type="number" name="hiring_id" id="hiring_id" required><br><br>

        <label for="end_time">End Time (YYYY-MM-DD HH:MM:SS):</label>
        <input type="text" name="end_time" id="end_time" placeholder="2025-01-17 14:00:00" required><br><br>

        <button type="submit" name="log_hours">Log Hours</button>
    </form>
</fieldset>

<!-- (c) Handle Mutual Confirmation -->
<fieldset>
    <legend>Confirm Hours (User or Helper)</legend>
    <form method="post">
        <label for="hiring_id_confirm">Hiring ID:</label>
        <input type="number" name="hiring_id" id="hiring_id_confirm" required><br><br>

        <label for="type">Confirmation Type:</label>
        <select name="type" id="type" required>
            <option value="">--Select--</option>
            <option value="user">User</option>
            <option value="helper">Helper</option>
        </select><br><br>

        <button type="submit" name="confirm_hours">Confirm</button>
    </form>
</fieldset>

<!-- (d) Admin Arbitration -->
<fieldset>
    <legend>Resolve Dispute (Admin)</legend>
    <form method="post">
        <label for="hiring_id_dispute">Hiring ID:</label>
        <input type="number" name="hiring_id" id="hiring_id_dispute" required><br><br>

        <button type="submit" name="resolve_dispute">Resolve Dispute</button>
    </form>
</fieldset>

</body>
</html>
<?php
/******************************************************
 * resource_repository.php
 * 
 * Demonstrates:
 * 1) Requesting a Resource
 * 2) Fulfilling (Uploading) a Resource with File Upload
 * 3) Marking Request as Fulfilled
 * 4) Listing & Downloading Resources
 * 5) Rating & Reviewing Resources
 ******************************************************/

/** 1) Database Connection **/
$host     = 'localhost';
$db       = 'togetheraplus';
$user     = 'root';
$password = '';

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

/**
 * 2. Fetch necessary data for dropdowns (Users, Tasks, etc.)
 *    Adjust queries to your real data structure as needed.
 */

// Get all users
$users_query = "SELECT user_id, name FROM users ORDER BY name";
$users_result = $conn->query($users_query);
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Get all tasks
$tasks_query = "SELECT task_id, title FROM tasks ORDER BY task_id DESC";
$tasks_result = $conn->query($tasks_query);
$tasks = [];
while ($row = $tasks_result->fetch_assoc()) {
    $tasks[] = $row;
}

// Get all resources (to list them, or for rating)
$resources_query = "SELECT resource_id, name FROM resources ORDER BY resource_id DESC";
$resources_result = $conn->query($resources_query);
$resourcesList = [];
while ($row = $resources_result->fetch_assoc()) {
    $resourcesList[] = $row;
}

/**
 * 3. Process Form Submissions
 */
$message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // (A) REQUEST A RESOURCE
    if (isset($_POST['request_resource'])) {
        $user_id       = (int) $_POST['user_id'];
        $task_id       = (int) $_POST['task_id'];
        $resource_type = $_POST['resource_type'];  // e.g. 'audiobook'
        $description   = $_POST['description'];

        // Insert into resource_requests
        $sql = "
            INSERT INTO resource_requests (user_id, task_id, resource_type, description)
            VALUES (?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $user_id, $task_id, $resource_type, $description);

        if ($stmt->execute()) {
            $message = "Resource request submitted successfully!";
        } else {
            $message = "Error requesting resource: " . $stmt->error;
        }
        $stmt->close();
    }

    // (B) FULFILL RESOURCE REQUEST (UPLOAD RESOURCE)
    if (isset($_POST['fulfill_resource'])) {
        // Gather basic form inputs
        $name        = $_POST['name'];
        $category    = $_POST['category'];   // 'audiobook', 'video', 'tutorial'
        $description = $_POST['description'];
        $uploaded_by = (int) $_POST['uploaded_by'];
        $task_id     = empty($_POST['task_id_for_upload']) ? null : (int) $_POST['task_id_for_upload'];

        // The user can optionally specify a 'type' (e.g., pdf, mp4)
        $type = !empty($_POST['type']) ? $_POST['type'] : '';

        // Handle the file upload
        if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath  = $_FILES['resource_file']['tmp_name'];
            $fileName     = $_FILES['resource_file']['name'];
            $fileSize     = $_FILES['resource_file']['size'];
            $fileTypeMime = $_FILES['resource_file']['type'];

            // Derive the extension
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            // If $type wasn't set by the user, use the extension
            if (empty($type)) {
                $type = $fileExtension;
            }

            // Construct a unique filename (avoid collisions)
            $newFileName = time() . '_' . $fileName;

            // Define the upload folder
            $uploadFileDir = 'uploads/';
            $dest_path = $uploadFileDir . $newFileName;

            // Move uploaded file to uploads/ folder
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // We'll store the server path in the 'link' field of resources
                $link = $dest_path;

                // Insert resource record
                $sql = "
                    INSERT INTO resources (name, type, category, description, link, uploaded_by, task_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssis", $name, $type, $category, $description, $link, $uploaded_by, $task_id);

                if ($stmt->execute()) {
                    // Mark the request as fulfilled if there's a matching task_id
                    if ($task_id) {
                        $update_sql = "UPDATE resource_requests SET status = 'fulfilled' WHERE task_id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("i", $task_id);
                        $update_stmt->execute();
                    }

                    $message = "Resource uploaded and request marked as fulfilled!";
                } else {
                    $message = "Error uploading resource: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $message = "Error moving the uploaded file to the destination folder.";
            }
        } else {
            $message = "No file uploaded or an upload error occurred.";
        }
    }

    // (C) RATE & REVIEW A RESOURCE
    if (isset($_POST['review_resource'])) {
        $resource_id = (int) $_POST['resource_id_for_review'];
        $user_id     = (int) $_POST['user_id_for_review'];
        $rating      = (int) $_POST['rating'];
        $comment     = $_POST['comment'];

        $sql = "
            INSERT INTO resource_reviews (resource_id, user_id, rating, comment)
            VALUES (?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiis", $resource_id, $user_id, $rating, $comment);

        if ($stmt->execute()) {
            $message = "Your review has been submitted!";
        } else {
            $message = "Error reviewing resource: " . $stmt->error;
        }
        $stmt->close();
    }
}

/**
 * 4. Fetch All Resources for Listing & Potential Download
 *    (We'll keep it simple: each resource has a `link` 
 *     that you can click to download or view.)
 */
$all_resources_sql = "
    SELECT r.resource_id, r.name, r.type, r.category,
           r.description, r.link, r.uploaded_by,
           u.name AS uploaded_by_name,
           r.task_id, r.created_at
    FROM resources r
    JOIN users u ON r.uploaded_by = u.user_id
    ORDER BY r.resource_id DESC
";
$all_resources_result = $conn->query($all_resources_sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resource Repository</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        fieldset {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        legend {
            font-weight: bold;
            padding: 0 10px;
        }
        .message {
            margin: 15px 0;
            padding: 10px;
            border-radius: 4px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }
        select, input[type="text"], input[type="number"], textarea, input[type="file"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            margin-bottom: 15px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        button {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        hr {
            margin: 25px 0;
        }
        .resource-listing {
            margin: 20px 0;
        }
        .resource-item {
            border: 1px solid #eee;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .resource-item h4 {
            margin: 0;
        }
        .review-section {
            margin-top: 10px;
        }
    </style>
</head>
<body>

    <h1>Resource Repository</h1>

    <?php if (!empty($message)): ?>
        <div class="message <?= (stripos($message, 'error') !== false) ? 'error' : 'success' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- (A) REQUEST A RESOURCE -->
    <fieldset>
        <legend>Request a Resource</legend>
        <form method="post">
            <label for="user_id">User:</label>
            <select name="user_id" id="user_id" required>
                <option value="">-- Select User --</option>
                <?php foreach ($users as $usr) : ?>
                    <option value="<?= $usr['user_id'] ?>">
                        <?= htmlspecialchars($usr['name']) ?> (ID: <?= $usr['user_id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="task_id">Related Task:</label>
            <select name="task_id" id="task_id">
                <option value="">-- Select Task (if any) --</option>
                <?php foreach ($tasks as $tsk) : ?>
                    <option value="<?= $tsk['task_id'] ?>">
                        <?= htmlspecialchars($tsk['title']) ?> (ID: <?= $tsk['task_id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="resource_type">Resource Type:</label>
            <select name="resource_type" id="resource_type" required>
                <option value="audiobook">Audiobook</option>
                <option value="video">Video</option>
                <option value="tutorial">Tutorial</option>
            </select>

            <label for="description">Description / Reason for Request:</label>
            <textarea name="description" id="description" rows="3" required></textarea>

            <button type="submit" name="request_resource">Submit Request</button>
        </form>
    </fieldset>

    <!-- (B) FULFILL RESOURCE REQUEST (UPLOAD RESOURCE) -->
    <fieldset>
        <legend>Fulfill a Resource Request (Upload)</legend>
        <!-- Note the enctype for file upload -->
        <form method="post" enctype="multipart/form-data">
            <label for="task_id_for_upload">Task ID (Optional):</label>
            <select name="task_id_for_upload" id="task_id_for_upload">
                <option value="">-- Select Task --</option>
                <?php foreach ($tasks as $tsk) : ?>
                    <option value="<?= $tsk['task_id'] ?>">
                        <?= htmlspecialchars($tsk['title']) ?> (ID: <?= $tsk['task_id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="name">Resource Name:</label>
            <input type="text" name="name" id="name" required>

            <label for="category">Category:</label>
            <select name="category" id="category" required>
                <option value="audiobook">Audiobook</option>
                <option value="video">Video</option>
                <option value="tutorial">Tutorial</option>
            </select>

            <label for="description">Description:</label>
            <textarea name="description" id="description" rows="3" required></textarea>

            <!-- File input replaces the link text input -->
            <label for="resource_file">Select File to Upload:</label>
            <input type="file" name="resource_file" id="resource_file" required>

            <label for="uploaded_by">Uploaded By (User):</label>
            <select name="uploaded_by" id="uploaded_by" required>
                <option value="">-- Select User --</option>
                <?php foreach ($users as $usr) : ?>
                    <option value="<?= $usr['user_id'] ?>">
                        <?= htmlspecialchars($usr['name']) ?> (ID: <?= $usr['user_id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="type">File Type (Optional, e.g. pdf, mp4):</label>
            <input type="text" name="type" id="type">

            <button type="submit" name="fulfill_resource">Upload Resource</button>
        </form>
    </fieldset>

    <!-- (C) RATE & REVIEW A RESOURCE -->
    <fieldset>
        <legend>Rate & Review a Resource</legend>
        <form method="post">
            <label for="resource_id_for_review">Select Resource:</label>
            <select name="resource_id_for_review" id="resource_id_for_review" required>
                <option value="">-- Select Resource --</option>
                <?php foreach ($resourcesList as $res) : ?>
                    <option value="<?= $res['resource_id'] ?>">
                        <?= htmlspecialchars($res['name']) ?> (ID: <?= $res['resource_id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="user_id_for_review">Your User Account:</label>
            <select name="user_id_for_review" id="user_id_for_review" required>
                <option value="">-- Select User --</option>
                <?php foreach ($users as $usr) : ?>
                    <option value="<?= $usr['user_id'] ?>">
                        <?= htmlspecialchars($usr['name']) ?> (ID: <?= $usr['user_id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="rating">Rating (1-5):</label>
            <input type="number" name="rating" id="rating" min="1" max="5" required>

            <label for="comment">Comment:</label>
            <textarea name="comment" id="comment" rows="3"></textarea>

            <button type="submit" name="review_resource">Submit Review</button>
        </form>
    </fieldset>

    <hr>

    <!-- (D) LIST & DOWNLOAD ALL RESOURCES -->
    <h2>All Available Resources</h2>
    <div class="resource-listing">
        <?php if ($all_resources_result && $all_resources_result->num_rows > 0): ?>
            <?php while ($resource = $all_resources_result->fetch_assoc()): ?>
                <div class="resource-item">
                    <h4><?= htmlspecialchars($resource['name']) ?> (ID: <?= $resource['resource_id'] ?>)</h4>
                    <p><strong>Type:</strong> <?= htmlspecialchars($resource['type']) ?></p>
                    <p><strong>Category:</strong> <?= htmlspecialchars($resource['category']) ?></p>
                    <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($resource['description'])) ?></p>
                    <p><strong>Uploaded By:</strong> <?= htmlspecialchars($resource['uploaded_by_name']) ?></p>

                    <p><strong>Link:</strong> 
                        <a href="<?= htmlspecialchars($resource['link']) ?>" download>
                            <?= htmlspecialchars($resource['link']) ?>
                        </a>
                        (Click to Download/View)
                    </p>
                    
                    <p><strong>Created At:</strong> <?= $resource['created_at'] ?></p>

                    <!-- Show average rating & reviews (optional) -->
                    <div class="review-section">
                        <?php
                        // Retrieve average rating and reviews for this resource
                        $rev_sql = "
                            SELECT rr.rating, rr.comment, u.name AS reviewer_name
                            FROM resource_reviews rr
                            JOIN users u ON rr.user_id = u.user_id
                            WHERE rr.resource_id = ?
                        ";
                        $rev_stmt = $conn->prepare($rev_sql);
                        $rev_stmt->bind_param("i", $resource['resource_id']);
                        $rev_stmt->execute();
                        $reviews_result = $rev_stmt->get_result();
                        
                        $reviews = [];
                        $total_rating = 0;
                        $count_rating = 0;
                        while ($review_row = $reviews_result->fetch_assoc()) {
                            $reviews[] = $review_row;
                            $total_rating += $review_row['rating'];
                            $count_rating++;
                        }
                        $rev_stmt->close();

                        if ($count_rating > 0) {
                            $avg_rating = round($total_rating / $count_rating, 1);
                            echo "<strong>Average Rating:</strong> {$avg_rating} / 5<br>";
                        } else {
                            echo "No reviews yet.<br>";
                        }

                        // List each review
                        foreach ($reviews as $rev) {
                            echo "<p><em>\"{$rev['comment']}\"</em> - Rating: {$rev['rating']} by "
                                . htmlspecialchars($rev['reviewer_name'])
                                . "</p>";
                        }
                        ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No resources uploaded yet.</p>
        <?php endif; ?>
    </div>

</body>
</html>

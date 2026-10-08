<?php
// projectaccess.php
$title = "Projects Access Management";
require_once 'include/msqli_connect.php';

// Handle new project assignment
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $project_code = trim($_POST['project_code']);

    if ($user_id && $project_code) {
        // Convert project_code -> project_id
        $stmt = $db->prepare("SELECT id FROM project WHERE project_code = ?");
        $stmt->bind_param("s", $project_code);
        $stmt->execute();
        $stmt->bind_result($project_id);
        $stmt->fetch();
        $stmt->close();

        if ($project_id) {
            // Check if already assigned
            $check = $db->prepare("SELECT id FROM user_project WHERE user_id = ? AND project_id = ?");
            $check->bind_param("ii", $user_id, $project_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows === 0) {
                $insert = $db->prepare("INSERT INTO user_project (user_id, project_id) VALUES (?, ?)");
                $insert->bind_param("ii", $user_id, $project_id);
                $insert->execute();
                $insert->close();

                $message = "✅ Project <b>$project_code</b> assigned successfully to user ID <b>$user_id</b>.";
            } else {
                $message = "⚠️ This project is already assigned to this user.";
            }

            $check->close();
        } else {
            $message = "❌ Invalid project code selected.";
        }
    } else {
        $message = "⚠️ Please fill in all fields.";
    }
}

// Fetch all projects
$projects = $db->query("SELECT project_code, project_name FROM project ORDER BY id ASC");

// Fetch all users
$users = $db->query("SELECT id, name FROM users ORDER BY id ASC"); // make sure 'users' table has name

// Fetch all current assignments
$assignments = $db->query("
    SELECT up.id, u.name AS user_name, p.project_code, p.project_name
    FROM user_project up
    JOIN users u ON up.user_id = u.id
    JOIN project p ON up.project_id = p.id
    ORDER BY u.name ASC, p.project_code ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f9fb;
            margin: 0;
            padding: 40px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h2 {
            text-align: center;
            color: #333;
        }
        .message {
            text-align: center;
            margin: 20px 0;
            font-weight: bold;
        }
        form {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
        }
        select, input[type="text"], button {
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 15px;
        }
        button {
            background: #007bff;
            color: #fff;
            cursor: pointer;
            border: none;
        }
        button:hover {
            background: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #f1f3f7;
        }
        tr:nth-child(even) {
            background: #fafbfc;
        }
    </style>
</head>
<body>
<div class="container">
    <h2><?php echo $title; ?></h2>

    <?php if ($message): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div>
            <label for="user_id">Select Staff:</label><br>
            <select name="user_id" id="user_id" required>
                <option value="">-- Choose Staff --</option>
                <?php while ($u = $users->fetch_assoc()): ?>
                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label for="project_code">Select Project:</label><br>
            <select name="project_code" id="project_code" required>
                <option value="">-- Choose Project --</option>
                <?php while ($p = $projects->fetch_assoc()): ?>
                    <option value="<?php echo $p['project_code']; ?>">
                        <?php echo $p['project_code'] . " - " . htmlspecialchars($p['project_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div style="align-self: end;">
            <button type="submit">Assign Project</button>
        </div>
    </form>

    <h3>Current Project Access</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Staff Name</th>
                <th>Project Code</th>
                <th>Project Name</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $count = 1;
            while ($a = $assignments->fetch_assoc()):
            ?>
            <tr>
                <td><?php echo $count++; ?></td>
                <td><?php echo htmlspecialchars($a['user_name']); ?></td>
                <td><?php echo htmlspecialchars($a['project_code']); ?></td>
                <td><?php echo htmlspecialchars($a['project_name']); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <a href="createproject.php" style="display:block;text-align:center;margin-top:20px;color:#007bff;text-decoration:none;">+ Create New Project</a>
</div>
</body>
</html>

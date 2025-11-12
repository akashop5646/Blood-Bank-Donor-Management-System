<?php
session_start();
require_once '../config.php';

// Check if the admin is logged in, otherwise redirect to login page
if (!isset($_SESSION['admin_loggedin'])) {
    header("Location: admin_login.php");
    exit();
}

$success_message = '';
$errors = [];

// --- LOGIC TO HANDLE SINGLE QUERY DELETION ---
if (isset($_GET['delete_id'])) {
    $query_id_to_delete = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM contact_queries WHERE id = ?");
    $stmt->bind_param("i", $query_id_to_delete);
    if ($stmt->execute()) {
        $success_message = "Query deleted successfully.";
    } else {
        $errors[] = "Error deleting query.";
    }
    $stmt->close();
}

// --- LOGIC TO HANDLE MULTIPLE QUERY DELETION ---
if (isset($_POST['delete_selected'])) {
    if (!empty($_POST['query_ids'])) {
        $query_ids = array_map('intval', $_POST['query_ids']);
        $placeholders = implode(',', array_fill(0, count($query_ids), '?'));
        $types = str_repeat('i', count($query_ids));

        $stmt = $conn->prepare("DELETE FROM contact_queries WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$query_ids);

        if ($stmt->execute()) {
            $success_message = "Selected queries deleted successfully.";
        } else {
            $errors[] = "Error deleting selected queries.";
        }
        $stmt->close();
    } else {
        $errors[] = "No queries selected for deletion.";
    }
}

// --- FETCH ALL CONTACT QUERIES ---
$queries_result = $conn->query("SELECT * FROM contact_queries ORDER BY submission_date DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Queries - Admin Panel</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" xintegrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        /* Using the same admin template styles */
        :root {
            --primary-color: #D92A2A;
            --primary-dark: #b32424;
            --sidebar-bg: #2c3e50;
            --sidebar-text: #ecf0f1;
            --sidebar-active: var(--primary-color);
            --main-bg: #f4f7f6;
            --card-bg: #ffffff;
            --text-dark: #34495e;
            --text-light: #7f8c8d;
            --border-color: #e1e1e1;
            --shadow: 0 4px 15px rgba(0,0,0,0.07);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background-color: var(--main-bg); color: var(--text-dark); display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: var(--sidebar-bg); color: var(--sidebar-text); display: flex; flex-direction: column; position: fixed; left: 0; top: 0; height: 100%; z-index: 1000; transition: transform 0.3s ease-in-out; }
        .sidebar-header { padding: 1.5rem; text-align: center; background-color: rgba(0,0,0,0.2); }
        .sidebar-logo { color: #fff; font-size: 1.8rem; font-weight: 700; text-decoration: none; }
        .sidebar-nav { flex-grow: 1; list-style: none; padding: 1rem 0; }
        .sidebar-nav li a { display: flex; align-items: center; padding: 1rem 1.5rem; color: var(--sidebar-text); text-decoration: none; transition: background-color 0.3s, color 0.3s; font-weight: 500; }
        .sidebar-nav li a i { margin-right: 1rem; width: 20px; text-align: center; }
        .sidebar-nav li a:hover { background-color: rgba(255,255,255,0.1); }
        .sidebar-nav li.active > a { background-color: var(--sidebar-active); color: #fff; }
        .sidebar-footer {
            padding: 1rem;
            text-align: center;
            font-size: 0.9rem;
            margin-top: auto;
        }
        .sidebar-footer a {
            display: block;
            padding: 0.7rem 1.5rem;
            border-radius: 5px;
            border: 2px solid var(--sidebar-text);
            color: var(--sidebar-text);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .sidebar-footer a:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: #fff;
        }
        .main-wrapper { flex-grow: 1; margin-left: 260px; display: flex; flex-direction: column; transition: margin-left 0.3s ease-in-out; }
        .header { background-color: var(--card-bg); box-shadow: 0 2px 5px rgba(0,0,0,0.05); padding: 0 2rem; height: 70px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 999; }
        .menu-icon { display: none; font-size: 1.5rem; cursor: pointer; color: var(--text-dark); }
        .header-title { font-size: 1.5rem; font-weight: 600; }
        .admin-profile a { color: var(--text-dark); text-decoration: none; font-weight: 600; }
        .admin-profile i { margin-left: 0.5rem; }
        .content { padding: 2rem; flex-grow: 1; }
        
        .btn-danger { background: #dc3545; color: #fff; border-color: #dc3545; padding: 0.7rem 1.5rem; border-radius: 5px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; cursor: pointer; border: 1px solid transparent; }
        .btn-danger:hover { background: #c82333; }

        .table-actions { margin-bottom: 1rem; }
        .table-container { background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { font-weight: 600; background-color: #f9f9f9; white-space: nowrap; }
        td.message-col { min-width: 300px; white-space: normal; }
        
        .action-buttons a { color: var(--text-dark); text-decoration: none; margin: 0 5px; font-size: 1.1rem; padding: 5px; border-radius: 5px; transition: color 0.2s, background-color 0.2s; }
        .action-buttons .delete-btn:hover { color: var(--primary-color); background-color: #ecf0f1; }
        
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; font-weight: 500; }
        .message.success { background-color: #d4edda; color: #155724; }
        .message.error { background-color: #f8d7da; color: #721c24; }

        @media (max-width: 992px) { .sidebar { transform: translateX(-260px); } .main-wrapper { margin-left: 0; } .menu-icon { display: block; } body.sidebar-active .sidebar { transform: translateX(0); } .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 998; } body.sidebar-active .overlay { display: block; } }
    </style>
</head>
<body>
    
    <div class="overlay"></div>

    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="sidebar-logo">BloodLink</a>
        </div>
        <ul class="sidebar-nav">
            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="manage_donors.php"><i class="fas fa-users"></i> Manage Donors</a></li>
            <li><a href="manage_requests.php"><i class="fas fa-tint"></i> Manage Requests</a></li>
            <li><a href="manage_admins.php"><i class="fas fa-user-shield"></i> Manage Admins</a></li>
            <li class="active"><a href="contact_queries.php"><i class="fas fa-envelope-open-text"></i> Contact Queries</a></li>
            <li><a href="settings.php"><i class="fas fa-cogs"></i> Settings</a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="../index.php?logout=true"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <div class="main-wrapper">
        <header class="header">
            <div class="menu-icon"><i class="fas fa-bars"></i></div>
            <h1 class="header-title">Contact Queries</h1>
            <div class="admin-profile"><a href="#">Admin <i class="fas fa-user-circle"></i></a></div>
        </header>

        <main class="content">
            <?php if (!empty($success_message)): ?>
                <div class="message success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="message error"><?php foreach ($errors as $error) echo "<p>$error</p>"; ?></div>
            <?php endif; ?>

            <form action="contact_queries.php" method="post" id="bulk-delete-form">
                <div class="table-actions">
                    <button type="submit" name="delete_selected" class="btn-danger">Delete Selected</button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all"></th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($queries_result && $queries_result->num_rows > 0): ?>
                                <?php while($query = $queries_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><input type="checkbox" name="query_ids[]" value="<?php echo $query['id']; ?>" class="row-checkbox"></td>
                                        <td><?php echo $query['id']; ?></td>
                                        <td><?php echo htmlspecialchars($query['name']); ?></td>
                                        <td><?php echo htmlspecialchars($query['email']); ?></td>
                                        <td class="message-col"><?php echo htmlspecialchars($query['message']); ?></td>
                                        <td><?php echo date("d M, Y H:i", strtotime($query['submission_date'])); ?></td>
                                        <td class="action-buttons">
                                            <a href="contact_queries.php?delete_id=<?php echo $query['id']; ?>" 
                                               class="delete-btn" 
                                               title="Delete Query"
                                               onclick="return confirm('Are you sure you want to delete this query?');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" style="text-align:center;">No contact queries found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Sidebar Toggle Logic ---
            const menuIcon = document.querySelector('.menu-icon');
            const overlay = document.querySelector('.overlay');
            if (menuIcon) {
                menuIcon.addEventListener('click', () => document.body.classList.toggle('sidebar-active'));
            }
            if (overlay) {
                overlay.addEventListener('click', () => document.body.classList.toggle('sidebar-active'));
            }

            // --- Bulk Delete and Select All Logic ---
            const selectAllCheckbox = document.getElementById('select-all');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            const bulkDeleteForm = document.getElementById('bulk-delete-form');

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    rowCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }

            if (bulkDeleteForm) {
                bulkDeleteForm.addEventListener('submit', function(e) {
                    const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked').length;
                    if (checkedCheckboxes === 0) {
                        alert('Please select at least one query to delete.');
                        e.preventDefault(); // Stop form submission
                        return;
                    }
                    if (!confirm('Are you sure you want to delete the selected queries?')) {
                        e.preventDefault(); // Stop form submission
                    }
                });
            }
        });
    </script>
</body>
</html>

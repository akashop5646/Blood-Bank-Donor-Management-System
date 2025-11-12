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

// --- LOGIC TO HANDLE SINGLE REQUEST DELETION ---
if (isset($_GET['delete_id'])) {
    $request_id_to_delete = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM donor_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id_to_delete);
    if ($stmt->execute()) {
        $success_message = "Request deleted successfully.";
    } else {
        $errors[] = "Error deleting request.";
    }
    $stmt->close();
}

// --- LOGIC TO HANDLE MULTIPLE REQUEST DELETION ---
if (isset($_POST['delete_selected'])) {
    if (!empty($_POST['request_ids'])) {
        // Sanitize all incoming IDs to ensure they are integers
        $request_ids = array_map('intval', $_POST['request_ids']);
        $placeholders = implode(',', array_fill(0, count($request_ids), '?'));
        $types = str_repeat('i', count($request_ids));

        $stmt = $conn->prepare("DELETE FROM donor_requests WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$request_ids);

        if ($stmt->execute()) {
            $success_message = "Selected requests deleted successfully.";
        } else {
            $errors[] = "Error deleting selected requests.";
        }
        $stmt->close();
    } else {
        $errors[] = "No requests selected for deletion.";
    }
}


// --- FETCH REQUESTS WITH SEARCH AND FILTER ---
$sql = "
    SELECT 
        dr.id, 
        dr.requester_name, 
        dr.requester_contact, 
        dr.message, 
        dr.request_date, 
        dr.status, 
        dr.expiry_date, -- ### NEW: Fetch expiry_date ###
        d.full_name as donor_name 
    FROM donor_requests dr 
    JOIN donors d ON dr.donor_id = d.id
";

$where_clauses = [];
$params = [];
$types = '';

// Check for search term (requester or donor name)
if (!empty($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $where_clauses[] = "(dr.requester_name LIKE ? OR d.full_name LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// Check for status filter
if (!empty($_GET['status'])) {
    // ### NEW: Handle 'Expired' status filter ###
    if ($_GET['status'] == 'Expired') {
        // Find requests that ARE 'Accepted' but ARE ALSO past their expiry date
        $where_clauses[] = "(dr.status = 'Accepted' AND dr.expiry_date IS NOT NULL AND dr.expiry_date < CURDATE())";
    } else if ($_GET['status'] == 'Accepted') {
        // Find requests that ARE 'Accepted' and ARE NOT expired
        $where_clauses[] = "(dr.status = 'Accepted' AND (dr.expiry_date IS NULL OR dr.expiry_date >= CURDATE()))";
    } else {
        $where_clauses[] = "dr.status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }
}

// Append WHERE clauses to the main query if any exist
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY dr.request_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$requests_result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests - Admin Panel</title>
    
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
        
        .filter-container { background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); margin-bottom: 2rem; }
        .filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end; }
        .filter-form .form-group input, .filter-form .form-group select { width: 100%; padding: 0.7rem; border: 1px solid #ccc; border-radius: 5px; font-family: inherit; }
        .filter-form .form-group label { margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; }
        .filter-buttons { display: flex; gap: 0.5rem; }
        .btn { padding: 0.7rem 1.5rem; border-radius: 5px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; cursor: pointer; border: 1px solid transparent; }
        .btn-primary { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary { background: #6c757d; color: #fff; border-color: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-danger { background: #dc3545; color: #fff; border-color: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-export { background-color: #28a745; color: #fff; border-color: #28a745; display: inline-block; }
        .btn-export:hover { background-color: #218838; }
        .btn-export i { margin-right: 0.5rem; }

        .table-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem; }
        .table-container { background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { font-weight: 600; background-color: #f9f9f9; white-space: nowrap; }
        td.message-col { min-width: 250px; white-space: normal; }
        
        .status-badge { padding: 0.25rem 0.6rem; border-radius: 1rem; font-size: 0.8rem; font-weight: 600; color: #fff; text-align: center; display: inline-block; }
        .status-Pending { background-color: #f1c40f; }
        .status-Accepted { background-color: #2ecc71; }
        .status-Denied { background-color: #e74c3c; }
        .status-Expired { background-color: #6c757d; } /* ### NEW: Expired status style ### */

        .action-buttons a { color: var(--text-dark); text-decoration: none; margin: 0 5px; font-size: 1.1rem; padding: 5px; border-radius: 5px; transition: color 0.2s, background-color 0.2s; }
        .action-buttons .delete-btn:hover { color: var(--primary-color); background-color: #ecf0f1; }
        
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; font-weight: 500; }
        .message.success { background-color: #d4edda; color: #155724; } /* ### Updated success colors ### */
        .message.error { background-color: #f8d7da; color: #721c24; } /* ### Updated error colors ### */

        @media (max-width: 992px) { .sidebar { transform: translateX(-260px); } .main-wrapper { margin-left: 0; } .menu-icon { display: block; } body.sidebar-active .sidebar { transform: translateX(0); } .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 998; } body.sidebar-active .overlay { display: block; } }
        @media (max-width: 768px) { .filter-form { grid-template-columns: 1fr; } }
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
            <li class="active"><a href="manage_requests.php"><i class="fas fa-tint"></i> Manage Requests</a></li>
            <li><a href="manage_admins.php"><i class="fas fa-user-shield"></i> Manage Admins</a></li>
            <li><a href="contact_queries.php"><i class="fas fa-envelope-open-text"></i> Contact Queries</a></li>
            <li><a href="settings.php"><i class="fas fa-cogs"></i> Settings</a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="../index.php?logout=true"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <div class="main-wrapper">
        <header class="header">
            <div class="menu-icon"><i class="fas fa-bars"></i></div>
            <h1 class="header-title">Manage Blood Requests</h1>
            <div class="admin-profile"><a href="#">Admin <i class="fas fa-user-circle"></i></a></div>
        </header>

        <main class="content">
            <?php if (!empty($success_message)): ?>
                <div class="message success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="message error"><?php foreach ($errors as $error) echo "<p>$error</p>"; ?></div>
            <?php endif; ?>

            <div class="filter-container">
                <form action="manage_requests.php" method="get" class="filter-form">
                    <div class="form-group">
                        <label for="search">Search Requester/Donor</label>
                        <input type="text" name="search" id="search" placeholder="Name..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">All</option>
                            <option value="Pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Accepted" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Accepted') ? 'selected' : ''; ?>>Accepted</option>
                            <option value="Denied" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Denied') ? 'selected' : ''; ?>>Denied</option>
                            <option value="Expired" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Expired') ? 'selected' : ''; ?>>Expired</option> <!-- ### NEW: Expired option ### -->
                        </select>
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="manage_requests.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <form action="manage_requests.php" method="post" id="bulk-delete-form">
                <div class="table-actions">
                    <button type="submit" name="delete_selected" class="btn btn-danger">Delete Selected</button>
                    <a href="export_requests.php" class="btn btn-export">
                        <i class="fas fa-file-excel"></i> Export Data
                    </a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all"></th>
                                <th>ID</th>
                                <th>Requester</th>
                                <th>Contact</th>
                                <th>Donor</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Expiry Date</th> <!-- ### NEW: Column ### -->
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($requests_result && $requests_result->num_rows > 0): ?>
                                <?php while($request = $requests_result->fetch_assoc()): ?>
                                    <?php
                                        // ### NEW: Check for expiry ###
                                        $status = $request['status'];
                                        $expiry_date = $request['expiry_date'];
                                        if ($status === 'Accepted' && $expiry_date !== NULL && (strtotime(date('Y-m-d')) > strtotime($expiry_date))) {
                                            $status = 'Expired';
                                        }
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" name="request_ids[]" value="<?php echo $request['id']; ?>" class="row-checkbox"></td>
                                        <td><?php echo $request['id']; ?></td>
                                        <td><?php echo htmlspecialchars($request['requester_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['requester_contact']); ?></td>
                                        <td><?php echo htmlspecialchars($request['donor_name']); ?></td>
                                        <td class="message-col"><?php echo htmlspecialchars($request['message']); ?></td>
                                        <td>
                                            <!-- ### NEW: Use $status variable for class ### -->
                                            <span class="status-badge status-<?php echo htmlspecialchars($status); ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date("d M, Y H:i", strtotime($request['request_date'])); ?></td>
                                        <!-- ### NEW: Display expiry date ### -->
                                        <td><?php echo ($expiry_date) ? date("d M, Y", strtotime($expiry_date)) : 'N/A'; ?></td>
                                        <td class="action-buttons">
                                            <a href="manage_requests.php?delete_id=<?php echo $request['id']; ?>" 
                                               class="delete-btn" 
                                               title="Delete Request"
                                               onclick="return confirm('Are you sure you want to delete this request?');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="10" style="text-align:center;">No requests found matching your criteria.</td></tr> <!-- ### NEW: Colspan is 10 ### -->
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
                        alert('Please select at least one request to delete.');
                        e.preventDefault(); // Stop form submission
                        return;
                    }
                    if (!confirm('Are you sure you want to delete the selected requests?')) {
                        e.preventDefault(); // Stop form submission
                    }
                });
            }
        });
    </script>
</body>
</html>

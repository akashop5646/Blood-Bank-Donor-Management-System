<?php
session_start();
require_once '../config.php';

// Check if the admin is logged in, otherwise redirect to login page
if (!isset($_SESSION['admin_loggedin'])) {
    header("Location: admin_login.php");
    exit();
}

$errors = [];
$success_message = '';

// --- FUNCTION TO CALCULATE AGE ---
function calculateAge($dob) {
    if ($dob) {
        $birthDate = new DateTime($dob);
        $today = new DateTime('today');
        if ($birthDate > $today) {
            return 0; // Or handle as an error, as DOB cannot be in the future
        }
        $age = $birthDate->diff($today)->y;
        return $age;
    }
    return 'N/A'; // Return Not Available if DOB is not set
}

// --- LOGIC TO HANDLE SINGLE DONOR DELETION ---
if (isset($_GET['delete_id'])) {
    $donor_id_to_delete = (int)$_GET['delete_id'];
    
    $stmt = $conn->prepare("DELETE FROM donors WHERE id = ?");
    $stmt->bind_param("i", $donor_id_to_delete);
    
    if ($stmt->execute()) {
        $success_message = "Donor deleted successfully.";
    } else {
        $errors[] = "Error deleting donor.";
    }
    $stmt->close();
}

// --- LOGIC TO HANDLE MULTIPLE DONOR DELETION ---
if (isset($_POST['delete_selected'])) {
    if (!empty($_POST['donor_ids'])) {
        // Sanitize all incoming IDs to ensure they are integers
        $donor_ids = array_map('intval', $_POST['donor_ids']);
        $placeholders = implode(',', array_fill(0, count($donor_ids), '?'));
        $types = str_repeat('i', count($donor_ids));

        $stmt = $conn->prepare("DELETE FROM donors WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$donor_ids);

        if ($stmt->execute()) {
            $success_message = "Selected donors deleted successfully.";
        } else {
            $errors[] = "Error deleting selected donors.";
        }
        $stmt->close();
    } else {
        $errors[] = "No donors selected for deletion.";
    }
}


// --- LOGIC TO HANDLE DONOR UPDATE ---
if (isset($_POST['update_donor'])) {
    // Sanitize and retrieve form data
    $donor_id = (int)$_POST['donor_id'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $blood_group = mysqli_real_escape_string($conn, $_POST['blood_group']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $date_of_birth = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
    $new_password = $_POST['new_password'];

    // Validation
    if (empty($full_name) || empty($email) || empty($phone_number) || empty($blood_group) || empty($address) || empty($status) || empty($date_of_birth)) {
        $errors[] = "All fields except 'New Password' are required.";
    }

    if (empty($errors)) {
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE donors SET full_name = ?, email = ?, phone_number = ?, blood_group = ?, address = ?, status = ?, date_of_birth = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssssssi", $full_name, $email, $phone_number, $blood_group, $address, $status, $date_of_birth, $hashed_password, $donor_id);
        } else {
            $stmt = $conn->prepare("UPDATE donors SET full_name = ?, email = ?, phone_number = ?, blood_group = ?, address = ?, status = ?, date_of_birth = ? WHERE id = ?");
            $stmt->bind_param("sssssssi", $full_name, $email, $phone_number, $blood_group, $address, $status, $date_of_birth, $donor_id);
        }

        if ($stmt->execute()) {
            $success_message = "Donor information updated successfully!";
        } else {
            $errors[] = "Failed to update donor information.";
        }
        $stmt->close();
    }
}


// --- FETCH DONORS WITH SEARCH AND FILTER ---
$sql = "SELECT * FROM donors";
$where_clauses = [];
$params = [];
$types = '';

if (!empty($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $where_clauses[] = "(full_name LIKE ? OR email LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}
if (!empty($_GET['blood_group'])) {
    $where_clauses[] = "blood_group = ?";
    $params[] = $_GET['blood_group'];
    $types .= 's';
}
if (!empty($_GET['status'])) {
    $where_clauses[] = "status = ?";
    $params[] = $_GET['status'];
    $types .= 's';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY registered_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$donors_result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Donors - Admin Panel</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" xintegrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        /* Using the same admin template styles from admin_dashboard.php */
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
            --success-bg: #d4edda;
            --success-text: #155724;
            --error-bg: #f8d7da;
            --error-text: #721c24;
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
        
        /* Search and Filter Form Styles */
        .filter-container { background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); margin-bottom: 2rem; }
        .filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; align-items: flex-end; }
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
        th, td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        th { font-weight: 600; background-color: #f9f9f9; }
        
        .action-buttons a, .action-buttons button { color: var(--text-dark); text-decoration: none; margin: 0 5px; font-size: 1.1rem; padding: 5px; border-radius: 5px; transition: color 0.2s, background-color 0.2s; background: none; border: none; cursor: pointer; }
        .action-buttons .edit-btn:hover { color: #3498db; background-color: #ecf0f1; }
        .action-buttons .delete-btn:hover { color: var(--primary-color); background-color: #ecf0f1; }
        
        .status-badge { padding: 0.25rem 0.6rem; border-radius: 1rem; font-size: 0.8rem; font-weight: 600; color: #fff; }
        .status-active { background-color: #2ecc71; }
        .status-inactive { background-color: #95a5a6; }

        /* Message Styles */
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; font-weight: 500; }
        .message.success { background-color: var(--success-bg); color: var(--success-text); }
        .message.error { background-color: var(--error-bg); color: var(--error-text); }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 600px; position: relative; animation: slideIn 0.4s ease-out; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .modal-header h2 { font-size: 1.5rem; }
        .close-btn { font-size: 2rem; font-weight: bold; cursor: pointer; color: #aaa; line-height: 1; }
        .close-btn:hover { color: #333; }
        @keyframes slideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group select { width: 100%; padding: 0.8rem; border: 1px solid #ccc; border-radius: 5px; font-family: inherit; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary-color); }
        .form-group.full-width { grid-column: 1 / -1; }
        .modal-footer { margin-top: 1.5rem; text-align: right; }
        .modal-footer .btn-primary { padding: 0.7rem 1.5rem; border-radius: 5px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; cursor: pointer; background: var(--primary-color); color: #fff; border: 1px solid var(--primary-color); }
        .modal-footer .btn-primary:hover { background: var(--primary-dark); }

        @media (max-width: 992px) { .sidebar { transform: translateX(-260px); } .main-wrapper { margin-left: 0; } .menu-icon { display: block; } body.sidebar-active .sidebar { transform: translateX(0); } .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 998; } body.sidebar-active .overlay { display: block; } }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .filter-form { grid-template-columns: 1fr; } }
        @media (max-width: 576px) { .content { padding: 1rem; } .header { padding: 0 1rem; } }
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
            <li class="active"><a href="manage_donors.php"><i class="fas fa-users"></i> Manage Donors</a></li>
            <li><a href="manage_requests.php"><i class="fas fa-tint"></i> Manage Requests</a></li>
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
            <h1 class="header-title">Manage Donors</h1>
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
                <form action="manage_donors.php" method="get" class="filter-form">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" placeholder="Name or Email..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="blood_group">Blood Group</label>
                        <select name="blood_group" id="blood_group">
                            <option value="">All</option>
                            <?php 
                            $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach ($blood_groups as $bg) {
                                $selected = (isset($_GET['blood_group']) && $_GET['blood_group'] == $bg) ? 'selected' : '';
                                echo "<option value='$bg' $selected>$bg</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">All</option>
                            <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (isset($_GET['status']) && $_GET['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="manage_donors.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <form action="manage_donors.php" method="post" id="bulk-delete-form">
                <div class="table-actions">
                    <button type="submit" name="delete_selected" class="btn btn-danger">Delete Selected</button>
                    <a href="export_excel.php" class="btn btn-export">
                        <i class="fas fa-file-excel"></i> Export as Excel
                    </a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all"></th>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>DOB</th>
                                <th>Age</th>
                                <th>Blood Group</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($donors_result && $donors_result->num_rows > 0): ?>
                                <?php while($donor = $donors_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><input type="checkbox" name="donor_ids[]" value="<?php echo $donor['id']; ?>" class="row-checkbox"></td>
                                        <td><?php echo $donor['id']; ?></td>
                                        <td><?php echo htmlspecialchars($donor['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($donor['email']); ?></td>
                                        <td><?php echo htmlspecialchars($donor['phone_number']); ?></td>
                                        <td><?php echo htmlspecialchars($donor['address']); ?></td>
                                        <td><?php echo date("d M, Y", strtotime($donor['date_of_birth'])); ?></td>
                                        <td><?php echo calculateAge($donor['date_of_birth']); ?></td>
                                        <td><?php echo htmlspecialchars($donor['blood_group']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($donor['status']); ?>">
                                                <?php echo htmlspecialchars($donor['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date("d M, Y", strtotime($donor['registered_date'])); ?></td>
                                        <td class="action-buttons">
                                            <button type="button" class="edit-btn" 
                                                data-id="<?php echo $donor['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($donor['full_name']); ?>"
                                                data-email="<?php echo htmlspecialchars($donor['email']); ?>"
                                                data-phone="<?php echo htmlspecialchars($donor['phone_number']); ?>"
                                                data-bloodgroup="<?php echo htmlspecialchars($donor['blood_group']); ?>"
                                                data-address="<?php echo htmlspecialchars($donor['address']); ?>"
                                                data-status="<?php echo htmlspecialchars($donor['status']); ?>"
                                                data-dob="<?php echo htmlspecialchars($donor['date_of_birth']); ?>"
                                                title="Edit Donor">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>
                                            <a href="manage_donors.php?delete_id=<?php echo $donor['id']; ?>" 
                                               class="delete-btn" 
                                               title="Delete Donor"
                                               onclick="return confirm('Are you sure you want to delete this donor? This action cannot be undone.');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="12" style="text-align:center;">No donors found matching your criteria.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </main>
    </div>

    <!-- Edit Donor Modal -->
    <div id="editDonorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Donor Information</h2>
                <span class="close-btn">&times;</span>
            </div>
            <form action="manage_donors.php" method="post">
                <input type="hidden" name="donor_id" id="edit-donor-id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit-full-name">Full Name</label>
                        <input type="text" name="full_name" id="edit-full-name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-email">Email</label>
                        <input type="email" name="email" id="edit-email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-phone-number">Phone Number</label>
                        <input type="tel" name="phone_number" id="edit-phone-number" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-blood-group">Blood Group</label>
                        <select name="blood_group" id="edit-blood-group" required>
                            <option value="A+">A+</option><option value="A-">A-</option>
                            <option value="B+">B+</option><option value="B-">B-</option>
                            <option value="AB+">AB+</option><option value="AB-">AB-</option>
                            <option value="O+">O+</option><option value="O-">O-</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="edit-address">Address</label>
                        <input type="text" name="address" id="edit-address" required>
                    </div>
                     <div class="form-group">
                        <label for="edit-date-of-birth">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="edit-date-of-birth" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-status">Status</label>
                        <select name="status" id="edit-status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="edit-new-password">New Password (optional)</label>
                        <input type="password" name="new_password" id="edit-new-password" placeholder="Leave blank to keep current password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_donor" class="btn-primary">Update Donor</button>
                </div>
            </form>
        </div>
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

            // --- Edit Modal Logic ---
            const editModal = document.getElementById('editDonorModal');
            const editBtns = document.querySelectorAll('.edit-btn');
            const closeBtn = editModal.querySelector('.close-btn');

            editBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id;
                    const name = btn.dataset.name;
                    const email = btn.dataset.email;
                    const phone = btn.dataset.phone;
                    const bloodgroup = btn.dataset.bloodgroup;
                    const address = btn.dataset.address;
                    const status = btn.dataset.status;
                    const dob = btn.dataset.dob;

                    document.getElementById('edit-donor-id').value = id;
                    document.getElementById('edit-full-name').value = name;
                    document.getElementById('edit-email').value = email;
                    document.getElementById('edit-phone-number').value = phone;
                    document.getElementById('edit-blood-group').value = bloodgroup;
                    document.getElementById('edit-address').value = address;
                    document.getElementById('edit-status').value = status;
                    document.getElementById('edit-date-of-birth').value = dob;
                    document.getElementById('edit-new-password').value = '';

                    editModal.style.display = 'block';
                });
            });

            const closeModal = () => {
                editModal.style.display = 'none';
            };

            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal);
            }
            window.addEventListener('click', (event) => {
                if (event.target == editModal) {
                    closeModal();
                }
            });

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
                        alert('Please select at least one donor to delete.');
                        e.preventDefault(); // Stop form submission
                        return;
                    }
                    if (!confirm('Are you sure you want to delete the selected donors?')) {
                        e.preventDefault(); // Stop form submission
                    }
                });
            }
        });
    </script>
</body>
</html>

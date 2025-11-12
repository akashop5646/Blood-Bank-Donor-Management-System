<?php
session_start();
require_once '../config.php';

// Check if the admin is logged in, otherwise redirect to login page
if (!isset($_SESSION['admin_loggedin']) || !isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Get the current admin's ID from the session to prevent self-deletion
$current_admin_id = $_SESSION['admin_id'];
$errors = [];
$success_message = '';

// --- LOGIC TO HANDLE ADMIN DELETION ---
if (isset($_GET['delete_id'])) {
    $admin_id_to_delete = (int)$_GET['delete_id'];

    // Security check: Prevent an admin from deleting their own account
    if ($admin_id_to_delete === $current_admin_id) {
        $errors[] = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->bind_param("i", $admin_id_to_delete);
        if ($stmt->execute()) {
            $success_message = "Admin deleted successfully.";
        } else {
            $errors[] = "Error deleting admin.";
        }
        $stmt->close();
    }
}

// --- LOGIC TO HANDLE ADMIN UPDATE ---
if (isset($_POST['update_admin'])) {
    $admin_id = (int)$_POST['admin_id'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $new_password = $_POST['new_password'];

    if (empty($full_name) || empty($username) || empty($email)) {
        $errors[] = "Full Name, Username, and Email are required.";
    }

    if (empty($errors)) {
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET full_name = ?, username = ?, email = ?, phone_number = ?, password = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $full_name, $username, $email, $phone_number, $hashed_password, $admin_id);
        } else {
            $stmt = $conn->prepare("UPDATE admins SET full_name = ?, username = ?, email = ?, phone_number = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $full_name, $username, $email, $phone_number, $admin_id);
        }

        if ($stmt->execute()) {
            $success_message = "Admin information updated successfully!";
        } else {
            $errors[] = "Failed to update admin information. Username or Email may already exist.";
        }
        $stmt->close();
    }
}

// --- LOGIC TO HANDLE NEW ADMIN CREATION ---
if (isset($_POST['add_admin'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $password = $_POST['password'];

    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $errors[] = "Full Name, Username, Email, and Password are required.";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admins (full_name, username, email, phone_number, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $full_name, $username, $email, $phone_number, $hashed_password);

        if ($stmt->execute()) {
            $success_message = "New admin added successfully!";
        } else {
            $errors[] = "Failed to add new admin. Username or Email may already exist.";
        }
        $stmt->close();
    }
}

// Fetch all admins from the database
$admins_result = $conn->query("SELECT id, full_name, username, email, phone_number FROM admins");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - Admin Panel</title>
    
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
        .sidebar-footer { padding: 1rem; text-align: center; font-size: 0.9rem; margin-top: auto; }
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
        .btn { padding: 0.7rem 1.5rem; border-radius: 5px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; cursor: pointer; border: 1px solid transparent; }
        .btn-primary { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background-color: #28a745; color: #fff; border-color: #28a745; }
        .btn-success:hover { background-color: #218838; }

        .page-actions { margin-bottom: 2rem; text-align: right; }
        .table-container { background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        th { font-weight: 600; background-color: #f9f9f9; }
        
        .action-buttons a, .action-buttons button { color: var(--text-dark); text-decoration: none; margin: 0 5px; font-size: 1.1rem; padding: 5px; border-radius: 5px; transition: color 0.2s, background-color 0.2s; background: none; border: none; cursor: pointer; }
        .action-buttons .edit-btn:hover { color: #3498db; background-color: #ecf0f1; }
        .action-buttons .delete-btn:hover { color: var(--primary-color); background-color: #ecf0f1; }
        .action-buttons .delete-btn[disabled] { color: #ccc; cursor: not-allowed; }
        .action-buttons .delete-btn[disabled]:hover { background-color: transparent; }
        
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; font-weight: 500; }
        .message.success { background-color: var(--success-bg); color: var(--success-text); }
        .message.error { background-color: var(--error-bg); color: var(--error-text); }

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
        .form-group input { width: 100%; padding: 0.8rem; border: 1px solid #ccc; border-radius: 5px; font-family: inherit; }
        .form-group input:focus { outline: none; border-color: var(--primary-color); }
        .form-group.full-width { grid-column: 1 / -1; }
        .modal-footer { margin-top: 1.5rem; text-align: right; }
        .modal-footer .btn { padding: 0.7rem 1.5rem; }

        @media (max-width: 992px) { .sidebar { transform: translateX(-260px); } .main-wrapper { margin-left: 0; } .menu-icon { display: block; } body.sidebar-active .sidebar { transform: translateX(0); } .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 998; } body.sidebar-active .overlay { display: block; } }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
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
            <li class="active"><a href="manage_admins.php"><i class="fas fa-user-shield"></i> Manage Admins</a></li>
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
            <h1 class="header-title">Manage Admins</h1>
            <div class="admin-profile"><a href="#">Admin <i class="fas fa-user-circle"></i></a></div>
        </header>

        <main class="content">
            <?php if (!empty($success_message)): ?>
                <div class="message success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="message error"><?php foreach ($errors as $error) echo "<p>$error</p>"; ?></div>
            <?php endif; ?>

            <div class="page-actions">
                <button id="add-admin-btn" class="btn btn-success"><i class="fas fa-plus"></i> Add New Admin</button>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($admins_result && $admins_result->num_rows > 0): ?>
                            <?php while($admin = $admins_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $admin['id']; ?></td>
                                    <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['phone_number']); ?></td>
                                    <td class="action-buttons">
                                        <button class="edit-btn" 
                                            data-id="<?php echo $admin['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($admin['full_name']); ?>"
                                            data-username="<?php echo htmlspecialchars($admin['username']); ?>"
                                            data-email="<?php echo htmlspecialchars($admin['email']); ?>"
                                            data-phone="<?php echo htmlspecialchars($admin['phone_number']); ?>"
                                            title="Edit Admin">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <a href="manage_admins.php?delete_id=<?php echo $admin['id']; ?>" 
                                           class="delete-btn" 
                                           title="Delete Admin"
                                           <?php if ($admin['id'] === $current_admin_id) echo 'disabled onclick="return false;"'; else echo 'onclick="return confirm(\'Are you sure you want to delete this admin?\');"'; ?>>
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center;">No admins found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add/Edit Admin Modal -->
    <div id="adminModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Add New Admin</h2>
                <span class="close-btn">&times;</span>
            </div>
            <form id="admin-form" action="manage_admins.php" method="post">
                <input type="hidden" name="admin_id" id="admin-id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" name="full_name" id="full_name" required>
                    </div>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <input type="tel" name="phone_number" id="phone_number">
                    </div>
                    <div class="form-group full-width">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" placeholder="Leave blank to keep current password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_admin" id="form-submit-btn" class="btn btn-primary">Add Admin</button>
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

            // --- Add/Edit Modal Logic ---
            const adminModal = document.getElementById('adminModal');
            const addAdminBtn = document.getElementById('add-admin-btn');
            const editBtns = document.querySelectorAll('.edit-btn');
            const closeBtn = adminModal.querySelector('.close-btn');
            const modalTitle = document.getElementById('modal-title');
            const adminForm = document.getElementById('admin-form');
            const submitBtn = document.getElementById('form-submit-btn');

            const openModal = (mode = 'add', data = {}) => {
                adminForm.reset(); // Clear previous data
                if (mode === 'add') {
                    modalTitle.textContent = 'Add New Admin';
                    submitBtn.textContent = 'Add Admin';
                    submitBtn.name = 'add_admin';
                    document.getElementById('password').required = true;
                } else {
                    modalTitle.textContent = 'Edit Admin Information';
                    submitBtn.textContent = 'Update Admin';
                    submitBtn.name = 'update_admin';
                    document.getElementById('admin-id').value = data.id;
                    document.getElementById('full_name').value = data.name;
                    document.getElementById('username').value = data.username;
                    document.getElementById('email').value = data.email;
                    document.getElementById('phone_number').value = data.phone;
                    document.getElementById('password').required = false;
                }
                adminModal.style.display = 'block';
            };

            addAdminBtn.addEventListener('click', () => openModal('add'));

            editBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const data = {
                        id: btn.dataset.id,
                        name: btn.dataset.name,
                        username: btn.dataset.username,
                        email: btn.dataset.email,
                        phone: btn.dataset.phone
                    };
                    openModal('edit', data);
                });
            });

            const closeModal = () => {
                adminModal.style.display = 'none';
            };

            closeBtn.addEventListener('click', closeModal);
            window.addEventListener('click', (event) => {
                if (event.target == adminModal) {
                    closeModal();
                }
            });
        });
    </script>
</body>
</html>

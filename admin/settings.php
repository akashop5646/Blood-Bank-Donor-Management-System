<?php
session_start();
require_once '../config.php';

// Check if the admin is logged in, otherwise redirect to login page
if (!isset($_SESSION['admin_loggedin']) || !isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Get the current admin's ID from the session
$current_admin_id = $_SESSION['admin_id'];
$profile_errors = [];
$profile_success_message = '';
$password_errors = [];
$password_success_message = '';

// --- LOGIC TO HANDLE PROFILE INFORMATION UPDATE ---
if (isset($_POST['update_profile'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);

    if (empty($full_name) || empty($username) || empty($email)) {
        $profile_errors[] = "Full Name, Username, and Email are required.";
    }

    if (empty($profile_errors)) {
        $stmt = $conn->prepare("UPDATE admins SET full_name = ?, username = ?, email = ?, phone_number = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $full_name, $username, $email, $phone_number, $current_admin_id);
        if ($stmt->execute()) {
            $profile_success_message = "Profile updated successfully!";
        } else {
            $profile_errors[] = "Failed to update profile. Username or Email may already be in use.";
        }
        $stmt->close();
    }
}

// --- LOGIC TO HANDLE PASSWORD CHANGE ---
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $password_errors[] = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_new_password) {
        $password_errors[] = "New passwords do not match.";
    } else {
        // Fetch the current hashed password from the database
        $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->bind_param("i", $current_admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();

        // Verify if the current password is correct
        if (password_verify($current_password, $admin['password'])) {
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_new_password, $current_admin_id);
            if ($stmt->execute()) {
                $password_success_message = "Password changed successfully!";
            } else {
                $password_errors[] = "Failed to change password.";
            }
            $stmt->close();
        } else {
            $password_errors[] = "Incorrect current password.";
        }
    }
}


// Fetch the latest admin data to display in the form
$stmt = $conn->prepare("SELECT full_name, username, email, phone_number FROM admins WHERE id = ?");
$stmt->bind_param("i", $current_admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Panel</title>
    
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
        
        .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .settings-card { background-color: var(--card-bg); padding: 2rem; border-radius: 8px; box-shadow: var(--shadow); }
        .settings-card h2 { font-size: 1.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input { width: 100%; padding: 0.8rem; border: 1px solid #ccc; border-radius: 5px; font-family: inherit; }
        .form-group input:focus { outline: none; border-color: var(--primary-color); }
        .btn-primary { padding: 0.7rem 1.5rem; border-radius: 5px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; cursor: pointer; background: var(--primary-color); color: #fff; border: 1px solid var(--primary-color); }
        .btn-primary:hover { background: var(--primary-dark); }
        
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; font-weight: 500; }
        .message.success { background-color: #d4edda; color: #155724; }
        .message.error { background-color: #f8d7da; color: #721c24; }

        @media (max-width: 992px) { .sidebar { transform: translateX(-260px); } .main-wrapper { margin-left: 0; } .menu-icon { display: block; } body.sidebar-active .sidebar { transform: translateX(0); } .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 998; } body.sidebar-active .overlay { display: block; } .settings-grid { grid-template-columns: 1fr; } }
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
            <li><a href="contact_queries.php"><i class="fas fa-envelope-open-text"></i> Contact Queries</a></li>
            <li class="active"><a href="settings.php"><i class="fas fa-cogs"></i> Settings</a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="../index.php?logout=true"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <div class="main-wrapper">
        <header class="header">
            <div class="menu-icon"><i class="fas fa-bars"></i></div>
            <h1 class="header-title">Settings</h1>
            <div class="admin-profile"><a href="#">Admin <i class="fas fa-user-circle"></i></a></div>
        </header>

        <main class="content">
            <div class="settings-grid">
                <!-- Profile Information Form -->
                <div class="settings-card">
                    <h2>Edit Profile</h2>
                    <?php if (!empty($profile_success_message)): ?>
                        <div class="message success"><?php echo $profile_success_message; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($profile_errors)): ?>
                        <div class="message error"><?php foreach ($profile_errors as $error) echo "<p>$error</p>"; ?></div>
                    <?php endif; ?>
                    <form action="settings.php" method="post">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($admin_data['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($admin_data['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($admin_data['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone_number">Phone Number</label>
                            <input type="tel" name="phone_number" id="phone_number" value="<?php echo htmlspecialchars($admin_data['phone_number']); ?>">
                        </div>
                        <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                    </form>
                </div>

                <!-- Change Password Form -->
                <div class="settings-card">
                    <h2>Change Password</h2>
                    <?php if (!empty($password_success_message)): ?>
                        <div class="message success"><?php echo $password_success_message; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($password_errors)): ?>
                        <div class="message error"><?php foreach ($password_errors as $error) echo "<p>$error</p>"; ?></div>
                    <?php endif; ?>
                    <form action="settings.php" method="post">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" name="current_password" id="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" name="new_password" id="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_new_password">Confirm New Password</label>
                            <input type="password" name="confirm_new_password" id="confirm_new_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
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
        });
    </script>
</body>
</html>

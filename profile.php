<?php
// We start the session to handle user login state. This must be at the very top.
session_start();
// We include the database connection file.
require_once 'config.php';

// If the user is not logged in, redirect them to the index page.
if (!isset($_SESSION['donor_id'])) {
    header("Location: index.php");
    exit();
}

// Get the logged-in donor's ID from the session.
$donor_id = $_SESSION['donor_id'];
$errors = [];
$success_message = '';
$today = date("Y-m-d");

// --- LOGIC TO HANDLE PROFILE INFORMATION UPDATE ---
if (isset($_POST['update_profile'])) {
    // Sanitize and retrieve form data
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $date_of_birth = mysqli_real_escape_string($conn, $_POST['date_of_birth']);


    // Basic validation
    if (empty($full_name) || empty($phone_number) || empty($address) || empty($date_of_birth)) {
        $errors[] = "Please fill in all profile fields.";
    }
    if ($status !== 'active' && $status !== 'inactive') {
        $errors[] = "Invalid status selected.";
    }
    if ($date_of_birth > $today) {
        $errors[] = "Date of Birth cannot be in the future.";
    }


    // If there are no errors, update the database
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE donors SET full_name = ?, phone_number = ?, address = ?, status = ?, date_of_birth = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $full_name, $phone_number, $address, $status, $date_of_birth, $donor_id);

        if ($stmt->execute()) {
            // Update the session name as well
            $_SESSION['donor_name'] = $full_name;
            $success_message = "Profile updated successfully!";
        } else {
            $errors[] = "Failed to update profile. Please try again.";
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
        $errors[] = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_new_password) {
        $errors[] = "New passwords do not match.";
    } else {
        // Fetch the current hashed password from the database
        $stmt = $conn->prepare("SELECT password FROM donors WHERE id = ?");
        $stmt->bind_param("i", $donor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Verify if the current password is correct
        if (password_verify($current_password, $user['password'])) {
            // Hash the new password and update it in the database
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE donors SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_new_password, $donor_id);
            if ($stmt->execute()) {
                $success_message = "Password changed successfully!";
            } else {
                $errors[] = "Failed to change password.";
            }
            $stmt->close();
        } else {
            $errors[] = "Incorrect current password.";
        }
    }
}


// Fetch the latest donor data to display in the form
$stmt = $conn->prepare("SELECT full_name, email, phone_number, blood_group, address, status, date_of_birth FROM donors WHERE id = ?");
$stmt->bind_param("i", $donor_id);
$stmt->execute();
$result = $stmt->get_result();
$donor = $result->fetch_assoc();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Blood Donor Directory</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

    <style>
        :root {
            --primary-color: #D92A2A;
            --secondary-color: #f8f9fa;
            --dark-color: #212529;
            --light-color: #fff;
            --font-family: 'Poppins', sans-serif;
            --border-radius: 8px;
            --shadow: 0 4px 15px rgba(0,0,0,0.07);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-family);
            line-height: 1.6;
            color: var(--dark-color);
            background-image: linear-gradient(rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.9)), url('https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1170&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .container { max-width: 1100px; margin: auto; overflow: hidden; padding: 0 2rem; }

        /* ### NEW RESPONSIVE HEADER STYLES ### */
        .header { background: var(--light-color); box-shadow: var(--shadow); position: fixed; width: 100%; top: 0; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 70px; }
        .logo { font-size: 1.8rem; font-weight: 700; color: var(--primary-color); text-decoration: none; }
        .logo i { transform: rotate(20deg); }
        .nav-menu { display: flex; list-style: none; }
        .nav-menu li a { color: var(--dark-color); padding: 0.5rem 1rem; text-decoration: none; font-weight: 600; transition: color 0.3s ease; }
        .nav-menu li a:hover, .nav-menu li.active a { color: var(--primary-color); }
        .nav-actions { display: flex; align-items: center; }
        .nav-actions > * { margin-left: 1rem; }
        .nav-greeting-link { font-weight: 600; color: #555; text-decoration: none; }
        .profile-icon-btn { font-size: 1.8rem; color: var(--dark-color); text-decoration: none; }
        .btn { display: inline-block; padding: 0.7rem 1.5rem; border-radius: var(--border-radius); text-decoration: none; font-weight: 600; transition: all 0.3s ease; cursor: pointer; }
        .btn-primary { background: var(--primary-color); color: var(--light-color); border: 1px solid var(--primary-color); }
        .btn-primary:hover { background: #c72525; }
        .btn-secondary { background: transparent; color: var(--dark-color); border: 1px solid #ddd; }
        .btn-secondary:hover { background: #f1f1f1; }

        .hamburger-menu { display: none; cursor: pointer; padding: 0.5rem; }
        .hamburger-menu .bar { display: block; width: 25px; height: 3px; margin: 5px auto; background-color: var(--dark-color); transition: all 0.3s ease-in-out; }

        .sidebar { position: fixed; top: 0; right: -300px; width: 280px; height: 100%; background-color: var(--light-color); box-shadow: -5px 0 15px rgba(0,0,0,0.1); z-index: 1005; transition: right 0.3s ease-in-out; display: flex; flex-direction: column; padding: 1.5rem; }
        .sidebar.active { right: 0; }
        .sidebar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #eee; }
        .sidebar-header .logo { font-size: 1.5rem; }
        .sidebar-close-btn { font-size: 2rem; cursor: pointer; }
        .sidebar .nav-menu { flex-direction: column; align-items: flex-start; }
        .sidebar .nav-menu li { width: 100%; margin-bottom: 1rem; }
        .sidebar .nav-menu li a { font-size: 1.2rem; padding: 0.5rem 0; }
        .sidebar .nav-actions { flex-direction: column; align-items: stretch; margin-top: auto; }
        .sidebar .nav-actions > * { margin: 0.5rem 0; text-align: center; }

        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1004; transition: opacity 0.3s ease-in-out; opacity: 0; }
        .overlay.active { display: block; opacity: 1; }
        /* ### END OF HEADER STYLES ### */

        .profile-wrapper {
            padding-top: 100px;
            padding-bottom: 40px;
        }
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            background: var(--light-color);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        .profile-box {
            padding: 2rem;
        }
        .profile-box h2 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 0.5rem;
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary-color); }
        .form-group input[disabled] { background: #e9ecef; cursor: not-allowed; }
        
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: var(--border-radius); text-align: left; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        .footer { background: var(--dark-color); color: var(--light-color); text-align: center; padding: 2rem 0; }

        @media (max-width: 992px) {
            .header .nav-menu, .header .nav-actions { display: none; }
            .hamburger-menu { display: block; }
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <header class="header">
        <nav class="navbar container">
            <a href="index.php" class="logo">Blood<i class="fas fa-tint"></i>Link</a>
            
            <!-- Desktop Navigation -->
            <ul class="nav-menu">
                <li><a href="index.php#about" class="nav-link">About Us</a></li>
                <li><a href="index.php#how-it-works" class="nav-link">How It Works</a></li>
                <li><a href="search_donor.php" class="nav-link">Search Donors</a></li>
                <li><a href="index.php#contact" class="nav-link">Contact Us</a></li>
            </ul>
            <div class="nav-actions">
                <a href="profile.php" class="nav-greeting-link" title="My Profile">Hi, <?php echo htmlspecialchars(explode(' ', $_SESSION['donor_name'])[0]); ?></a>
                <a href="profile.php" class="profile-icon-btn" title="My Profile"><i class="fas fa-user-circle"></i></a>
                <a href="index.php?logout=true" class="btn btn-secondary">Logout</a>
            </div>
            
            <!-- Hamburger Menu Icon -->
            <div class="hamburger-menu">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
        </nav>
    </header>

    <!-- Mobile Sidebar & Overlay -->
    <div class="overlay"></div>
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">BloodLink</a>
            <span class="sidebar-close-btn">&times;</span>
        </div>
        <ul class="nav-menu">
            <li><a href="index.php#about" class="nav-link">About Us</a></li>
            <li><a href="index.php#how-it-works" class="nav-link">How It Works</a></li>
            <li><a href="search_donor.php" class="nav-link">Search Donors</a></li>
            <li><a href="index.php#contact" class="nav-link">Contact Us</a></li>
        </ul>
        <div class="nav-actions">
            <a href="profile.php" class="btn btn-secondary">My Profile</a>
            <a href="index.php?logout=true" class="btn btn-primary">Logout</a>
        </div>
    </div>

    <main class="profile-wrapper">
        <div class="container">
            <?php if (!empty($errors)): ?>
                <div class="message error"><?php foreach ($errors as $error) echo "<p>$error</p>"; ?></div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo $success_message; ?></p></div>
            <?php endif; ?>

            <div class="profile-container">
                <!-- Profile Information Form -->
                <div class="profile-box">
                    <h2>Edit Profile Information</h2>
                    <form action="profile.php" method="post">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($donor['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($donor['email']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo htmlspecialchars($donor['date_of_birth']); ?>" max="<?php echo $today; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone_number">Phone Number</label>
                            <input type="tel" name="phone_number" id="phone_number" value="<?php echo htmlspecialchars($donor['phone_number']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="blood_group">Blood Group</label>
                            <input type="text" name="blood_group" id="blood_group" value="<?php echo htmlspecialchars($donor['blood_group']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="address">City / Address</label>
                            <input type="text" name="address" id="address" value="<?php echo htmlspecialchars($donor['address']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="status">Availability Status</label>
                            <select name="status" id="status" required>
                                <option value="active" <?php if($donor['status'] == 'active') echo 'selected'; ?>>Active (Available for Donation)</option>
                                <option value="inactive" <?php if($donor['status'] == 'inactive') echo 'selected'; ?>>Inactive (Not Available)</option>
                            </select>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>

                <!-- Change Password Form -->
                <div class="profile-box">
                    <h2>Change Password</h2>
                    <form action="profile.php" method="post">
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
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 BloodLink Directory. All Rights Reserved.</p>
        </div>
    </footer>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector('.hamburger-menu');
            const sidebar = document.querySelector('.sidebar');
            const sidebarClose = document.querySelector('.sidebar-close-btn');
            const overlay = document.querySelector('.overlay');
            const sidebarLinks = document.querySelectorAll('.sidebar .nav-link');

            const openSidebar = () => {
                sidebar.classList.add('active');
                overlay.classList.add('active');
            };

            const closeSidebar = () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            };

            hamburger.addEventListener('click', openSidebar);
            sidebarClose.addEventListener('click', closeSidebar);
            overlay.addEventListener('click', closeSidebar);
            sidebarLinks.forEach(link => link.addEventListener('click', closeSidebar));
        });
    </script>

</body>
</html>

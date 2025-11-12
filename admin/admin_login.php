<?php
session_start();
// Go up one directory to find the config.php file
require_once '../config.php'; 

$errors = [];

// Redirect if already logged in
if (isset($_SESSION['admin_loggedin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_POST['admin_login'])) {
    // Sanitize inputs
    $login_identifier = mysqli_real_escape_string($conn, $_POST['login_identifier']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $password = $_POST['password'];

    // Validation
    if (empty($login_identifier) || empty($phone_number) || empty($password)) {
        $errors[] = "All fields are required.";
    } else {
        // Prepare a statement to find the admin by username/email and phone number
        // **MODIFIED**: Also select the admin's ID
        $stmt = $conn->prepare("SELECT id, password FROM admins WHERE (username = ? OR email = ?) AND phone_number = ?");
        $stmt->bind_param("sss", $login_identifier, $login_identifier, $phone_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            // Verify the password
            if (password_verify($password, $admin['password'])) {
                // Login successful, set session variables
                $_SESSION['admin_loggedin'] = true;
                // **ADDED**: Store the admin's ID in the session for security checks
                $_SESSION['admin_id'] = $admin['id']; 
                header("Location: admin_dashboard.php");
                exit();
            } else {
                // Password does not match
                $errors[] = "Invalid credentials. Please try again.";
            }
        } else {
            // No user found with the given details
            $errors[] = "Invalid credentials. Please try again.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - BloodLink</title>
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
        body {
            font-family: var(--font-family);
            background-color: #f4f7f6;
        }
        .container { max-width: 1100px; margin: auto; overflow: hidden; padding: 0 2rem; }

        /* ### HEADER & NAVIGATION STYLES (from index.php) ### */
        .header { background: var(--light-color); box-shadow: var(--shadow); position: fixed; width: 100%; top: 0; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 70px; }
        .logo { font-size: 1.8rem; font-weight: 700; color: var(--primary-color); text-decoration: none; }
        .logo i { transform: rotate(20deg); }
        .nav-menu { display: flex; list-style: none; }
        .nav-menu li a { color: var(--dark-color); padding: 0.5rem 1rem; text-decoration: none; font-weight: 600; transition: color 0.3s ease; }
        .nav-menu li a:hover { color: var(--primary-color); }
        .nav-actions { display: flex; align-items: center; }
        .nav-actions > * { margin-left: 1rem; }
        .btn { display: inline-block; padding: 0.7rem 1.5rem; border-radius: var(--border-radius); text-decoration: none; font-weight: 600; transition: all 0.3s ease; cursor: pointer; }
        .btn-primary { background: var(--primary-color); color: var(--light-color); border: 1px solid var(--primary-color); }
        .btn-primary:hover { background: #c72525; }
        .btn-secondary { background: transparent; color: var(--dark-color); border: 1px solid #ddd; }
        .btn-secondary:hover { background: #f1f1f1; }
        .hamburger-menu { display: none; cursor: pointer; padding: 0.5rem; }
        .hamburger-menu .bar { display: block; width: 25px; height: 3px; margin: 5px auto; background-color: var(--dark-color); }
        
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

        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1004; opacity: 0; transition: opacity 0.3s ease-in-out; }
        .overlay.active { display: block; opacity: 1; }
        
        /* Main content area for centering the form */
        .login-main {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding-top: 70px; /* Offset for fixed header */
        }
        .login-container {
            background: var(--light-color);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-container h2 { margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: var(--border-radius); font-family: inherit; }
        .btn-login { display: block; width: 100%; padding: 0.8rem; border: none; background: var(--primary-color); color: var(--light-color); border-radius: var(--border-radius); cursor: pointer; font-size: 1rem; font-weight: 600; transition: background-color 0.3s ease; }
        .btn-login:hover { background-color: #c72525; }
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: var(--border-radius); }
        .message.error { background-color: #f8d7da; color: #721c24; }
        .form-extra-link {
            text-align: right;
            margin-top: -1rem;
            margin-bottom: 1.5rem;
        }
        .form-extra-link a {
            font-size: 0.9rem;
            color: #555;
            text-decoration: none;
        }
        .form-extra-link a:hover {
            color: var(--primary-color);
        }
        
        @media (max-width: 992px) {
            .header .nav-menu, .header .nav-actions { display: none; }
            .hamburger-menu { display: block; }
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="navbar container">
            <a href="../index.php" class="logo">Blood<i class="fas fa-tint"></i>Link</a>
            
            <ul class="nav-menu">
                <li><a href="../index.php#about" class="nav-link">About Us</a></li>
                <li><a href="../index.php#how-it-works" class="nav-link">How It Works</a></li>
                <li><a href="../search_donor.php" class="nav-link">Search Donors</a></li>
                <li><a href="../index.php#contact" class="nav-link">Contact Us</a></li>
            </ul>
            <div class="nav-actions">
                <a href="../index.php" class="btn btn-secondary">Back to Home</a>
            </div>
            
            <div class="hamburger-menu">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
        </nav>
    </header>
    
    <div class="overlay"></div>
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="logo">BloodLink</a>
            <span class="sidebar-close-btn">&times;</span>
        </div>
        <ul class="nav-menu">
            <li><a href="../index.php#about" class="nav-link">About Us</a></li>
            <li><a href="../index.php#how-it-works" class="nav-link">How It Works</a></li>
            <li><a href="../search_donor.php" class="nav-link">Search Donors</a></li>
            <li><a href="../index.php#contact" class="nav-link">Contact Us</a></li>
        </ul>
        <div class="nav-actions">
            <a href="../index.php" class="btn btn-secondary">Back to Home</a>
        </div>
    </div>

    <main class="login-main">
        <div class="login-container">
            <h2>Admin Login</h2>
            <?php if (!empty($errors)): ?>
                <div class="message error"><?php foreach ($errors as $error) echo "<p>$error</p>"; ?></div>
            <?php endif; ?>
            <form action="admin_login.php" method="post">
                <div class="form-group">
                    <label for="login_identifier">Username or Email</label>
                    <input type="text" name="login_identifier" id="login_identifier" required>
                </div>
                <div class="form-group">
                    <label for="phone_number">Phone Number</label>
                    <input type="tel" name="phone_number" id="phone_number" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <div class="form-extra-link">
                    <a href="admin_forgot.php">Forgot Password?</a>
                </div>
                <button type="submit" name="admin_login" class="btn-login">Login</button>
            </form>
        </div>
    </main>

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

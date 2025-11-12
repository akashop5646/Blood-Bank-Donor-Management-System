<?php
// We start the session to handle user login state. This must be at the very top.
session_start();
// We include the database connection file. Make sure config.php exists in the same folder.
require_once 'config.php';

$errors = [];
$success_message = '';
// ### NEW: Variables specifically for the contact form to avoid message mix-ups ###
$contact_errors = [];
$contact_success_message = '';


// ### NEW: Get today's date to prevent future date selection ###
$today = date("Y-m-d");

// --- REGISTRATION LOGIC ---
if (isset($_POST['register'])) {
    // Sanitize and retrieve form data
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $date_of_birth = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $blood_group = mysqli_real_escape_string($conn, $_POST['blood_group']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);

    // --- Validation Checks ---
    if (empty($full_name) || empty($email) || empty($date_of_birth) || empty($password) || empty($phone_number) || empty($blood_group) || empty($address)) {
        $errors[] = "All fields are required.";
    }

    // ### UPDATED: Validate password strength ###
    if (strlen($password) <= 6 || !preg_match("/[0-9]/", $password) || !preg_match("/[^A-Za-z0-9]/", $password)) {
        $errors[] = "Password must be more than 6 characters and contain at least one number and one special symbol.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // ### UPDATED: Validate phone number for exactly 10 digits ###
    if (!preg_match('/^\d{10}$/', $phone_number)) {
        $errors[] = "Phone number must be exactly 10 digits.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    if ($date_of_birth > $today) {
        $errors[] = "Date of Birth cannot be in the future.";
    }


    // Check if the email already exists in the database to prevent duplicates
    $stmt = $conn->prepare("SELECT id FROM donors WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = "An account with this email already exists.";
    }
    $stmt->close();

    // If there are no errors, proceed to insert the new donor into the database
    if (empty($errors)) {
        // Hash the password for security before storing it
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO donors (full_name, email, date_of_birth, password, phone_number, blood_group, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $full_name, $email, $date_of_birth, $hashed_password, $phone_number, $blood_group, $address);

        if ($stmt->execute()) {
            $success_message = "Registration successful! You can now log in.";
            // ### NEW: Clear POST data after successful registration ###
            $_POST = array();
        } else {
            $errors[] = "Error during registration. Please try again.";
        }
        $stmt->close();
    }
}

// --- LOGIN LOGIC ---
if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $errors[] = "Both email and password are required.";
    } else {
        // Prepare a statement to find the user by email
        $stmt = $conn->prepare("SELECT id, full_name, password FROM donors WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Verify the submitted password against the hashed password in the database
            if (password_verify($password, $user['password'])) {
                // Login successful, set session variables to remember the user
                $_SESSION['donor_id'] = $user['id'];
                $_SESSION['donor_name'] = $user['full_name'];
                // Redirect back to the index page to show the logged-in state
                header("Location: index.php"); 
                exit();
            } else {
                $errors[] = "Invalid email or password.";
            }
        } else {
            $errors[] = "Invalid email or password.";
        }
        $stmt->close();
    }
}

// --- CONTACT FORM LOGIC ---
if (isset($_POST['contact_submit'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    // Validation
    if (empty($name) || empty($email) || empty($message)) {
        $contact_errors[] = "All fields are required.";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contact_errors[] = "Invalid email format.";
    }

    // If no errors, insert into the database
    if (empty($contact_errors)) {
        $stmt = $conn->prepare("INSERT INTO contact_queries (name, email, message) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $message);
        if ($stmt->execute()) {
            $contact_success_message = "Thank you for your message! We will get back to you shortly.";
        } else {
            $contact_errors[] = "Sorry, there was an error sending your message. Please try again later.";
        }
        $stmt->close();
    }
}


// --- UNIFIED LOGOUT LOGIC ---
if (isset($_GET['logout'])) {
    // This will destroy all session data for both donors and admins
    session_destroy();
    header("Location: index.php");
    exit();
}

// Determine the correct link for the Admin button
$admin_link = 'admin/admin_login.php'; // Default link
if (isset($_SESSION['admin_loggedin']) && $_SESSION['admin_loggedin'] === true) {
    $admin_link = 'admin/admin_dashboard.php';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donor Directory - Save a Life</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Using Font Awesome 5 for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    

    <style>
        /* General Styling & Variables */
        :root {
            --primary-color: #D92A2A; /* A strong, hopeful red */
            --secondary-color: #f8f9fa;
            --dark-color: #212529;
            --light-color: #fff;
            --font-family: 'Poppins', sans-serif;
            --border-radius: 8px;
            --shadow: 0 4px 15px rgba(0,0,0,0.07);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-family);
            line-height: 1.6;
            background: #fff;
            color: var(--dark-color);
        }

        .container {
            max-width: 1100px;
            margin: auto;
            overflow: hidden;
            padding: 0 2rem;
        }

        /* Header & Navigation */
        .header {
            background: var(--light-color);
            box-shadow: var(--shadow);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav-menu {
            display: flex;
            list-style: none;
        }

        .nav-menu li a {
            color: var(--dark-color);
            padding: 0.5rem 1rem;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .nav-menu li a:hover {
            color: var(--primary-color);
        }

        .nav-actions {
            display: flex;
            align-items: center;
        }

        .nav-actions > * {
            margin-left: 1rem;
        }
        .nav-actions > *:first-child {
            margin-left: 0;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 0.7rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--light-color);
            border: 1px solid var(--primary-color);
        }

        .btn-primary:hover {
            background: #c72525;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(217, 42, 42, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: var(--dark-color);
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background: #f1f1f1;
        }

        .btn-lg {
            font-size: 1.1rem;
            padding: 0.9rem 2rem;
        }

        .nav-greeting-link {
            font-weight: 600;
            color: #555;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .nav-greeting-link:hover {
            color: var(--primary-color);
        }

        .profile-icon-btn {
            font-size: 1.8rem;
            color: var(--dark-color);
            text-decoration: none;
            transition: color 0.3s ease;
            line-height: 1;
        }

        .profile-icon-btn:hover {
            color: var(--primary-color);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(to right, #fdfbfb, #ebedee);
            padding: 120px 0;
            margin-top: 70px;
            overflow: hidden;
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center;
            gap: 2rem;
        }

        .hero-title {
            font-size: 3rem;
            line-height: 1.2;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .hero-subtitle {
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .hero-image-container {
            text-align: center;
        }

        .hero-image {
            max-width: 100%;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .animate-on-load {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s ease-out forwards;
        }
        .hero-title { animation-delay: 0.2s; }
        .hero-subtitle { animation-delay: 0.4s; }
        .hero-image-container { animation-delay: 0.5s; }
        .btn-lg { animation-delay: 0.6s; }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .section-subtitle {
            text-align: center;
            max-width: 600px;
            margin: auto;
            margin-bottom: 3rem;
            color: #666;
        }

        .how-it-works-section {
            padding: 80px 0;
            background: var(--secondary-color);
        }
        .steps-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            text-align: center;
        }
        .step {
            background: var(--light-color);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .step:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 25px rgba(0,0,0,0.1);
        }
        .step-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        .step h3 {
            margin-bottom: 0.5rem;
        }

        .content-section {
            padding: 80px 0;
        }
        
        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }
        .contact-info p {
            margin-bottom: 1rem;
        }
        .contact-info i {
            color: var(--primary-color);
            margin-right: 10px;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
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

        .footer {
            background: var(--dark-color);
            color: var(--light-color);
            text-align: center;
            padding: 2rem 0;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: var(--border-radius);
            position: relative;
            animation: slideIn 0.5s ease-out;
        }
        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-btn:hover,
        .close-btn:focus {
            color: black;
            text-decoration: none;
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: var(--border-radius);
            text-align: left;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        /* MOBILE SIDEBAR STYLES */
        .hamburger-menu {
            display: none; /* Hidden on desktop */
            cursor: pointer;
            padding: 0.5rem;
        }
        .hamburger-menu .bar {
            display: block;
            width: 25px;
            height: 3px;
            margin: 5px auto;
            background-color: var(--dark-color);
            transition: all 0.3s ease-in-out;
        }

        .sidebar {
            position: fixed;
            top: 0;
            right: -300px; /* Start off-screen */
            width: 280px;
            height: 100%;
            background-color: var(--light-color);
            box-shadow: -5px 0 15px rgba(0,0,0,0.1);
            z-index: 1005;
            transition: right 0.3s ease-in-out;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
        }

        .sidebar.active {
            right: 0; /* Slide in */
        }
        
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        .sidebar-header .logo {
            font-size: 1.5rem;
        }
        .sidebar-close-btn {
            font-size: 2rem;
            cursor: pointer;
        }
        
        .sidebar .nav-menu {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .sidebar .nav-menu li {
            width: 100%;
            margin-bottom: 1rem;
        }
        .sidebar .nav-menu li a {
            font-size: 1.2rem;
            padding: 0.5rem 0;
        }
        
        .sidebar .nav-actions {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            margin-top: auto; /* Push to the bottom */
        }
        .sidebar .nav-actions > * {
            margin: 0.5rem 0;
            text-align: center;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            z-index: 1004;
            transition: opacity 0.3s ease-in-out;
            opacity: 0;
        }
        .overlay.active {
            display: block;
            opacity: 1;
        }

        /* --- ADD THIS CSS FOR INFO TOOLTIPS --- */
        .label-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px; /* Replaces the label's default margin */
        }

        .form-group label {
            margin-bottom: 0; /* We use the container for spacing now */
        }
        
        .info-icon {
            position: relative;
            cursor: pointer;
            color: #aaa;
        }

        .info-icon .fa-info-circle {
            font-size: 1rem;
        }

        .tooltip-text {
            visibility: hidden;
            width: 240px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 10;
            bottom: 135%; /* Position above the icon */
            left: 50%;
            margin-left: -120px; /* Use half of the width to center the tooltip */
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.85rem;
            font-weight: 400;
        }

        .tooltip-text::after { /* This creates the small arrow */
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #555 transparent transparent transparent;
        }

        .info-icon:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        /* --- END OF TOOLTIP CSS --- */


        @media (max-width: 992px) {
            .header .nav-menu, .header .nav-actions {
                display: none; 
            }
            .hamburger-menu {
                display: block; 
            }
        }

        @media (max-width: 768px) {
            .hero-content, .contact-container {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .contact-info {
                margin-bottom: 2rem;
            }
            .hero-image-container {
                margin-top: 2rem;
            }
            .hero-title {
                font-size: 2.5rem;
            }
            .steps-container {
                grid-template-columns: 1fr;
            }
            .section-title {
                font-size: 2rem;
            }

            .tooltip-text { /* Adjust tooltip for mobile */
                width: 200px;
                margin-left: -100px;
            }
        }
    </style>
</head>
<body>

    <header class="header">
        <nav class="navbar container">
            <a href="index.php" class="logo">BloodLink</a>
            
            <!-- Desktop Navigation -->
            <ul class="nav-menu">
                <li><a href="#about" class="nav-link">About Us</a></li>
                <li><a href="#how-it-works" class="nav-link">How It Works</a></li>
                <li><a href="search_donor.php" class="nav-link">Search Donors</a></li>
                <li><a href="#contact" class="nav-link">Contact Us</a></li>
                <li><a href="<?php echo $admin_link; ?>" class="nav-link">Admin</a></li>
            </ul>
            <div class="nav-actions">
                <?php if (isset($_SESSION['admin_loggedin'])): ?>
                    <!-- Admin is logged in -->
                    <span class="nav-greeting-link">Admin</span>
                    <a href="admin/admin_dashboard.php" class="profile-icon-btn" title="Dashboard"><i class="fas fa-tachometer-alt"></i></a>
                    <a href="index.php?logout=true" class="btn btn-secondary">Logout</a>
                <?php elseif (isset($_SESSION['donor_id'])): ?>
                    <!-- Donor is logged in -->
                    <a href="profile.php" class="nav-greeting-link" title="My Profile">Hi, <?php echo htmlspecialchars(explode(' ', $_SESSION['donor_name'])[0]); ?></a>
                    <a href="profile.php" class="profile-icon-btn" title="My Profile"><i class="fas fa-user-circle"></i></a>
                    <a href="index.php?logout=true" class="btn btn-secondary">Logout</a>
                <?php else: ?>
                    <!-- No one is logged in -->
                    <button id="loginBtn" class="btn btn-secondary">Login</button>
                    <button id="registerBtn" class="btn btn-primary">Register</button>
                <?php endif; ?>
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
            <li><a href="#about" class="nav-link">About Us</a></li>
            <li><a href="#how-it-works" class="nav-link">How It Works</a></li>
            <li><a href="search_donor.php" class="nav-link">Search Donors</a></li>
            <li><a href="#contact" class="nav-link">Contact Us</a></li>
            <li><a href="<?php echo $admin_link; ?>" class="nav-link">Admin</a></li>
        </ul>
        <div class="nav-actions">
            <?php if (isset($_SESSION['admin_loggedin'])): ?>
                <a href="admin/admin_dashboard.php" class="btn btn-secondary">Dashboard</a>
                <a href="index.php?logout=true" class="btn btn-primary">Logout</a>
            <?php elseif (isset($_SESSION['donor_id'])): ?>
                <a href="profile.php" class="btn btn-secondary">My Profile</a>
                <a href="index.php?logout=true" class="btn btn-primary">Logout</a>
            <?php else: ?>
                <button id="mobileLoginBtn" class="btn btn-secondary">Login</button>
                <button id="mobileRegisterBtn" class="btn btn-primary">Register</button>
            <?php endif; ?>
        </div>
    </div>


    <main>
        <section class="hero">
            <div class="hero-content container">
                <div class="hero-text">
                    <h1 class="hero-title animate-on-load">Your Drop of Blood Can Give a Lifetime.</h1>
                    <p class="hero-subtitle animate-on-load">Connect with voluntary blood donors in your area. Become a part of a community dedicated to saving lives.</p>
                    <a href="search_donor.php" class="btn btn-primary btn-lg animate-on-load">Find a Donor Now &rarr;</a>
                </div>
                <div class="hero-image-container animate-on-load">
                    <img src="https://imgs.search.brave.com/SgHepJwrvh8AW__HhlG_AbfFHEIy9CB2maxERWOp-jk/rs:fit:860:0:0:0/g:ce/aHR0cHM6Ly9tZWRp/YS5nZXR0eWltYWdl/cy5jb20vaWQvMTQz/ODg5MDc0NC92ZWN0/b3IvaW50cmF2ZW5v/dXMtYmFnLmpwZz9z/PTYxMng2MTImdz0w/Jms9MjAmYz1CUVY1/cE1sU1E3M3NYS1FL/TS1XWjdyd1FRelZS/OGpXdm9LZmhod2dq/YjFBPQ" alt="Blood Donation Concept" class="hero-image" onerror="this.onerror=null;this.src='https://placehold.co/500x350/EFEFEF/333333?text=Image+Not+Found';">
                </div>
            </div>
        </section>

        <section id="about" class="content-section container">
            <h2 class="section-title">Who We Are</h2>
            <p class="section-subtitle">We are a non-profit organization from Dibrugarh, Assam, India, dedicated to creating a bridge between blood donors and recipients. Our mission is to ensure that no one suffers due to a shortage of blood.</p>
        </section>

        <section id="how-it-works" class="how-it-works-section">
            <div class="container">
                <h2 class="section-title">How It Works</h2>
                <div class="steps-container">
                    <div class="step">
                        <div class="step-icon"><i class="fas fa-search"></i></div>
                        <h3>1. Search for a Donor</h3>
                        <p>Use our simple search to find donors by blood group in your city.</p>
                    </div>
                    <div class="step">
                        <div class="step-icon"><i class="fas fa-paper-plane"></i></div>
                        <h3>2. Send a Request</h3>
                        <p>Select a donor and send a secure, private request for contact.</p>
                    </div>
                    <div class="step">
                        <div class="step-icon"><i class="fas fa-hands-helping"></i></div>
                        <h3>3. Connect & Save a Life</h3>
                        <p>The donor receives your request and can connect with you directly.</p>
                    </div>
                </div>
            </div>
        </section>
        
        <section id="contact" class="content-section container">
            <h2 class="section-title">Get In Touch</h2>
            <p class="section-subtitle">Have questions or need support? We're here to help.</p>
            <div class="contact-container">
                <div class="contact-info">
                    <h3>Contact Information</h3>
                    <p><i class="fas fa-map-marker-alt"></i> Dibrugarh, Assam, India</p>
                    <p><i class="fas fa-envelope"></i> contact@bloodlink.org</p>
                    <p><i class="fas fa-phone"></i> +91 123 456 7890</p>
                </div>
                <form action="index.php#contact" method="post" class="contact-form">
                    <?php if (!empty($contact_errors)): ?>
                        <div class="message error"><?php foreach ($contact_errors as $error) echo "<p>$error</p>"; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($contact_success_message)): ?>
                        <div class="message success"><p><?php echo $contact_success_message; ?></p></div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" name="name" id="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Your Email</label>
                        <input type="email" name="email" id="email" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Your Message</label>
                        <textarea name="message" id="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" name="contact_submit" class="btn btn-primary">Send Message</button>
                </form>
            </div>
        </section>

    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> BloodLink Directory. All Rights Reserved.</p>
        </div>
    </footer>
    
    <!-- MODAL HTML -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2>Become a Donor</h2>
            <p>Create an account to join our community of lifesavers.</p>
            <br>
            <?php if (!empty($errors) && isset($_POST['register'])): ?>
                <div class="message error"><?php foreach ($errors as $error) echo "<p>$error</p>"; ?></div>
            <?php endif; ?>
            <?php if (!empty($success_message) && isset($_POST['register'])): ?>
                <div class="message success"><p><?php echo $success_message; ?></p></div>
            <?php endif; ?>
            
            <!-- ### UPDATED REGISTRATION FORM ### -->
            <form action="index.php" method="post">
                <!-- Full Name -->
                <div class="form-group">
                    <label for="reg_full_name">Full Name</label>
                    <input type="text" name="full_name" id="reg_full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <div class="label-container">
                        <label for="reg_email">Email</label>
                        <span class="info-icon">
                            <i class="fas fa-info-circle"></i>
                            <span class="tooltip-text">Must be a valid email address (e.g., name@example.com).</span>
                        </span>
                    </div>
                    <input type="email" name="email" id="reg_email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>

                <!-- Date of Birth -->
                <div class="form-group">
                    <label for="reg_dob">Date of Birth</label>
                    <input type="date" name="date_of_birth" id="reg_dob" max="<?php echo $today; ?>" value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>" required>
                </div>
                
                <!-- Password -->
                <div class="form-group">
                     <div class="label-container">
                        <label for="reg_password">Password</label>
                         <span class="info-icon">
                            <i class="fas fa-info-circle"></i>
                            <span class="tooltip-text">Must be over 6 characters, including at least one number and one special symbol.</span>
                        </span>
                    </div>
                    <input type="password" name="password" id="reg_password" required>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="reg_confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" id="reg_confirm_password" required>
                </div>

                <!-- Phone Number -->
                <div class="form-group">
                    <div class="label-container">
                        <label for="reg_phone">Phone Number</label>
                        <span class="info-icon">
                            <i class="fas fa-info-circle"></i>
                            <span class="tooltip-text">Must be exactly 10 digits (e.g., 9876543210).</span>
                        </span>
                    </div>
                    <input type="tel" name="phone_number" id="reg_phone" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>" required>
                </div>

                <!-- Blood Group -->
                <div class="form-group">
                    <label for="reg_blood_group">Blood Group</label>
                    <select name="blood_group" id="reg_blood_group" required>
                        <option value="">Select Group</option>
                        <option value="A+" <?php if (isset($_POST['blood_group']) && $_POST['blood_group'] == 'A+') echo 'selected'; ?>>A+</option>
                        <option value="A-" <?php if (isset($_POST['blood_group']) && $_POST['blood_group'] == 'A-') echo 'selected'; ?>>A-</option>
                        <option value="B+" <?php if (isset($_POST['blood_group']) && $_POST['blood_group'] == 'B+') echo 'selected'; ?>>B+</option>
                        <option value="B-" <?php if (isset($_POST['blood_group']) && $_POST['blood_group'] == 'B-') echo 'selected'; ?>>B-</option>
                        <option value="AB+" <?php if (isset($_POST['blood_group']) && $_POST['blood_group'] == 'AB+') echo 'selected'; ?>>AB+</option>
                        <option value="AB-" <?php if (isset($_POST['blood_group']) && $_POST['blood_group'] == 'AB-') echo 'selected'; ?>>AB-</option>
                        <option value="O+" <?php if (isset($_POST['blood_group']) && $_POST['blood_group'] == 'O+') echo 'selected'; ?>>O+</option>
                        <option value="O-" <?php if (isset($_POST['blood_group']) && $_POST['blood_group'] == 'O-') echo 'selected'; ?>>O-</option>
                    </select>
                </div>
                
                <!-- Address -->
                <div class="form-group">
                    <label for="reg_address">City / Address</label>
                    <input type="text" name="address" id="reg_address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" required>
                </div>
                
                <button type="submit" name="register" class="btn btn-primary">Register</button>
            </form>
            <!-- ### END OF UPDATED FORM ### -->
        </div>
    </div>

    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2>Donor Login</h2>
            <p>Welcome back! Please log in to your account.</p>
            <br>
            <?php if (!empty($errors) && isset($_POST['login'])): ?>
                <div class="message error"><?php foreach ($errors as $error) echo "<p>$error</p>"; ?></div>
            <?php endif; ?>
            <form action="index.php" method="post">
                <div class="form-group"><label for="login_email">Email</label><input type="email" name="email" id="login_email" required></div>
                <div class="form-group"><label for="login_password">Password</label><input type="password" name="password" id="login_password" required></div>
                <div class="form-extra-link">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
                <button type="submit" name="login" class="btn btn-primary">Login</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            const registerModal = document.getElementById('registerModal');
            const loginModal = document.getElementById('loginModal');
            const registerBtn = document.getElementById('registerBtn');
            const loginBtn = document.getElementById('loginBtn');
            const mobileRegisterBtn = document.getElementById('mobileRegisterBtn');
            const mobileLoginBtn = document.getElementById('mobileLoginBtn');
            const closeBtns = document.querySelectorAll('.close-btn');
            
            // ### SIDEBAR LOGIC ###
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

            // --- Modal Logic ---
            const openModal = (modal) => modal.style.display = 'block';
            const closeModal = () => {
                if(registerModal) registerModal.style.display = 'none';
                if(loginModal) loginModal.style.display = 'none';
            };

            if(registerBtn) registerBtn.onclick = () => openModal(registerModal);
            if(loginBtn) loginBtn.onclick = () => openModal(loginModal);
            if(mobileRegisterBtn) mobileRegisterBtn.onclick = () => {
                closeSidebar();
                openModal(registerModal);
            };
            if(mobileLoginBtn) mobileLoginBtn.onclick = () => {
                closeSidebar();
                openModal(loginModal);
            };

            closeBtns.forEach(btn => btn.onclick = closeModal);
            window.onclick = function(event) {
                if (event.target == registerModal || event.target == loginModal) {
                    closeModal();
                }
            }
            
            <?php if (!empty($errors) && isset($_POST['register'])): ?>
                openModal(registerModal);
            <?php endif; ?>
            <?php if (!empty($errors) && isset($_POST['login'])): ?>
                openModal(loginModal);
            <?php endif; ?>
            <?php if (!empty($success_message) && isset($_POST['register'])): ?>
                // Keep the modal open to show the success message
                openModal(registerModal);
            <?php endif; ?>


            // --- Smooth Scrolling ---
            const navLinks = document.querySelectorAll('a[href^="#"]');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Close sidebar if a link is clicked
                    if (sidebar.classList.contains('active')) {
                        closeSidebar();
                    }
                    const targetId = this.getAttribute('href');
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 70, // Offset for fixed header
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // --- Scroll Reveal ---
            const sections = document.querySelectorAll('.content-section, .how-it-works-section');
            const revealSection = (entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = 1;
                        entry.target.style.transform = 'translateY(0)';
                        observer.unobserve(entry.target);
                    }
                });
            };
            const sectionObserver = new IntersectionObserver(revealSection, { root: null, threshold: 0.15 });
            sections.forEach(section => {
                section.style.opacity = 0;
                section.style.transform = 'translateY(20px)';
                section.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
                sectionObserver.observe(section);
_            });

        });
    </script>

</body>
</html>


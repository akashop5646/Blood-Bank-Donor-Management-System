<?php
// We start the session and include the database connection file.
session_start();
require_once 'config.php';

$errors = [];
$success_message = '';

// --- LOGIC TO HANDLE FORGOT PASSWORD FORM SUBMISSION ---
if (isset($_POST['reset_password'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Basic validation
    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // For security reasons, we don't reveal if the email was found or not.
        // We just show a generic success message.
        // In a real-world application, this is where you would generate a unique token,
        // save it to the database with an expiry date, and email a reset link to the user.
        
        // For this project, we will just display the success message.
        $success_message = "If an account with that email exists, a password reset link has been sent.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Blood Donor Directory</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

    <style>
        /* General Styling & Variables from index.php */
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
            line-height: 1.6;
            color: var(--dark-color);
            /* Thematic background consistent with profile.php */
            background-image: linear-gradient(rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.95)), url('https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1170&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .container { max-width: 1100px; margin: auto; overflow: hidden; padding: 0 2rem; }

        /* Simplified Header for this page */
        .header { background: var(--light-color); box-shadow: var(--shadow); width: 100%; }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 70px; }
        .logo { font-size: 1.8rem; font-weight: 700; color: var(--primary-color); text-decoration: none; }
        .logo i { transform: rotate(20deg); }
        
        .btn { display: inline-block; padding: 0.7rem 1.5rem; border-radius: var(--border-radius); text-decoration: none; font-weight: 600; transition: all 0.3s ease; cursor: pointer; }
        .btn-primary { background: var(--primary-color); color: var(--light-color); border: 1px solid var(--primary-color); }
        .btn-primary:hover { background: #c72525; }
        
        /* Form Container */
        .form-wrapper {
            flex: 1; /* Allows the wrapper to grow and fill available space */
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        .form-container {
            width: 100%;
            max-width: 500px;
            background: var(--light-color);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
        }
        .form-container h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }
        .form-container p {
            margin-bottom: 1.5rem;
            color: #666;
        }

        .form-group { margin-bottom: 1.5rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
        }
        .form-group input:focus { outline: none; border-color: var(--primary-color); }
        
        /* Message Styles */
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: var(--border-radius); text-align: left; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        .back-link {
            display: block;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: #555;
            text-decoration: none;
        }
        .back-link:hover {
            color: var(--primary-color);
        }
        
        /* Footer */
        .footer { background: var(--dark-color); color: var(--light-color); text-align: center; padding: 2rem 0; }
    </style>
</head>
<body>

    <header class="header">
        <nav class="navbar container">
            <a href="index.php" class="logo">Blood<i class="fas fa-tint"></i>Link</a>
            <a href="index.php" class="btn btn-primary">Back to Home</a>
        </nav>
    </header>

    <main class="form-wrapper">
        <div class="form-container">
            <h2>Forgot Your Password?</h2>
            <p>No problem. Enter your email address below and we will send you a link to reset it.</p>

            <?php if (!empty($errors)): ?>
                <div class="message error"><?php foreach ($errors as $error) echo "<p>$error</p>"; ?></div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo $success_message; ?></p></div>
            <?php endif; ?>

            <form action="forgot_password.php" method="post">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" required>
                </div>
                <button type="submit" name="reset_password" class="btn btn-primary" style="width: 100%;">Send Reset Link</button>
            </form>

            <a href="index.php" class="back-link">Remember your password? Login</a>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 BloodLink Directory. All Rights Reserved.</p>
        </div>
    </footer>

</body>
</html>

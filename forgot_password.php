<?php
// We start the session and include the database connection file.
session_start();
require_once 'config.php';

$errors = [];
$success_message = '';
$step = 1; // Start at step 1 (verification)
$email_verified = ''; // Store the email after verification

// --- LOGIC TO HANDLE VERIFICATION (STEP 1) ---
if (isset($_POST['verify_identity'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $date_of_birth = mysqli_real_escape_string($conn, $_POST['date_of_birth']);

    if (empty($email) || empty($phone_number) || empty($date_of_birth)) {
        $errors[] = "All verification fields are required.";
    } else {
        // Check if a donor exists with these exact details
        $stmt = $conn->prepare("SELECT id FROM donors WHERE email = ? AND phone_number = ? AND date_of_birth = ?");
        $stmt->bind_param("sss", $email, $phone_number, $date_of_birth);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // Identity verified! Move to step 2
            $step = 2;
            $email_verified = $email; // Store the email to use in the next step
        } else {
            $errors[] = "The information provided does not match any account.";
        }
        $stmt->close();
    }
}

// --- LOGIC TO HANDLE PASSWORD RESET (STEP 2) ---
if (isset($_POST['reset_password'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];
    $step = 2; // Keep them on step 2 if there's an error
    $email_verified = $email; // Keep the email field populated

    if (empty($new_password) || empty($confirm_new_password)) {
        $errors[] = "Please enter and confirm your new password.";
    } elseif ($new_password !== $confirm_new_password) {
        $errors[] = "The new passwords do not match.";
    } else {
        // Validation passed, update the password
        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE donors SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed_new_password, $email);
        
        if ($stmt->execute()) {
            $success_message = "Password has been reset successfully. You can now log in.";
            $step = 3; // Move to a final success step
        } else {
            $errors[] = "Failed to update password. Please try again.";
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
    <title>Forgot Password - Blood Donor Directory</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Using Font Awesome 6 for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" xintegrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

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
        .form-group input[disabled] { background-color: #e9ecef; }
        
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

            <?php if (!empty($errors)): ?>
                <div class="message error"><?php foreach ($errors as $error) echo "<p>$error</p>"; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <!-- Step 3: Success Message -->
                <div class="message success"><p><?php echo $success_message; ?></p></div>
                <h2>Password Reset!</h2>
                <a href="index.php" class="btn btn-primary" style="width: 100%;">Click to Login</a>
            
            <?php elseif ($step === 2): ?>
                <!-- Step 2: Set New Password -->
                <h2>Set New Password</h2>
                <p>Hello! Please enter your new password below.</p>
                
                <form action="forgot_password.php" method="post">
                    <!-- We hide the email in the form so it can be passed to the next step -->
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email_verified); ?>">
                    
                    <div class="form-group">
                        <label for="email_display">Email</label>
                        <input type="email" id="email_display" value="<?php echo htmlspecialchars($email_verified); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" name="new_password" id="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_new_password">Confirm New Password</label>
                        <input type="password" name="confirm_new_password" id="confirm_new_password" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary" style="width: 100%;">Reset Password</button>
                </form>

            <?php else: ?>
                <!-- Step 1: Verify Identity -->
                <h2>Forgot Your Password?</h2>
                <p>Please verify your identity by providing the details associated with your account.</p>

                <form action="forgot_password.php" method="post">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" id="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <input type="tel" name="phone_number" id="phone_number" required>
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="date_of_birth" required>
                    </div>
                    <button type="submit" name="verify_identity" class="btn btn-primary" style="width: 100%;">Verify Identity</button>
                </form>
                <a href="index.php" class="back-link">Remember your password? Login</a>
            <?php endif; ?>

        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> BloodLink Directory. All Rights Reserved.</p>
        </div>
    </footer>

</body>
</html>

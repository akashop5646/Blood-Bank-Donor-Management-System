<?php
session_start();
require_once '../config.php'; 

$errors = [];
$success_message = '';

// If the form is submitted
if (isset($_POST['reset_password'])) {
    // Sanitize all inputs
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    // --- Validation ---
    if (empty($username) || empty($email) || empty($phone_number) || empty($new_password) || empty($confirm_new_password)) {
        $errors[] = "All fields are required.";
    } elseif ($new_password !== $confirm_new_password) {
        $errors[] = "The new passwords do not match.";
    } else {
        // Check if an admin with the provided details exists
        $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ? AND email = ? AND phone_number = ?");
        $stmt->bind_param("sss", $username, $email, $phone_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // Admin found, proceed to update the password
            $admin = $result->fetch_assoc();
            $admin_id = $admin['id'];
            
            // Hash the new password for security
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update_stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_new_password, $admin_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Password has been reset successfully. You can now log in with your new password.";
            } else {
                $errors[] = "Failed to update password. Please try again.";
            }
            $update_stmt->close();
        } else {
            // No admin found with the matching details
            $errors[] = "The provided information is incorrect. Please check and try again.";
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
    <title>Admin Forgot Password - BloodLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #D92A2A; --light-color: #fff; --font-family: 'Poppins', sans-serif; --border-radius: 8px; }
        body {
            font-family: var(--font-family);
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem 0;
            margin: 0;
        }
        .form-container {
            background: var(--light-color);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .logo { font-size: 2rem; font-weight: 700; color: var(--primary-color); text-decoration: none; margin-bottom: 1rem; display: block; }
        .form-container h2 { font-size: 1.8rem; margin-bottom: 0.5rem; }
        .form-container p { margin-bottom: 1.5rem; color: #666; }
        .form-group { margin-bottom: 1.5rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: var(--border-radius); font-family: inherit; }
        .btn { display: block; width: 100%; padding: 0.8rem; border: none; background: var(--primary-color); color: var(--light-color); border-radius: var(--border-radius); cursor: pointer; font-size: 1rem; font-weight: 600; transition: background-color 0.3s ease; }
        .btn:hover { background-color: #c72525; }
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: var(--border-radius); }
        .message.error { background-color: #f8d7da; color: #721c24; }
        .message.success { background-color: #d4edda; color: #155724; }
        .back-link { display: block; margin-top: 1.5rem; font-size: 0.9rem; color: #555; text-decoration: none; }
        .back-link:hover { color: var(--primary-color); }
    </style>
</head>
<body>
    <div class="form-container">
        <a href="../index.php" class="logo">BloodLink Admin</a>
        <h2>Reset Password</h2>
        <p>Please verify your identity to reset your password.</p>
        
        <?php if (!empty($errors)): ?>
            <div class="message error"><?php foreach ($errors as $error) echo "<p>$error</p>"; ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="message success"><p><?php echo $success_message; ?></p></div>
        <?php endif; ?>

        <form action="admin_forgot.php" method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" required>
            </div>
            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input type="tel" name="phone_number" id="phone_number" required>
            </div>
            <hr style="border: 1px solid #eee; margin: 2rem 0;">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" name="new_password" id="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_new_password">Confirm New Password</label>
                <input type="password" name="confirm_new_password" id="confirm_new_password" required>
            </div>
            <button type="submit" name="reset_password" class="btn">Reset Password</button>
        </form>
        <a href="admin_login.php" class="back-link">Back to Login</a>
    </div>
</body>
</html>

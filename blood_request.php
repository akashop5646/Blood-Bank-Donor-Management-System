<?php // We start the session to handle user login state. This must be at the very top.
session_start();
// We include the database connection file.
require_once 'config.php';

// If the user is not logged in, redirect them to the index page.
if (!isset($_SESSION['donor_id'])) {
    header("Location: index.php");
    exit();
}

// Get the logged-in donor's ID and details from the session.
$donor_id = $_SESSION['donor_id'];
$donor_name = $_SESSION['donor_name'];
$success_message = '';
$errors = [];

// --- LOGIC TO HANDLE INCOMING STATUS UPDATES (ACCEPT/DENY) ---
if (isset($_POST['update_status'])) {
    $request_id = (int)$_POST['request_id'];
    $new_status = $_POST['new_status'];

    // Validate the new status to ensure it's either 'Accepted' or 'Denied'
    if ($new_status === 'Accepted' || $new_status === 'Denied') {

        $stmt = null;
        if ($new_status === 'Accepted') {
            // ### MODIFIED: Get the expiry date from the form POST ###
            if (empty($_POST['expiry_date'])) {
                $errors[] = "You must select a donation date to accept the request.";
            } else {
                $expiry_date = $_POST['expiry_date'];
                // If ACCEPTED, set the status and the user-selected expiry date
                $stmt = $conn->prepare("UPDATE donor_requests SET status = ?, expiry_date = ? WHERE id = ? AND donor_id = ?");
                $stmt->bind_param("ssii", $new_status, $expiry_date, $request_id, $donor_id);
            }
        } else {
            // If DENIED, set the status and clear any existing expiry date
            $stmt = $conn->prepare("UPDATE donor_requests SET status = ?, expiry_date = NULL WHERE id = ? AND donor_id = ?");
            $stmt->bind_param("sii", $new_status, $request_id, $donor_id);
        }

        // Only execute if $stmt was prepared (i.e., no errors)
        if ($stmt && $stmt->execute()) {
            $success_message = "Request status updated successfully.";
        } else if (empty($errors)) {
            $errors[] = "Failed to update status.";
        }
        if ($stmt) {
            $stmt->close();
        }
    }
}

// --- LOGIC TO HANDLE OUTGOING REQUEST REMOVAL ---
if (isset($_POST['remove_request'])) {
    $request_id = (int)$_POST['request_id'];

    // Prepare a statement to delete the request
    // CRITICAL: We check that the request was made by the logged-in user to ensure they can only delete their own requests.
    $stmt = $conn->prepare("DELETE FROM donor_requests WHERE id = ? AND requester_name = ?");
    $stmt->bind_param("is", $request_id, $donor_name);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $success_message = "Your request has been successfully removed.";
        } else {
            $errors[] = "Could not remove the request. It may have already been processed.";
        }
    } else {
        $errors[] = "Failed to remove the request.";
    }
    $stmt->close();
}

// --- FETCH INCOMING REQUESTS ---
// We now select the expiry_date as well
$incoming_requests_sql = "SELECT id, requester_name, requester_contact, message, request_date, status, expiry_date FROM donor_requests WHERE donor_id = ?";
$stmt_incoming = $conn->prepare($incoming_requests_sql);
$stmt_incoming->bind_param("i", $donor_id);
$stmt_incoming->execute();
$incoming_requests_result = $stmt_incoming->get_result();
$stmt_incoming->close();

// --- FETCH OUTGOING REQUESTS ---
// We now select the expiry_date as well
$outgoing_requests_sql = "SELECT dr.id, d.full_name as donor_name, dr.message, dr.request_date, dr.status, dr.expiry_date
                           FROM donor_requests dr
                           JOIN donors d ON dr.donor_id = d.id
                           WHERE dr.requester_name = ?";
$stmt_outgoing = $conn->prepare($outgoing_requests_sql);
$stmt_outgoing->bind_param("s", $donor_name);
$stmt_outgoing->execute();
$outgoing_requests_result = $stmt_outgoing->get_result();
$stmt_outgoing->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Blood Requests - Blood Donor Directory</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Use Font Awesome CSS from CDN (reliable and includes brands) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #D92A2A;
            --secondary-color: #f8f9fa;
            --dark-color: #212529;
            --light-color: #fff;
            --font-family: 'Poppins', sans-serif;
            --border-radius: 8px;
            --shadow: 0 4px 15px rgba(0,0,0,0.07);
            --status-pending: #007bff;
            --status-accepted: #28a745;
            --status-denied: #dc3545;
            --status-expired: #6c757d; /* Added new color for expired status */
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font-family);
            line-height: 1.6;
            color: var(--dark-color);
            background-color: var(--secondary-color);
        }

        .container { max-width: 1100px; margin: auto; overflow: hidden; padding: 0 2rem; }

        /* Header Styles */
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
        .btn-secondary { background: transparent; color: var(--dark-color); border: 1px solid #ddd; }

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

        /* Main Content */
        .main-wrapper { padding-top: 100px; padding-bottom: 40px; }
        .page-title { font-size: 2.5rem; font-weight: 600; margin-bottom: 2rem; text-align: center; }

        .requests-container { display: grid; grid-template-columns: 1fr; gap: 2rem; }
        .request-box { background: var(--light-color); padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--shadow); }
        .request-box h2 { font-size: 1.8rem; margin-bottom: 1.5rem; border-bottom: 2px solid #f0f0f0; padding-bottom: 0.5rem; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { font-weight: 600; }
        .status-badge { padding: 0.25rem 0.6rem; border-radius: 1rem; font-size: 0.8rem; font-weight: 600; color: var(--light-color); text-transform: capitalize; }
        .status-Pending { background-color: var(--status-pending); }
        .status-Accepted { background-color: var(--status-accepted); }
        .status-Denied { background-color: var(--status-denied); }
        .status-Expired { background-color: var(--status-expired); } /* Added style for expired */
        .no-requests { text-align: center; padding: 2rem; color: #777; }

        .action-buttons { display: flex; gap: 0.5rem; }

        .action-btn {
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.3s;
            font-size: 1.5rem; /* Make symbols larger */
            font-weight: bold;
            line-height: 1; /* Better vertical alignment */
            text-decoration: none; /* For new <a> buttons */
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .action-btn i { /* ensure the icon is visible and aligned */
            vertical-align: middle;
            font-size: 1.2em;
            line-height: 1;
        }
        .action-btn.accept { color: var(--status-accepted); }
        .action-btn.accept:hover { background-color: #28a74520; }
        .action-btn.deny { color: var(--status-denied); }
        .action-btn.deny:hover { background-color: #dc354520; }
        .action-btn.remove { color: var(--primary-color); }
        .action-btn.remove:hover { background-color: #d92a2a20; }

        /* ### NEW: Styles for WhatsApp button ### */
        .action-btn.whatsapp { color: #25D366; font-size: 1.4rem; }
        .action-btn.whatsapp:hover { background-color: #25D36620; }

        .message { padding: 1rem; margin-bottom: 1rem; border-radius: var(--border-radius); }
        .message.error { background-color: #f8d7da; color: #721c24; }
        .message.success { background-color: #d4edda; color: #155724; }

        /* Styles for Modal and Date Form */
        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 30px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: var(--border-radius); position: relative; animation: slideIn 0.5s ease-out; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: black; }
        @keyframes slideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-content h2 { margin-bottom: 1rem; }
        .modal-content p { color: #666; margin-bottom: 1.5rem; }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input[type="date"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
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
        <a href="index.php" class="logo">Blood<i class="fas fa-tint"></i>Link</a>

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

<main class="main-wrapper">
    <div class="container">
        <h1 class="page-title">My Blood Requests</h1>

        <?php if (!empty($errors)): ?>
            <div class="message error"><?php foreach ($errors as $error) echo "<p>$error</p>"; ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="message success"><p><?php echo $success_message; ?></p></div>
        <?php endif; ?>

        <div class="requests-container">
            <!-- Incoming Requests -->
            <div class="request-box">
                <h2><i class="fas fa-inbox"></i> Incoming Requests</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>From</th>
                                <th>Contact</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Expiry Date</th>
                                <th>Contact</th> <!-- ### NEW: Column for WhatsApp ### -->
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($incoming_requests_result && $incoming_requests_result->num_rows > 0): ?>
                                <?php while($req = $incoming_requests_result->fetch_assoc()): ?>
                                    <?php
                                        // Check for expiry
                                        $status = $req['status'];
                                        $expiry_date = $req['expiry_date'];
                                        // Check if today's date is past the expiry date (ignoring time)
                                        if ($status === 'Accepted' && $expiry_date !== NULL && (strtotime(date('Y-m-d')) > strtotime($expiry_date))) {
                                            $status = 'Expired';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($req['requester_name']); ?></td>
                                        <td><?php echo htmlspecialchars($req['requester_contact']); ?></td>
                                        <td><?php echo htmlspecialchars($req['message']); ?></td>
                                        <td><?php echo date("d M, Y", strtotime($req['request_date'])); ?></td>
                                        <td><span class="status-badge status-<?php echo $status; ?>"><?php echo $status; ?></span></td>
                                        <td><?php echo ($req['expiry_date']) ? date("d M, Y", strtotime($req['expiry_date'])) : 'N/A'; ?></td>
                                        <!-- ### NEW: WhatsApp button column ### -->
                                        <td>
                                            <?php
                                            if ($status === 'Accepted') {
                                                // Sanitize phone number
                                                $phone_numeric = preg_replace('/[^0-9]/', '', $req['requester_contact']);

                                                // Create WhatsApp link (prepend 91 if it's a 10-digit number)
                                                $wa_phone = $phone_numeric;
                                                if (strlen($wa_phone) == 10) {
                                                    $wa_phone = '91' . $wa_phone; // Assuming +91 for India
                                                }
                                                $wa_link = "https://wa.me/" . $wa_phone;

                                                // Use Font Awesome brand icon (fab fa-whatsapp) - font loaded from CDN above
                                                echo '<a href="' . htmlspecialchars($wa_link) . '" class="action-btn whatsapp" title="Contact on WhatsApp" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp" aria-hidden="true"></i></a>';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php
                                                // Show 'Accept' button if status is Pending or Denied
                                                if ($status === 'Pending' || $status === 'Denied') {
                                                    // This button now opens the modal
                                                    echo '<button type="button" class="action-btn accept open-accept-modal" title="Accept Request" data-request-id="' . $req['id'] . '">&#10004;</button>';
                                                }
                                                // Show 'Deny' button if status is Pending or Accepted
                                                if ($status === 'Pending' || $status === 'Accepted') {
                                                    echo '<form action="blood_request.php" method="post" style="display: inline;">
                                                            <input type="hidden" name="request_id" value="' . $req['id'] . '">
                                                            <input type="hidden" name="new_status" value="Denied">
                                                            <button type="submit" name="update_status" class="action-btn deny" title="Deny Request">&#10006;</button>
                                                          </form>';
                                                }
                                                // ### WhatsApp button removed from here ###
                                                ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <!-- ### NEW: Colspan is now 8 ### -->
                                    <td colspan="8" class="no-requests">You have no incoming requests.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Outgoing Requests -->
            <div class="request-box">
                <h2><i class="fas fa-paper-plane"></i> Outgoing Requests</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>To</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Expiry Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($outgoing_requests_result && $outgoing_requests_result->num_rows > 0): ?>
                                <?php while($req = $outgoing_requests_result->fetch_assoc()): ?>
                                    <?php
                                        // Check for expiry
                                        $status = $req['status'];
                                        $expiry_date = $req['expiry_date'];
                                        // Check if today's date is past the expiry date (ignoring time)
                                        if ($status === 'Accepted' && $expiry_date !== NULL && (strtotime(date('Y-m-d')) > strtotime($expiry_date))) {
                                            $status = 'Expired';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($req['donor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($req['message']); ?></td>
                                        <td><?php echo date("d M, Y", strtotime($req['request_date'])); ?></td>
                                        <td><span class="status-badge status-<?php echo $status; ?>"><?php echo $status; ?></span></td>
                                        <td><?php echo ($expiry_date) ? date("d M, Y", strtotime($expiry_date)) : 'N/A'; ?></td>
                                        <td>
                                            <?php if ($req['status'] === 'Pending'): ?>
                                                <form action="blood_request.php" method="post">
                                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                    <button type="submit" name="remove_request" class="action-btn remove" title="Remove Request"><i class="fas fa-trash"></i></button>
                                                </form>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-requests">You have not sent any requests.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal for Accepting Request and Setting Date -->
<div id="acceptModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h2>Set Donation Date</h2>
        <p>Please select the date you plan to donate. This will be the expiry date for this request.</p>
        <form action="blood_request.php" method="post">
            <input type="hidden" name="request_id" id="modal_request_id">
            <input type="hidden" name="new_status" value="Accepted">

            <div class="form-group">
                <label for="expiry_date">Donation Date</label>
                <input type="date" name="expiry_date" id="expiry_date" required>
            </div>

            <button type="submit" name="update_status" class="btn btn-primary">Confirm Acceptance</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.querySelector('.hamburger-menu');
        const sidebar = document.querySelector('.sidebar');
        const sidebarClose = document.querySelector('.sidebar-close-btn');
        const overlay = document.querySelector('.overlay');
        const sidebarLinks = document.querySelectorAll('.sidebar .nav-link');

        const openSidebar = () => { sidebar.classList.add('active'); overlay.classList.add('active'); };
        const closeSidebar = () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); };

        hamburger.addEventListener('click', openSidebar);
        sidebarClose.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);
        sidebarLinks.forEach(link => link.addEventListener('click', closeSidebar));

        // Modal JavaScript
        const acceptModal = document.getElementById('acceptModal');
        const openAcceptModalBtns = document.querySelectorAll('.open-accept-modal');
        const closeBtns = document.querySelectorAll('.close-btn');

        // Function to close all modals
        const closeModal = () => {
            if (acceptModal) acceptModal.style.display = 'none';
        };

        // Add click listener to all "Accept" buttons
        openAcceptModalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const requestId = btn.dataset.requestId;
                document.getElementById('modal_request_id').value = requestId;

                // Set the minimum date for the date picker to today
                document.getElementById('expiry_date').min = new Date().toISOString().split("T")[0];

                acceptModal.style.display = 'block';
            });
        });

        // Add click listener to all close buttons
        closeBtns.forEach(btn => btn.onclick = closeModal);

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            if (event.target == acceptModal) {
                closeModal();
            }
        };
    });
</script>
</body>
</html>

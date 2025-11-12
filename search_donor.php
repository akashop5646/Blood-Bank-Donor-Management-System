<?php
session_start();
require_once 'config.php';

$errors = [];
$success_message = '';
$logged_in_user = null;

// Check if a user is logged in and fetch their details
if (isset($_SESSION['donor_id'])) {
    // ### MODIFIED: Fetched email and address for the logged-in user ###
    $stmt = $conn->prepare("SELECT id, full_name, phone_number, email, address FROM donors WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['donor_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $logged_in_user = $result->fetch_assoc();
    $stmt->close();
}

// --- LOGIC TO HANDLE REQUEST SUBMISSION ---
if (isset($_POST['submit_request'])) {
    if (!$logged_in_user) {
        $errors[] = "You must be logged in to send a request.";
    } else {
        $donor_id = (int)$_POST['donor_id'];
        $message = mysqli_real_escape_string($conn, $_POST['message']);
        
        // The requester is the currently logged-in user
        $requester_name = $logged_in_user['full_name'];
        $requester_contact = $logged_in_user['phone_number'];

        if (empty($message)) {
            $errors[] = "A message is required to send a request.";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO donor_requests (donor_id, requester_name, requester_contact, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $donor_id, $requester_name, $requester_contact, $message);
            if ($stmt->execute()) {
                $success_message = "Your request has been sent successfully!";
            } else {
                $errors[] = "There was an error sending your request. Please try again.";
            }
            $stmt->close();
        }
    }
}


// --- Function to calculate age from Date of Birth ---
function calculateAge($dob) {
    if ($dob) {
        $birthDate = new DateTime($dob);
        $today = new DateTime('today');
        $age = $birthDate->diff($today)->y;
        return $age;
    }
    return 'N/A';
}

// --- Fetch Donors Logic ---
$sql = "SELECT id, full_name, email, phone_number, blood_group, address, date_of_birth FROM donors WHERE status = 'active'";

// ### NEW: Exclude the logged-in user from the search results ###
if (isset($_SESSION['donor_id'])) {
    $current_donor_id = (int)$_SESSION['donor_id'];
    $sql .= " AND id != $current_donor_id";
}


if (isset($_GET['search'])) {
    $blood_group_search = mysqli_real_escape_string($conn, $_GET['blood_group']);
    $address_search = mysqli_real_escape_string($conn, $_GET['address']);

    if (!empty($blood_group_search)) {
        $sql .= " AND blood_group = '$blood_group_search'";
    }
    if (!empty($address_search)) {
        $sql .= " AND address LIKE '%$address_search%'";
    }
}

$donors_result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Donors - Blood Donor Directory</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
            line-height: 1.6;
            color: var(--dark-color);
            background-color: var(--secondary-color);
        }

        .container { max-width: 1100px; margin: auto; overflow: hidden; padding: 0 2rem; }

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
        
        .main-wrapper { padding-top: 100px; padding-bottom: 40px; }

        .search-container { background: var(--light-color); padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--shadow); margin-bottom: 2rem; }
        .search-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: flex-end; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: var(--border-radius); font-family: inherit; }
        .form-group input[disabled] { background-color: #e9ecef; cursor: not-allowed; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        
        /* ### NEW: Styles for the requests link container ### */
        .user-actions-container {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
            text-align: center;
        }

        .donor-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
        .donor-card { background: var(--light-color); border-radius: var(--border-radius); box-shadow: var(--shadow); display: flex; flex-direction: column; transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .donor-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid #f0f0f0; }
        .card-header h3 { font-size: 1.2rem; color: var(--primary-color); display: flex; align-items: center; }
        .card-header h3 i { margin-right: 10px; }
        .card-body { padding: 1rem 1.5rem 1.5rem; flex-grow: 1; }
        .card-body ul { list-style: none; }
        .card-body li { padding: 0.6rem 0; display: flex; align-items: center; font-size: 0.9rem; }
        .card-body li i { color: var(--primary-color); width: 25px; text-align: center; margin-right: 10px; }
        .card-body li strong { margin-right: 5px; }
        .card-footer { padding: 1rem 1.5rem; border-top: 1px solid #f0f0f0; }
        .card-footer .btn { width: 100%; }

        .no-donors { background: var(--light-color); padding: 2rem; text-align: center; border-radius: var(--border-radius); box-shadow: var(--shadow); }

        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 30px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: var(--border-radius); position: relative; animation: slideIn 0.5s ease-out; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: black; }
        @keyframes slideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: var(--border-radius); }
        .message.error { background-color: #f8d7da; color: #721c24; }
        .message.success { background-color: #d4edda; color: #155724; }
        .modal-content h2 { margin-bottom: 1rem; }
        .modal-content p { color: #666; margin-bottom: 1.5rem; }
        .modal-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; }

        @media (max-width: 992px) {
            .header .nav-menu, .header .nav-actions { display: none; }
            .hamburger-menu { display: block; }
        }
        @media (max-width: 768px) {
            .search-form { grid-template-columns: 1fr; }
            .search-form button { width: 100%; }
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
                <li class="active"><a href="search_donor.php" class="nav-link">Search Donors</a></li>
                <li><a href="index.php#contact" class="nav-link">Contact Us</a></li>
            </ul>
            <div class="nav-actions">
                <?php if (isset($_SESSION['donor_id'])): ?>
                    <a href="profile.php" class="nav-greeting-link" title="My Profile">Hi, <?php echo htmlspecialchars(explode(' ', $_SESSION['donor_name'])[0]); ?></a>
                    <a href="profile.php" class="profile-icon-btn" title="My Profile"><i class="fas fa-user-circle"></i></a>
                    <a href="index.php?logout=true" class="btn btn-secondary">Logout</a>
                <?php else: ?>
                    <a href="index.php" class="btn btn-primary">Home</a>
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
            <li><a href="index.php#about" class="nav-link">About Us</a></li>
            <li><a href="index.php#how-it-works" class="nav-link">How It Works</a></li>
            <li class="active"><a href="search_donor.php" class="nav-link">Search Donors</a></li>
            <li><a href="index.php#contact" class="nav-link">Contact Us</a></li>
        </ul>
        <div class="nav-actions">
            <?php if (isset($_SESSION['donor_id'])): ?>
                <a href="profile.php" class="btn btn-secondary">My Profile</a>
                <a href="index.php?logout=true" class="btn btn-primary">Logout</a>
            <?php else: ?>
                 <a href="index.php" class="btn btn-primary">Home</a>
            <?php endif; ?>
        </div>
    </div>


    <main class="main-wrapper">
        <div class="container">
            <!-- Search Bar -->
            <div class="search-container">
                <form action="search_donor.php" method="get" class="search-form">
                    <div class="form-group">
                        <label for="blood_group">Blood Group</label>
                        <select name="blood_group" id="blood_group">
                            <option value="">Any</option>
                            <option value="A+">A+</option><option value="A-">A-</option>
                            <option value="B+">B+</option><option value="B-">B-</option>
                            <option value="AB+">AB+</option><option value="AB-">AB-</option>
                            <option value="O+">O+</option><option value="O-">O-</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="address">City / Address</label>
                        <input type="text" name="address" id="address" placeholder="e.g., Dibrugarh">
                    </div>
                    <button type="submit" name="search" class="btn btn-primary">Search</button>
                </form>

                <!-- ### NEW: "My Requests" button container ### -->
                <?php if (isset($_SESSION['donor_id'])): ?>
                    <div class="user-actions-container">
                        <a href="blood_request.php" class="btn btn-secondary">
                            <i class="fas fa-history"></i> View My Requests
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($errors) && isset($_POST['submit_request'])): ?>
                <div class="message error" style="margin-bottom: 1.5rem;"><?php foreach ($errors as $error) echo "<p>$error</p>"; ?></div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="message success" style="margin-bottom: 1.5rem;"><p><?php echo $success_message; ?></p></div>
            <?php endif; ?>

            <div class="donor-grid">
                <?php if ($donors_result && $donors_result->num_rows > 0): ?>
                    <?php while($donor = $donors_result->fetch_assoc()): ?>
                        <div class="donor-card">
                            <div class="card-header">
                                <h3><i class="fas fa-user"></i><?php echo htmlspecialchars($donor['full_name']); ?></h3>
                            </div>
                            <div class="card-body">
                                <ul>
                                    <li><i class="fas fa-tint"></i> <strong>Blood Group:</strong> <?php echo htmlspecialchars($donor['blood_group']); ?></li>
                                    <li><i class="fas fa-birthday-cake"></i> <strong>Age:</strong> <?php echo calculateAge($donor['date_of_birth']); ?></li>
                                    <li><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($donor['email']); ?></li>
                                    <li><i class="fas fa-map-marker-alt"></i> <strong>Address:</strong> <?php echo htmlspecialchars($donor['address']); ?></li>
                                </ul>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-primary request-btn" 
                                        data-donor-id="<?php echo $donor['id']; ?>" 
                                        data-donor-name="<?php echo htmlspecialchars($donor['full_name']); ?>">
                                    <i class="fas fa-paper-plane"></i> Request
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-donors">
                        <h3>No Donors Found</h3>
                        <p>No active donors match your search criteria. Please try again later or broaden your search.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Request Modal (for logged-in users) -->
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2>Send Request to <span id="modalDonorName"></span></h2>
            <p>Your details below will be shared with the donor.</p>
            <form action="search_donor.php" method="post">
                <input type="hidden" name="donor_id" id="modalDonorId">
                <div class="form-group">
                    <label for="requester_name">Your Name</label>
                    <input type="text" id="requester_name" value="<?php echo htmlspecialchars($logged_in_user['full_name'] ?? ''); ?>" disabled>
                </div>
                <div class="form-group">
                    <label for="requester_email">Your Email</label>
                    <input type="text" id="requester_email" value="<?php echo htmlspecialchars($logged_in_user['email'] ?? ''); ?>" disabled>
                </div>
                <div class="form-group">
                    <label for="requester_contact">Your Contact Number</label>
                    <input type="text" id="requester_contact" value="<?php echo htmlspecialchars($logged_in_user['phone_number'] ?? ''); ?>" disabled>
                </div>
                <div class="form-group">
                    <label for="requester_address">Your Address</label>
                    <input type="text" id="requester_address" value="<?php echo htmlspecialchars($logged_in_user['address'] ?? ''); ?>" disabled>
                </div>
                <div class="form-group">
                    <label for="message">Message (Purpose of request)</label>
                    <textarea name="message" id="message" rows="4" required placeholder="e.g., Urgent need for a family member at XYZ Hospital."></textarea>
                </div>
                <button type="submit" name="submit_request" class="btn btn-primary">Confirm Request</button>
            </form>
        </div>
    </div>

    <!-- Login Required Modal (for guests) -->
    <div id="loginRequiredModal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2>Login Required</h2>
            <p>You must be logged in to send a request to a donor. Please log in or create an account.</p>
            <div class="modal-actions">
                <a href="index.php" class="btn btn-secondary">Register</a>
                <a href="index.php" class="btn btn-primary">Login</a>
            </div>
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

            const requestModal = document.getElementById('requestModal');
            const loginRequiredModal = document.getElementById('loginRequiredModal');
            const requestBtns = document.querySelectorAll('.request-btn');
            const closeBtns = document.querySelectorAll('.close-btn');
            
            const isUserLoggedIn = <?php echo isset($_SESSION['donor_id']) ? 'true' : 'false'; ?>;

            requestBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    if (isUserLoggedIn) {
                        const donorId = btn.dataset.donorId;
                        const donorName = btn.dataset.donorName;
                        document.getElementById('modalDonorId').value = donorId;
                        document.getElementById('modalDonorName').innerText = donorName;
                        requestModal.style.display = 'block';
                    } else {
                        loginRequiredModal.style.display = 'block';
                    }
                });
            });

            const closeModal = () => {
                if(requestModal) requestModal.style.display = 'none';
                if(loginRequiredModal) loginRequiredModal.style.display = 'none';
            };

            closeBtns.forEach(btn => btn.onclick = closeModal);
            window.onclick = function(event) {
                if (event.target == requestModal || event.target == loginRequiredModal) {
                    closeModal();
                }
            };
        });
    </script>
</body>
</html>

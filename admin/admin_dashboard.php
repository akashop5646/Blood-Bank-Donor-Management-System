<?php
// We must start the session on all admin pages.
session_start();

// 1. REQUIRE THE DATABASE CONNECTION
// We are in the 'admin' folder, so we need to go up one level ('../') to find config.php.
require_once '../config.php';

// 2. CHECK IF THE ADMIN IS LOGGED IN
// If the admin is not logged in, redirect them to the login page.
if (!isset($_SESSION['admin_loggedin'])) {
    header("Location: admin_login.php");
    exit();
}

// 3. FETCH DATA FOR DASHBOARD WIDGETS
// We will fetch key statistics to give the admin a quick overview of the site's activity.

// Get total number of registered donors
$total_donors_result = $conn->query("SELECT COUNT(id) as total FROM donors");
$total_donors = $total_donors_result->fetch_assoc()['total'];

// Get total number of blood requests made
$total_requests_result = $conn->query("SELECT COUNT(id) as total FROM donor_requests");
$total_requests = $total_requests_result->fetch_assoc()['total'];

// Get number of pending requests
$pending_requests_result = $conn->query("SELECT COUNT(id) as total FROM donor_requests WHERE status = 'Pending'");
$pending_requests = $pending_requests_result->fetch_assoc()['total'];

// Get total number of contact queries
$contact_queries_result = $conn->query("SELECT COUNT(id) as total FROM contact_queries");
$contact_queries = $contact_queries_result->fetch_assoc()['total'];


// 4. FETCH RECENTLY REGISTERED DONORS
$recent_donors_sql = "SELECT full_name, email, blood_group, registered_date FROM donors ORDER BY registered_date DESC LIMIT 5";
$recent_donors_result = $conn->query($recent_donors_sql);

// 5. FETCH RECENT BLOOD REQUESTS
$recent_requests_sql = "
    SELECT dr.requester_name, d.full_name as donor_name, dr.request_date, dr.status 
    FROM donor_requests dr 
    JOIN donors d ON dr.donor_id = d.id 
    ORDER BY dr.request_date DESC 
    LIMIT 5";
$recent_requests_result = $conn->query($recent_requests_sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BloodLink</title>
    
    <!-- Link to Google Fonts for a modern look -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- **FIX**: Using the reliable CSS-based CDN for Font Awesome icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" xintegrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        /* ==============================================
        ADMIN TEMPLATE STYLES
        This CSS will be the base for all admin pages.
        ==============================================
        */

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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--main-bg);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
        }

        /* --- Sidebar Navigation --- */
        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0;
            top: 0;
            height: 100%;
            z-index: 1000;
            transition: transform 0.3s ease-in-out;
        }

        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            background-color: rgba(0,0,0,0.2);
        }

        .sidebar-logo {
            color: #fff;
            font-size: 1.8rem;
            font-weight: 700;
            text-decoration: none;
        }

        .sidebar-nav {
            flex-grow: 1;
            list-style: none;
            padding: 1rem 0;
        }

        .sidebar-nav li a {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: background-color 0.3s, color 0.3s;
            font-weight: 500;
        }

        .sidebar-nav li a i {
            margin-right: 1rem;
            width: 20px;
            text-align: center;
        }

        .sidebar-nav li a:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .sidebar-nav li.active > a {
            background-color: var(--sidebar-active);
            color: #fff;
        }

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

        /* --- Main Content Area --- */
        .main-wrapper {
            flex-grow: 1;
            margin-left: 260px; /* Same as sidebar width */
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease-in-out;
        }

        /* --- Header --- */
        .header {
            background-color: var(--card-bg);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 0 2rem;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .menu-icon {
            display: none; /* Hidden on desktop */
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-dark); /* Ensure the icon has a visible color */
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .admin-profile a {
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
        }
        .admin-profile i {
            margin-left: 0.5rem;
        }

        .content {
            padding: 2rem;
            flex-grow: 1;
        }

        /* --- Dashboard Specific Styles --- */
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .card-icon {
            font-size: 2.5rem;
            padding: 1rem;
            border-radius: 50%;
            margin-right: 1.5rem;
            color: #fff;
        }
        .card-icon.icon-users { background-color: #3498db; }
        .card-icon.icon-requests { background-color: #e67e22; }
        .card-icon.icon-pending { background-color: #f1c40f; }
        .card-icon.icon-queries { background-color: #9b59b6; }

        .card-info h3 {
            font-size: 2rem;
            font-weight: 700;
        }
        .card-info p {
            color: var(--text-light);
        }

        .data-tables {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        .table-container {
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
            overflow-x: auto; /* For responsiveness */
        }

        .table-container h2 {
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.8rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        th {
            font-weight: 600;
            background-color: #f9f9f9;
        }
        .status-badge {
            padding: 0.25rem 0.6rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--card-bg);
        }
        .status-Pending { background-color: #f1c40f; }
        .status-Accepted { background-color: #2ecc71; }
        .status-Denied { background-color: #e74c3c; }

        /* --- Mobile Responsiveness --- */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-260px); /* Hide sidebar off-screen */
            }
            .main-wrapper {
                margin-left: 0;
            }
            .menu-icon {
                display: block;
            }
            /* When sidebar is active */
            body.sidebar-active .sidebar {
                transform: translateX(0);
            }
            body.sidebar-active .main-wrapper {
                /* Optional: could push content over, but an overlay is often better */
            }
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.4);
                z-index: 998;
            }
            body.sidebar-active .overlay {
                display: block;
            }
        }
        @media (max-width: 576px) {
            .content {
                padding: 1rem;
            }
            .header {
                padding: 0 1rem;
            }
            .card {
                flex-direction: column;
                text-align: center;
            }
            .card-icon {
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }

    </style>
</head>
<body>
    
    <div class="overlay"></div>

    <!-- ======================= SIDEBAR ======================= -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="sidebar-logo">BloodLink</a>
        </div>
        <ul class="sidebar-nav">
            <li class="active">
                <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>
            <li>
                <a href="manage_donors.php"><i class="fas fa-users"></i> Manage Donors</a>
            </li>
            <li>
                <a href="manage_requests.php"><i class="fas fa-tint"></i> Manage Requests</a>
            </li>
            <li>
                <a href="manage_admins.php"><i class="fas fa-user-shield"></i> Manage Admins</a>
            </li>
            <li>
                <a href="contact_queries.php"><i class="fas fa-envelope-open-text"></i> Contact Queries</a>
            </li>
            <li>
                <a href="settings.php"><i class="fas fa-cogs"></i> Settings</a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <a href="../index.php?logout=true"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <!-- ======================= MAIN CONTENT ======================= -->
    <div class="main-wrapper">
        <header class="header">
            <div class="menu-icon">
                <i class="fas fa-bars"></i>
            </div>
            <h1 class="header-title">Dashboard</h1>
            <div class="admin-profile">
                <a href="#">Admin <i class="fas fa-user-circle"></i></a>
            </div>
        </header>

        <main class="content">
            <!-- Statistics Cards -->
            <section class="stat-cards">
                <div class="card">
                    <div class="card-icon icon-users"><i class="fas fa-users"></i></div>
                    <div class="card-info">
                        <h3><?php echo $total_donors; ?></h3>
                        <p>Total Donors</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon icon-requests"><i class="fas fa-tint"></i></div>
                    <div class="card-info">
                        <h3><?php echo $total_requests; ?></h3>
                        <p>Total Requests</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon icon-pending"><i class="fas fa-clock"></i></div>
                    <div class="card-info">
                        <h3><?php echo $pending_requests; ?></h3>
                        <p>Pending Requests</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon icon-queries"><i class="fas fa-envelope"></i></div>
                    <div class="card-info">
                        <h3><?php echo $contact_queries; ?></h3>
                        <p>Contact Queries</p>
                    </div>
                </div>
            </section>

            <!-- Data Tables for Recent Activity -->
            <section class="data-tables">
                <div class="table-container">
                    <h2>Recent Donors</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Blood Group</th>
                                <th>Registered On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_donors_result && $recent_donors_result->num_rows > 0): ?>
                                <?php while($donor = $recent_donors_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($donor['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($donor['email']); ?></td>
                                        <td><?php echo htmlspecialchars($donor['blood_group']); ?></td>
                                        <td><?php echo date("d M, Y", strtotime($donor['registered_date'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4">No recent donors found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-container">
                    <h2>Recent Requests</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Requester</th>
                                <th>Donor</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_requests_result && $recent_requests_result->num_rows > 0): ?>
                                <?php while($request = $recent_requests_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['requester_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['donor_name']); ?></td>
                                        <td><?php echo date("d M, Y", strtotime($request['request_date'])); ?></td>
                                        <td><span class="status-badge status-<?php echo $request['status']; ?>"><?php echo $request['status']; ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4">No recent requests found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuIcon = document.querySelector('.menu-icon');
            const overlay = document.querySelector('.overlay');

            // Function to toggle the sidebar
            const toggleSidebar = () => {
                document.body.classList.toggle('sidebar-active');
            };

            // Event listener for the menu icon
            if(menuIcon) {
                menuIcon.addEventListener('click', toggleSidebar);
            }

            // Event listener for the overlay to close the sidebar
            if(overlay) {
                overlay.addEventListener('click', toggleSidebar);
            }
        });
    </script>
</body>
</html>

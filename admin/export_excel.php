<?php
session_start();
require_once '../config.php';

// Check if the admin is logged in, otherwise deny access
if (!isset($_SESSION['admin_loggedin'])) {
    die("Access Denied: You are not authorized to view this page.");
}

// --- HANDLE EXCEL DOWNLOAD REQUEST ---
if (isset($_GET['format']) && $_GET['format'] == 'excel') {
    // Set headers to force download as an Excel file
    $filename = "donors_list_" . date('Y-m-d') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");

    // Fetch all donors from the database
    $query = "SELECT id, full_name, email, phone_number, address, date_of_birth, blood_group, status, registered_date FROM donors ORDER BY id ASC";
    $result = mysqli_query($conn, $query);

    // Define the column headers for the Excel file
    $column_headers = [
        "ID", "Full Name", "Email", "Phone", "Address", "Date of Birth", 
        "Blood Group", "Status", "Registered Date"
    ];

    // Output column headers
    echo implode("\t", $column_headers) . "\n";

    // Loop through the results and output each row
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Data array for the row
            $row_data = [
                $row['id'], $row['full_name'], $row['email'], $row['phone_number'],
                $row['address'], $row['date_of_birth'], $row['blood_group'], $row['status'],
                $row['registered_date']
            ];
            
            // Sanitize data and output the row
            array_walk($row_data, function(&$str) {
                $str = preg_replace("/\t/", "\\t", $str);
                $str = preg_replace("/\r?\n/", "\\n", $str);
                if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"';
            });
            echo implode("\t", $row_data) . "\n";
        }
    } else {
        echo "No donors found.\n";
    }
    exit();
}

// --- FETCH DATA FOR HTML PREVIEW ---
$donors_result = $conn->query("SELECT * FROM donors ORDER BY registered_date DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Donors - Admin Panel</title>
    
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
        .main-wrapper { flex-grow: 1; margin-left: 260px; display: flex; flex-direction: column; transition: margin-left 0.3s ease-in-out; }
        .header { background-color: var(--card-bg); box-shadow: 0 2px 5px rgba(0,0,0,0.05); padding: 0 2rem; height: 70px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 999; }
        .menu-icon { display: none; font-size: 1.5rem; cursor: pointer; color: var(--text-dark); }
        .header-title { font-size: 1.5rem; font-weight: 600; }
        .admin-profile a { color: var(--text-dark); text-decoration: none; font-weight: 600; }
        .admin-profile i { margin-left: 0.5rem; }
        .content { padding: 2rem; flex-grow: 1; }
        
        .export-controls { background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .export-controls h2 { font-size: 1.2rem; margin: 0; }
        .export-buttons { display: flex; gap: 1rem; }
        .btn { padding: 0.7rem 1.5rem; border-radius: 5px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; cursor: pointer; border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center; }
        .btn i { margin-right: 0.5rem; }
        .btn-excel { background-color: #217346; color: #fff; }
        .btn-excel:hover { background-color: #1c643d; }
        .btn-pdf { background-color: #d93025; color: #fff; }
        .btn-pdf:hover { background-color: #b9271d; }

        .table-container { background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        th { font-weight: 600; background-color: #f9f9f9; }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-260px); }
            .main-wrapper { margin-left: 0; }
            .menu-icon { display: block; }
            body.sidebar-active .sidebar { transform: translateX(0); }
            .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 998; }
            body.sidebar-active .overlay { display: block; }
        }
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
            <li class="active"><a href="manage_donors.php"><i class="fas fa-users"></i> Manage Donors</a></li>
            <li><a href="manage_requests.php"><i class="fas fa-tint"></i> Manage Requests</a></li>
            <li><a href="manage_admins.php"><i class="fas fa-user-shield"></i> Manage Admins</a></li>
            <li><a href="contact_queries.php"><i class="fas fa-envelope-open-text"></i> Contact Queries</a></li>
            <li><a href="settings.php"><i class="fas fa-cogs"></i> Settings</a></li>
        </ul>
    </aside>

    <div class="main-wrapper">
        <header class="header">
            <div class="menu-icon"><i class="fas fa-bars"></i></div>
            <h1 class="header-title">Export Donor Data</h1>
            <div class="admin-profile"><a href="#">Admin <i class="fas fa-user-circle"></i></a></div>
        </header>

        <main class="content">
            <div class="export-controls">
                <h2>Export Options</h2>
                <div class="export-buttons">
                    <a href="export_excel.php?format=excel" class="btn btn-excel"><i class="fas fa-file-excel"></i> Download as Excel</a>
                    <button id="download-pdf" class="btn btn-pdf"><i class="fas fa-file-pdf"></i> Download as PDF</button>
                </div>
            </div>

            <div class="table-container">
                <h2>Data Preview</h2>
                <table id="donors-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>DOB</th>
                            <th>Blood Group</th>
                            <th>Status</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($donors_result && $donors_result->num_rows > 0): ?>
                            <?php while($donor = $donors_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $donor['id']; ?></td>
                                    <td><?php echo htmlspecialchars($donor['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($donor['email']); ?></td>
                                    <td><?php echo htmlspecialchars($donor['phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars($donor['address']); ?></td>
                                    <td><?php echo date("d M, Y", strtotime($donor['date_of_birth'])); ?></td>
                                    <td><?php echo htmlspecialchars($donor['blood_group']); ?></td>
                                    <td><?php echo htmlspecialchars($donor['status']); ?></td>
                                    <td><?php echo date("d M, Y", strtotime($donor['registered_date'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="9" style="text-align:center;">No donors found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- jsPDF Libraries for PDF Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

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

            // --- PDF Download Logic ---
            const pdfButton = document.getElementById('download-pdf');
            if (pdfButton) {
                pdfButton.addEventListener('click', () => {
                    // Initialize jsPDF
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF({ orientation: "landscape" });

                    // Add a title to the PDF
                    doc.text("BloodLink Donors List", 14, 16);
                    doc.setFontSize(10);
                    doc.text("Generated on: <?php echo date('Y-m-d H:i:s'); ?>", 14, 22);

                    // Use autoTable to convert the HTML table to a PDF table
                    doc.autoTable({
                        html: '#donors-table',
                        startY: 28, // Position the table after the title
                        theme: 'grid',
                        headStyles: {
                            fillColor: [217, 42, 42] // Red color for header
                        },
                        styles: {
                            fontSize: 8,
                            cellPadding: 2
                        }
                    });

                    // Save the PDF
                    doc.save('donors_list_<?php echo date("Y-m-d"); ?>.pdf');
                });
            }
        });
    </script>
</body>
</html>

<?php
session_start();
require_once '../config.php';

// Check if the admin is logged in, otherwise deny access
if (!isset($_SESSION['admin_loggedin'])) {
    die("Access Denied: You are not authorized to view this page.");
}

// --- BUILD DYNAMIC SQL QUERY BASED ON FILTERS ---
$sql = "
    SELECT dr.id, dr.requester_name, dr.requester_contact, d.full_name as donor_name, 
           dr.message, dr.status, dr.request_date, dr.expiry_date 
    FROM donor_requests dr
    JOIN donors d ON dr.donor_id = d.id
";

$where_clauses = [];
$params = [];
$types = '';

// Get filter values from GET request
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_start = $_GET['date_start'] ?? '';
$date_end = $_GET['date_end'] ?? '';

// 1. Search Filter
if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $where_clauses[] = "(dr.requester_name LIKE ? OR d.full_name LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// 2. Status Filter
if (!empty($status_filter)) {
    if ($status_filter == 'Expired') {
        $where_clauses[] = "(dr.status = 'Accepted' AND dr.expiry_date IS NOT NULL AND dr.expiry_date < CURDATE())";
    } else if ($status_filter == 'Accepted') {
        $where_clauses[] = "(dr.status = 'Accepted' AND (dr.expiry_date IS NULL OR dr.expiry_date >= CURDATE()))";
    } else {
        $where_clauses[] = "dr.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
}

// 3. Date Range Filter
if (!empty($date_start)) {
    $where_clauses[] = "dr.request_date >= ?";
    $params[] = $date_start;
    $types .= 's';
}
if (!empty($date_end)) {
    $where_clauses[] = "dr.request_date <= ?";
    $params[] = $date_end . ' 23:59:59'; // Include the full end day
    $types .= 's';
}

// Append WHERE clauses to the main query
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

// Add ORDER BY
$sql .= " ORDER BY dr.request_date DESC";


// --- HANDLE EXCEL DOWNLOAD REQUEST ---
if (isset($_GET['format']) && $_GET['format'] == 'excel') {
    // Set headers to force download as an Excel file
    $filename = "requests_list_" . date('Y-m-d') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");

    // Prepare and execute the dynamic query for Excel
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Define the column headers for the Excel file
    $column_headers = [
        "ID", "Requester", "Requester Contact", "Donor", "Message", "Status", "Request Date", "Expiry Date"
    ];

    // Output column headers
    echo implode("\t", $column_headers) . "\n";

    // Loop through the results and output each row
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Check for expiry logic
            $status = $row['status'];
            $expiry_date = $row['expiry_date'];
            if ($status === 'Accepted' && $expiry_date !== NULL && (strtotime(date('Y-m-d')) > strtotime($expiry_date))) {
                $status = 'Expired'; // Overwrite status for export
            }
            
            $row_data = [
                $row['id'], $row['requester_name'], $row['requester_contact'], $row['donor_name'],
                $row['message'], $status, $row['request_date'], $expiry_date
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
        echo "No requests found matching your filters.\n";
    }
    $stmt->close();
    exit();
}

// --- FETCH DATA FOR HTML PREVIEW ---
// Prepare and execute the same dynamic query for the HTML preview
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$requests_result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Requests - Admin Panel</title>
    
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
        
        .btn { padding: 0.7rem 1.5rem; border-radius: 5px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; cursor: pointer; border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center; }
        .btn i { margin-right: 0.5rem; }
        .btn-excel { background-color: #217346; color: #fff; }
        .btn-excel:hover { background-color: #1c643d; }
        .btn-pdf { background-color: #d93025; color: #fff; }
        .btn-pdf:hover { background-color: #b9271d; }
        .btn-primary { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary { background: #6c757d; color: #fff; border-color: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }

        /* ### NEW: Filter Form Styles ### */
        .filter-container { background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); margin-bottom: 2rem; }
        .filter-form { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 1rem; align-items: flex-end; }
        .form-group label { margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; display: block; }
        .form-group input, .form-group select { width: 100%; padding: 0.7rem; border: 1px solid #ccc; border-radius: 5px; font-family: inherit; }
        .filter-buttons { display: flex; gap: 0.5rem; }
        
        .export-controls { background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .export-controls h2 { font-size: 1.2rem; margin: 0; }
        .export-buttons { display: flex; gap: 1rem; }

        .table-container { background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { font-weight: 600; background-color: #f9f9f9; white-space: nowrap; }
        td.message-col { min-width: 250px; white-space: normal; }
        
        .status-badge { padding: 0.25rem 0.6rem; border-radius: 1rem; font-size: 0.8rem; font-weight: 600; color: #fff; text-align: center; display: inline-block; }
        .status-Pending { background-color: #f1c40f; }
        .status-Accepted { background-color: #2ecc71; }
        .status-Denied { background-color: #e74c3c; }
        .status-Expired { background-color: #6c757d; }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-260px); }
            .main-wrapper { margin-left: 0; }
            .menu-icon { display: block; }
            body.sidebar-active .sidebar { transform: translateX(0); }
            .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 998; }
            body.sidebar-active .overlay { display: block; }
        }
        @media (max-width: 768px) {
            .filter-form { grid-template-columns: 1fr; }
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
            <li><a href="manage_donors.php"><i class="fas fa-users"></i> Manage Donors</a></li>
            <li class="active"><a href="manage_requests.php"><i class="fas fa-tint"></i> Manage Requests</a></li>
            <li><a href="manage_admins.php"><i class="fas fa-user-shield"></i> Manage Admins</a></li>
            <li><a href="contact_queries.php"><i class="fas fa-envelope-open-text"></i> Contact Queries</a></li>
            <li><a href="settings.php"><i class="fas fa-cogs"></i> Settings</a></li>
        </ul>
    </aside>

    <div class="main-wrapper">
        <header class="header">
            <div class="menu-icon"><i class="fas fa-bars"></i></div>
            <h1 class="header-title">Export Request Data</h1>
            <div class="admin-profile"><a href="#">Admin <i class="fas fa-user-circle"></i></a></div>
        </header>

        <main class="content">
            
            <!-- ### NEW: Filter Form ### -->
            <div class="filter-container">
                <form action="export_requests.php" method="get" class="filter-form">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" placeholder="Requester or Donor..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">All</option>
                            <option value="Pending" <?php echo ($status_filter == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Accepted" <?php echo ($status_filter == 'Accepted') ? 'selected' : ''; ?>>Accepted</option>
                            <option value="Denied" <?php echo ($status_filter == 'Denied') ? 'selected' : ''; ?>>Denied</option>
                            <option value="Expired" <?php echo ($status_filter == 'Expired') ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_start">From</label>
                        <input type="date" name="date_start" id="date_start" value="<?php echo htmlspecialchars($date_start); ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_end">To</label>
                        <input type="date" name="date_end" id="date_end" value="<?php echo htmlspecialchars($date_end); ?>">
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="export_requests.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <div class="export-controls">
                <h2>Export Options</h2>
                <div class="export-buttons">
                    <?php
                        // Build the query string for export links, preserving filters
                        $excel_params = $_GET;
                        $excel_params['format'] = 'excel';
                        $excel_query_string = http_build_query($excel_params);
                    ?>
                    <a href="export_requests.php?<?php echo $excel_query_string; ?>" class="btn btn-excel"><i class="fas fa-file-excel"></i> Download as Excel</a>
                    <button id="download-pdf" class="btn btn-pdf"><i class="fas fa-file-pdf"></i> Download as PDF</button>
                </div>
            </div>

            <div class="table-container">
                <h2>Data Preview (Filtered)</h2>
                <table id="requests-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Requester</th>
                            <th>Contact</th>
                            <th>Donor</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($requests_result && $requests_result->num_rows > 0): ?>
                            <?php while($request = $requests_result->fetch_assoc()): ?>
                                <?php
                                    // Check for expiry logic
                                    $status = $request['status'];
                                    $expiry_date = $request['expiry_date'];
                                    if ($status === 'Accepted' && $expiry_date !== NULL && (strtotime(date('Y-m-d')) > strtotime($expiry_date))) {
                                        $status = 'Expired';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $request['id']; ?></td>
                                    <td><?php echo htmlspecialchars($request['requester_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['requester_contact']); ?></td>
                                    <td><?php echo htmlspecialchars($request['donor_name']); ?></td>
                                    <td class="message-col"><?php echo htmlspecialchars($request['message']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($status); ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date("d M, Y H:i", strtotime($request['request_date'])); ?></td>
                                    <td><?php echo ($expiry_date) ? date("d M, Y", strtotime($expiry_date)) : 'N/A'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center;">No requests found matching your filters.</td></tr>
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
            // This will automatically export the filtered table shown in the preview
            const pdfButton = document.getElementById('download-pdf');
            if (pdfButton) {
                pdfButton.addEventListener('click', () => {
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF({ orientation: "landscape" });

                    doc.text("BloodLink Requests List", 14, 16);
                    doc.setFontSize(10);
                    doc.text("Generated on: <?php echo date('Y-m-d H:i:s'); ?>", 14, 22);

                    doc.autoTable({
                        html: '#requests-table', // This ID points to the filtered preview table
                        startY: 28,
                        theme: 'grid',
                        headStyles: { fillColor: [217, 42, 42] },
                        styles: { fontSize: 8, cellPadding: 2 }
                    });

                    doc.save('requests_list_filtered_<?php echo date("Y-m-d"); ?>.pdf');
                });
            }
        });
    </script>
</body>
</html>

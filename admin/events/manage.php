<?php
// 1. Start Session & Include Config
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config.php'; 

// 2. SECURITY CHECK
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../auth/login.php");
    exit;
}

/**
 * 3. FETCH ALL EVENTS + ORGANIZER + CATEGORY
 */
$sql = "SELECT e.*, u.name as organizer_name, c.categoryName 
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.user_id 
        LEFT JOIN event_category c ON e.category_id = c.category_id 
        ORDER BY e.event_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All System Events | MyEvent System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #0d6efd; --sidebar-bg: #343a40; --bg-body: #f4f6f9; --danger: #dc3545; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-body); margin: 0; display: flex; }
        .sidebar { width: 250px; background: var(--sidebar-bg); color: #fff; height: auto; padding: 20px; flex-shrink: 0; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .table-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; padding: 12px; background: #f8f9fa; color: #6c757d; border-bottom: 2px solid #dee2e6; font-size: 0.85rem; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #dee2e6; color: #495057; font-size: 0.9rem; vertical-align: middle; }
        .btn-action { padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 0.8rem; margin-right: 5px; color: white; border: none; cursor: pointer; }
        .btn-edit { background: #ffc107; color: #212529; }
        .btn-delete { background: var(--danger); }
        .poster-thumb { width: 50px; height: 50px; border-radius: 4px; object-fit: cover; border: 1px solid #ddd; }
    </style>
</head>
<body>

<aside class="sidebar">
    <h3>MyEvent Admin</h3>
    <hr style="border-color: #4b545c;">
    <p><a href="../index.php" style="color:white; text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
</aside>

<main class="main-content">
    <div class="table-card">
        <h2><i class="fas fa-calendar-alt"></i> Event Management</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Poster</th>
                    <th>Event Name</th>
                    <th>Organizer</th>
                    <th>Category</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['event_id']; ?></td>
                        
                        <td>
                            <?php if(!empty($row['poster_path'])): ?>
                                <img src="../../uploads/<?php echo htmlspecialchars($row['poster_path']); ?>" class="poster-thumb">
                            <?php else: ?>
                                <div style="width:50px; height:50px; background:#eee; display:flex; align-items:center; justify-content:center; border-radius:4px;"><i class="fas fa-image" style="color:#ccc;"></i></div>
                            <?php endif; ?>
                        </td>

                        <td><strong><?php echo htmlspecialchars($row['event_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['organizer_name'] ?? 'Unknown'); ?></td>
                        
                        <td><span style="background:#e0e7ff; color:#4338ca; padding:4px 8px; border-radius:4px; font-size:0.8rem; font-weight:600;">
                            <?php echo htmlspecialchars($row['categoryName'] ?? 'Uncategorized'); ?>
                        </span></td>

                        <td><?php echo date('M d, Y', strtotime($row['event_date'])); ?></td>
                        <td>
                            <a href="edit.php?id=<?php echo $row['event_id']; ?>" class="btn-action btn-edit"><i class="fas fa-edit"></i></a>
                            <a href="delete.php?id=<?php echo $row['event_id']; ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
<?php
// 1. Start Session & Include Config
require_once '../../config.php'; 

require_admin();

// 3. HANDLE CSV DOWNLOAD
if (isset($_POST['download_csv'])) {
    ob_clean(); 
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users_list_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('User ID', 'Name', 'Email', 'Role', 'Category', 'Joined Date'));
    
    $query = "SELECT user_id, name, email, role, category, created_at FROM users ORDER BY created_at DESC";
    $rows = $conn->query($query);
    
    while ($row = $rows->fetch_assoc()) {
        fputcsv($output, csv_row($row));
    }
    fclose($output);
    exit;
}

// 4. FETCH ALL USERS FOR TABLE
$sql = "SELECT user_id, name, email, role, category, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);

// 5. FETCH UNIQUE CATEGORIES FOR FILTER
$cat_query = "SELECT DISTINCT category FROM users WHERE category IS NOT NULL AND category != ''";
$cat_result = $conn->query($cat_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users | MyEvent System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #0d6efd; --sidebar-bg: #343a40; --bg-body: #f1f5f9; --danger: #dc3545; --warning: #ffc107; --success: #198754; --pdf: #e11d48; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-body); margin: 0; display: flex; }
        .sidebar { width: 250px; background: var(--sidebar-bg); color: #fff; height: 100vh; padding: 20px; position: fixed; }
        .main-content { margin-left: 250px; flex: 1; padding: 30px; overflow-y: auto; }
        .table-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .search-container { display: flex; gap: 15px; margin: 20px 0; align-items: center; }
        .search-input { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; flex: 1; font-size: 0.9rem; }
        .filter-select { padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem; background: white; }
        
        /* Button Styles */
        .btn-group { display: flex; gap: 10px; }
        .btn-action { border: none; padding: 10px 15px; border-radius: 8px; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: white; transition: 0.3s; }
        .btn-csv { background: var(--success); }
        .btn-pdf { background: var(--pdf); }
        .btn-action:hover { opacity: 0.85; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 12px; background: #f8f9fa; color: #6c757d; border-bottom: 2px solid #dee2e6; }
        td { padding: 12px; border-bottom: 1px solid #dee2e6; color: #495057; vertical-align: middle; }
        
        /* PDF PRINT STYLES */
        @media print {
            .sidebar, .search-container, .btn-group, .actions-col, .fa-edit, .fa-trash, th:last-child, td:last-child { display: none !important; }
            .main-content { margin-left: 0; padding: 0; width: 100%; }
            .table-card { box-shadow: none; border: none; }
            body { background: white; }
            table { width: 100%; border: 1px solid #000; }
            th, td { border: 1px solid #000; padding: 8px; font-size: 10pt; }
            .badge { border: 1px solid #000; background: none !important; color: black !important; }
            h2::after { content: " - Official Campus Record"; font-size: 12pt; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <h3>MyEvent Admin</h3>
    <p><a href="../index.php" style="color:white; text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
</aside>

<main class="main-content">
    <?php include ROOT_PATH . '/include/flash.php'; ?>
    <div class="table-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2><i class="fas fa-users"></i> Manage Registered Users</h2>
            
            <div class="btn-group">
                <form method="POST" style="margin:0;">
                    <?= csrf_field() ?>
                    <button type="submit" name="download_csv" class="btn-action btn-csv">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                </form>
                <button onclick="window.print()" class="btn-action btn-pdf">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
            </div>
        </div>
        
        <p>Total Registered: <?php echo $result->num_rows; ?></p>

        <div class="search-container">
            <input type="text" id="userInput" class="search-input" placeholder="Search by name or email...">
            <select id="categoryFilter" class="filter-select">
                <option value="">All Categories</option>
                <?php while($cat = $cat_result->fetch_assoc()): ?>
                    <option value="<?= e($cat['category']) ?>"><?= e($cat['category']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <table id="userTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Category</th>
                    <th>Role</th>
                    <th>Joined Date</th>
                    <th class="actions-col">Actions</th> 
                </tr>
            </thead>
            <tbody>
                <?php while($user = $result->fetch_assoc()): ?>
                <tr class="user-row">
                    <td><?= e($user['user_id']) ?></td>
                    <td class="user-name"><strong><?php echo e($user['name']); ?></strong></td>
                    <td class="user-email"><?php echo e($user['email']); ?></td>
                    <td class="user-category"><?php echo e($user['category']); ?></td>
                    <td class="user-role">
                        <span class="badge badge-<?php echo strtolower($user['role']); ?>">
                            <?= e($user['role']) ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                    <td class="actions-col">
                        <a href="edit.php?id=<?= e($user['user_id']) ?>" style="color: var(--warning); margin-right: 10px;"><i class="fas fa-edit"></i></a>
                        <form action="delete.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this user permanently?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $user['user_id'] ?>">
                            <button type="submit" style="color: var(--danger); background:none; border:none; cursor:pointer; padding:0;" aria-label="Delete user"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
    const searchInput = document.getElementById('userInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const rows = document.querySelectorAll('.user-row');

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedCategory = categoryFilter.value.toLowerCase();

        rows.forEach(row => {
            const name = row.querySelector('.user-name').textContent.toLowerCase();
            const email = row.querySelector('.user-email').textContent.toLowerCase();
            const category = row.querySelector('.user-category').textContent.trim().toLowerCase();

            const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm);
            const matchesCategory = selectedCategory === "" || category === selectedCategory;

            row.style.display = (matchesSearch && matchesCategory) ? "" : "none";
        });
    }

    searchInput.addEventListener('keyup', filterTable);
    categoryFilter.addEventListener('change', filterTable);
</script>

</body>
</html>
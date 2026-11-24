<?php
require_once 'config.php';

// Fix for the session notice

// 1. Get the category ID from the dropdown selection
$cat_filter = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

// 2. Simple SQL using only the events table
$sql = "SELECT * FROM events WHERE visibility = 'Public' AND reg_close_date >= CURDATE()";

if ($cat_filter > 0) {
    $sql .= " AND category_id = " . $cat_filter;
}

$sql .= " ORDER BY event_date ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse Events | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; margin: 0; padding: 40px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .filter-container { margin: 20px 0; background: white; padding: 20px; border-radius: 12px; display: flex; align-items: center; gap: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .filter-select { padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; width: 250px; }
        .btn-filter { background: #4f46e5; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .event-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; }
        .event-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-top: 4px solid #4f46e5; }
        .btn-register { display: block; width: 100%; border: none; cursor: pointer; font-size: 1rem; background: #4f46e5; color: white; text-align: center; padding: 12px; border-radius: 6px; text-decoration: none; margin-top: 20px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Available Events</h2>
            <a href="users/dashboard.php" style="text-decoration: none; color: #4f46e5; font-weight: bold;">My Dashboard →</a>
        </div>
        
        <form action="" method="GET" class="filter-container">
            <label style="font-weight: 600;">Filter by Type:</label>
            <select name="category_id" class="filter-select">
                <option value="0">-- All Categories --</option>
                <option value="1" <?= $cat_filter == 1 ? 'selected' : '' ?>>Workshop</option>
                <option value="2" <?= $cat_filter == 2 ? 'selected' : '' ?>>Seminar</option>
                <option value="3" <?= $cat_filter == 3 ? 'selected' : '' ?>>Competition</option>
                <option value="4" <?= $cat_filter == 4 ? 'selected' : '' ?>>Festival</option>
                <option value="5" <?= $cat_filter == 5 ? 'selected' : '' ?>>Sport</option>
                <option value="6" <?= $cat_filter == 6 ? 'selected' : '' ?>>Course</option>
            </select>
            <button type="submit" class="btn-filter">Apply Filter</button>
            <?php if($cat_filter > 0): ?>
                <a href="browse_events.php" style="color: #ef4444; text-decoration: none; font-size: 0.9rem;">Clear</a>
            <?php endif; ?>
        </form>

        <div class="event-grid">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="event-card">
                        <h3><?= e($row['event_name']) ?></h3>
                        <p style="color: #64748b;"><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($row['event_date'])) ?></p>
                        <p style="color: #64748b;"><i class="fas fa-map-marker-alt"></i> <?= e($row['venue']) ?></p>
                        <p style="color: #64748b;"><i class="fas fa-tag"></i> Fee: <?= e(format_fee($row['fee'])) ?></p>
                        
                        <form action="users/register_event.php" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="event_id" value="<?= (int) $row['event_id'] ?>">
                            <button type="submit" class="btn-register">Register for Event</button>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 50px; background: white; border-radius: 12px;">
                    <p style="color: #64748b;">No events found in this category.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
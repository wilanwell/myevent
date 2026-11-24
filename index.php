<?php

// 1. Include global configuration
require_once 'config.php';

// 2. Handle Filtering Logic
$selected_category = isset($_GET['category']) ? intval($_GET['category']) : 0;

// 3. Fetch Categories for the filter bar
$cat_list = $conn->query("SELECT * FROM event_category ORDER BY categoryName ASC");

// 4. Fetch Events for Public Browsing
$sql = "SELECT e.*, c.categoryName 
        FROM events e 
        LEFT JOIN event_category c ON e.category_id = c.category_id";

if ($selected_category > 0) {
    $sql .= " WHERE e.category_id = $selected_category";
}

$sql .= " ORDER BY e.event_date ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_PATH_CSS; ?>style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .filter-container { display: flex; justify-content: center; gap: 10px; margin-bottom: 40px; flex-wrap: wrap; }
        .filter-btn { padding: 8px 18px; border-radius: 20px; border: 1px solid #e2e8f0; background: white; color: #64748b; text-decoration: none; font-size: 0.9rem; transition: 0.3s; }
        .filter-btn:hover, .filter-btn.active { background: #4f46e5; color: white; border-color: #4f46e5; }
    </style>
</head>
<body>

    <?php include 'include/topNav.php'; ?>

    <header class="hero">
        <div class="overlay"></div>
        <div class="hero-content">
            <img src="<?php echo BASE_PATH_IMG; ?>logo.png" alt="MyEvent Logo" class="logo">
            <h1>Welcome to <?php echo APP_NAME; ?></h1>
            <p>Digitalizing campus event handling from announcement to registration.</p>
        </div>
    </header>

    <main style="max-width: 1200px; margin: auto; padding: 0 20px;">
        <h2 class="section-title" style="text-align: center; margin-top: 50px; margin-bottom: 20px; font-size: 2rem; color: #1e293b;">Upcoming Campus Events</h2>

        <div class="filter-container">
            <a href="index.php" class="filter-btn <?php echo ($selected_category == 0) ? 'active' : ''; ?>">All Events</a>
            <?php while($cat = $cat_list->fetch_assoc()): ?>
                <a href="index.php?category=<?php echo $cat['category_id']; ?>" 
                   class="filter-btn <?php echo ($selected_category == $cat['category_id']) ? 'active' : ''; ?>">
                   <?php echo htmlspecialchars($cat['categoryName']); ?>
                </a>
            <?php endwhile; ?>
        </div>

        <div class="event-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 50px;">
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="event-card" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
                        
                        <div style="height: 200px; overflow: hidden;">
                            <?php 
                                $poster = !empty($row['poster_path']) ? 'uploads/'.$row['poster_path'] : 'assets/img/event1.jpg';
                            ?>
                            <img src="<?php echo $poster; ?>" alt="Event Poster" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>

                        <div style="padding: 20px;">
                            <span class="badge-cat" style="font-size: 0.75rem; background: #e0e7ff; color: #4f46e5; padding: 4px 8px; border-radius: 4px; font-weight: bold;">
                                <?php echo htmlspecialchars($row['categoryName'] ?? 'General'); ?>
                            </span>
                            
                            <h3 style="margin: 10px 0;"><?php echo htmlspecialchars($row['event_name']); ?></h3>
                            
                            <div style="font-size: 0.9rem; color: #64748b; margin-bottom: 15px;">
                                <i class="fa-regular fa-calendar"></i> <?php echo date('M d, Y', strtotime($row['event_date'])); ?><br>
                                <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($row['venue']); ?>
                            </div>

                            <div style="display: flex; gap: 10px;">
                                <a href="event_detail.php?id=<?php echo $row['event_id']; ?>" class="nav-btn" style="flex: 1; text-align: center; border: 1px solid #cbd5e1; color: #334155; text-decoration: none; padding: 8px; border-radius: 6px;">Details</a>
                                
                                <form action="users/register_event.php" method="POST" style="flex: 1;">
                                    <input type="hidden" name="event_id" value="<?php echo $row['event_id']; ?>">
                                    <button type="submit" class="nav-btn active" style="width: 100%; border: none; cursor: pointer; background: #4f46e5; color: white; padding: 8px; border-radius: 6px;">Join Now</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; grid-column: 1/-1; padding: 50px;">
                    <i class="fa-solid fa-calendar-xmark" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px;"></i>
                    <p style="color: #64748b;">No events found for this category.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'include/footer.php'; ?>
</body>
</html>
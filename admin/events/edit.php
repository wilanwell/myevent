<?php
// 1. Start Session & Include Config
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config.php'; 

// 2. SECURITY CHECK
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// 3. FETCH EVENT DATA
if (!isset($_GET['id']) || empty($_GET['id'])) { die("No event ID provided."); }
$event_id = intval($_GET['id']);

$sql = "SELECT * FROM events WHERE event_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) { die("Event not found."); }

// 4. FETCH CATEGORIES FOR DROPDOWN
$categories = $conn->query("SELECT category_id, categoryName FROM event_category ORDER BY categoryName ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Event | MyEvent System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --sidebar-bg: #1e293b; --bg-body: #f1f5f9; --primary: #4f46e5; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-body); margin: 0; display: flex; }
        .sidebar { width: 250px; background: var(--sidebar-bg); color: #fff; height: 100vh; padding: 20px; flex-shrink: 0; position: fixed; }
        .main-content { flex: 1; padding: 30px; margin-left: 250px; overflow-y: auto; }
        .form-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 850px; margin: auto; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; color: #475569; font-size: 0.9rem; }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; font-size: 0.95rem; }
        textarea { resize: vertical; }
        .poster-preview { width: 150px; height: 100px; border-radius: 8px; object-fit: cover; border: 2px solid #eee; margin-bottom: 10px; display: block; }
        button { background: var(--primary); color: white; border: none; padding: 15px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; font-size: 1rem; transition: 0.3s; margin-top: 10px; }
        button:hover { background: #4338ca; transform: translateY(-1px); }
        .section-title { border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 20px; color: var(--primary); font-size: 1.3rem; display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <h3 style="color: #fff; margin-bottom: 30px;">MyEvent Admin</h3>
    <nav>
        <a href="manage.php" style="color:#cbd5e1; text-decoration:none; display: block; padding: 10px 0;">
            <i class="fas fa-arrow-left"></i> Back to Manage
        </a>
    </nav>
</aside>

<main class="main-content">
    <div class="form-card">
        <h2 class="section-title"><i class="fas fa-calendar-edit"></i> Edit Event Details</h2>
        
        <form action="edit_action.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="event_id" value="<?= $event['event_id']; ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label>Event Name</label>
                    <input type="text" name="event_name" value="<?= htmlspecialchars($event['event_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" required>
                        <?php while($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= ($event['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['categoryName']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Contact No</label>
                    <input type="text" name="contact_no" value="<?= htmlspecialchars($event['contact_no'] ?? ''); ?>" placeholder="e.g. 012-3456789">
                </div>
                <div class="form-group">
                    <label>Event Mode</label>
                    <select name="mode">
                        <option value="Physical" <?= ($event['mode'] == 'Physical') ? 'selected' : '' ?>>Physical</option>
                        <option value="Online" <?= ($event['mode'] == 'Online') ? 'selected' : '' ?>>Online</option>
                        <option value="Hybrid" <?= ($event['mode'] == 'Hybrid') ? 'selected' : '' ?>>Hybrid</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Venue / Link</label>
                <input type="text" name="venue" value="<?= htmlspecialchars($event['venue']); ?>" required>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Event Date</label>
                    <input type="date" name="date" value="<?= $event['event_date']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Event Time</label>
                    <input type="time" name="time" value="<?= $event['event_time'] ?? ''; ?>" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Registration Close Date</label>
                    <input type="date" name="reg_close_date" value="<?= $event['reg_close_date'] ?? ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Fee (RM)</label>
                    <input type="number" step="0.01" name="fee" value="<?= floatval($event['fee']); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Max Participants</label>
                <input type="number" name="max_participants" value="<?= $event['max_participants'] ?? 0; ?>">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" required><?= htmlspecialchars($event['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Remarks / Special Notes</label>
                <textarea name="remarks" rows="2"><?= htmlspecialchars($event['remarks'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Event Poster</label>
                <?php if (!empty($event['poster_path']) && file_exists("../../uploads/" . $event['poster_path'])): ?>
                    <img src="../../uploads/<?= htmlspecialchars($event['poster_path']); ?>" class="poster-preview">
                <?php else: ?>
                    <div style="width:150px; height:100px; background:#f8fafc; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#94a3b8; margin-bottom:10px; border: 2px dashed #e2e8f0; font-size: 0.8rem;">No Poster</div>
                <?php endif; ?>
                <input type="file" name="poster" accept="image/*">
                <small style="color: #64748b;">Leave blank to keep current image.</small>
            </div>

            <button type="submit"><i class="fas fa-save"></i> Update Event</button>
        </form>
    </div>
</main>

</body>
</html>
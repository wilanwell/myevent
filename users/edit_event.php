<?php
require_once '../config.php';

require_login();

$error = "";
$user_id = current_user_id();

// 2. Get Event Data including poster_path
if (isset($_GET['id'])) {
    $event_id = intval($_GET['id']);
    $sql = "SELECT * FROM events WHERE event_id = ? AND created_by = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();

    if (!$event) {
        flash('error', 'Event not found or access denied.');
        redirect('users/my_events.php');
    }
} else {
    header("location: my_events.php");
    exit;
}

// --- File upload: validated by content type, random name (see include/security.php) ---
function handlePosterUpload($fileArray) {
    [$name, $err] = upload_image($fileArray, POSTER_DIR, 'event', 5 * 1024 * 1024);
    return $err ? [false, $err] : [$name !== null, $name];
}

// 3. Handle Update Logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = intval($_POST['category_id']);
    $contact_no = trim($_POST['contact_no']);
    $venue = trim($_POST['location']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $mode = (string) ($_POST['mode'] ?? '');
    if (!in_array($mode, ['Physical', 'Online', 'Hybrid'], true)) { $error = 'Please choose a valid event mode.'; }
    $reg_close = $_POST['reg_close_date'];
    $max_part = !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : 0;
    $fee = parse_fee($_POST['fee'] ?? 0);
    $remarks = trim($_POST['remarks']);
    
    // Default to existing poster
    $poster_path = $event['poster_path']; 

    // Handle New Poster Upload
    if (empty($error) && isset($_FILES['poster']) && $_FILES['poster']['size'] > 0) {
        list($success, $result) = handlePosterUpload($_FILES['poster']);
        if ($success) {
            // Delete old file if it exists
            delete_upload($event['poster_path'], POSTER_DIR);
            $poster_path = $result;
        } else {
            $error = $result;
        }
    }

    if (empty($error)) {
        $update_sql = "UPDATE events SET 
                       event_name=?, description=?, category_id=?, contact_no=?, 
                       venue=?, event_date=?, event_time=?, mode=?, 
                       reg_close_date=?, max_participants=?, fee=?, remarks=?, poster_path=? 
                       WHERE event_id=? AND created_by=?";
        
        if ($stmt = $conn->prepare($update_sql)) {
            $stmt->bind_param("ssissssssidssii", 
                $title, $description, $category_id, $contact_no, 
                $venue, $date, $time, $mode, 
                $reg_close, $max_part, $fee, $remarks, $poster_path,
                $event_id, $user_id
            );
            
            if ($stmt->execute()) {
                flash('success', 'Event updated successfully.');
                redirect('users/my_events.php');
            } else {
                $error = "Could not update the event. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Event | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: system-ui, sans-serif; background: #f1f5f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .form-card { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 750px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #4f46e5; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #334155; font-size: 0.85rem; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.95rem; }
        .poster-preview { width: 100px; height: 100px; border-radius: 8px; object-fit: cover; border: 2px solid #e2e8f0; margin-bottom: 10px; }
        .btn-update { background: #4f46e5; color: white; width: 100%; padding: 12px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 1rem; margin-top: 10px; }
        .alert { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f87171; }
    </style>
</head>
<body>
    <div class="form-card">
        <h2><i class="fas fa-edit"></i> Edit Event</h2>
        <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Current Poster</label>
                <img src="<?= e(poster_url($event['poster_path'])) ?>" class="poster-preview" alt="<?= e($event['event_name'] ?? 'Event poster') ?>" loading="lazy">
                <input type="file" name="poster" accept="image/*">
            </div>

            <div class="form-grid">
                <div class="form-group"><label>Event Name</label><input type="text" name="title" value="<?php echo e($event['event_name']); ?>" required></div>
                <div class="form-group">
                    <label>Type (Category)</label>
                    <select name="category_id" required>
                        <option value="1" <?php if($event['category_id'] == 1) echo 'selected'; ?>>Workshop</option>
                        <option value="2" <?php if($event['category_id'] == 2) echo 'selected'; ?>>Seminar</option>
                        <option value="3" <?php if($event['category_id'] == 3) echo 'selected'; ?>>Competition</option>
                        <option value="4" <?php if($event['category_id'] == 4) echo 'selected'; ?>>Festival</option>
                        <option value="5" <?php if($event['category_id'] == 5) echo 'selected'; ?>>Sport</option>
                        <option value="6" <?php if($event['category_id'] == 6) echo 'selected'; ?>>Course</option>
                    </select>
                </div>
            </div>

            <div class="form-group"><label>Description</label><textarea name="description" rows="2" required><?php echo e($event['description']); ?></textarea></div>

            <div class="form-grid">
                <div class="form-group"><label>Date</label><input type="date" name="date" value="<?= e($event['event_date']) ?>" required></div>
                <div class="form-group"><label>Time</label><input type="time" name="time" value="<?= e($event['event_time']) ?>" required></div>
            </div>

            <div class="form-grid">
                <div class="form-group"><label>Venue</label><input type="text" name="location" value="<?php echo e($event['venue']); ?>" required></div>
                <div class="form-group">
                    <label>Mode</label>
                    <select name="mode">
                        <option value="Physical" <?php if($event['mode'] == 'Physical') echo 'selected'; ?>>Physical</option>
                        <option value="Online" <?php if($event['mode'] == 'Online') echo 'selected'; ?>>Online</option>
                        <option value="Hybrid" <?php if($event['mode'] == 'Hybrid') echo 'selected'; ?>>Hybrid</option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group"><label>Fee (RM)</label><input type="text" name="fee" value="<?= e(number_format((float) $event['fee'], 2, ".", "")) ?>" placeholder="0.00 = Free" required></div>
                <div class="form-group"><label>Reg. Close Date</label><input type="date" name="reg_close_date" value="<?= e($event['reg_close_date']) ?>" required></div>
            </div>

            <div class="form-grid">
                <div class="form-group"><label>Contact No</label><input type="text" name="contact_no" value="<?php echo e($event['contact_no']); ?>"></div>
                <div class="form-group"><label>Max Participants</label><input type="number" name="max_participants" value="<?= e($event['max_participants']) ?>"></div>
            </div>

            <div class="form-group"><label>Remarks</label><textarea name="remarks" rows="2"><?php echo e($event['remarks']); ?></textarea></div>

            <button type="submit" class="btn-update">Update Event Details</button>
            <a href="my_events.php" style="display:block; text-align:center; margin-top:10px; color:#64748b; text-decoration:none;">Cancel</a>
        </form>
    </div>
</body>
</html>
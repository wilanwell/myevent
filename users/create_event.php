<?php
require_once '../config.php';

require_login();

$user_id = current_user_id();

$error = "";
$success = "";

// File upload: validated by content type, random name (see include/security.php)
function handleFileUpload($fileArray) {
    [$name, $err] = upload_image($fileArray, POSTER_DIR, 'event', 5 * 1024 * 1024);
    return $err ? [false, $err] : [$name !== null, $name];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 3. Collect Data based on Requirements
    $event_name   = trim($_POST['title']);
    $description  = trim($_POST['description']);
    $category_id  = (int) ($_POST['category_id'] ?? 0); 
    $contact_no   = trim($_POST['contact_no']); 
    $venue        = trim($_POST['location']);
    $event_date   = $_POST['date'];
    $event_time   = $_POST['time'];
    $mode         = (string) ($_POST['mode'] ?? '');
    $reg_close    = $_POST['reg_close_date'];
    $max_part     = !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : 0;
    $fee          = parse_fee($_POST['fee'] ?? 0); // "Free" or an amount -> number
    $remarks      = trim($_POST['remarks']);
    $visibility   = in_array($_POST['visibility'] ?? '', ['Public', 'Private'], true) ? $_POST['visibility'] : 'Public';
    $poster_path  = null; // no poster -> placeholder via poster_url()

    // Validation for Mandatory Fields
    if (!in_array($mode, ['Physical', 'Online', 'Hybrid'], true) || empty($event_name) || empty($event_date) || empty($venue) || empty($category_id) || empty($user_id)) {
        $error = "Please fill in all required fields. Session error: please re-login if ID is missing.";
    } else {
        if (isset($_FILES['poster']) && $_FILES['poster']['size'] > 0) {
            list($uploadSuccess, $uploadResult) = handleFileUpload($_FILES['poster']);
            if ($uploadSuccess) { $poster_path = $uploadResult; } 
            else { $error = $uploadResult; }
        }

        if (empty($error)) {
            // 4. SQL matches database columns
            $sql = "INSERT INTO events (event_name, description, category_id, contact_no, venue, event_date, event_time, mode, reg_close_date, max_participants, fee, remarks, visibility, poster_path, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssissssssidsssi", 
                    $event_name, $description, $category_id, $contact_no, $venue, 
                    $event_date, $event_time, $mode, $reg_close, $max_part, 
                    $fee, $remarks, $visibility, $poster_path, $user_id
                );
                
                if ($stmt->execute()) {
                    flash('success', 'Event created successfully.');
                    redirect('users/my_events.php');
                } else {
                    $error = "Could not save the event. Please try again.";
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Event | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: system-ui, sans-serif; background: #f1f5f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .form-card { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 750px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #4f46e5; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        /* High-contrast brand styling */
        .brand-text { color: #ffffff !important; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #334155; font-size: 0.9rem; }
        .required { color: #ef4444; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.95rem; }
        .btn-submit { background: #4f46e5; color: white; width: 100%; padding: 12px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 10px; font-size: 1rem; }
        .alert { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f87171; }
    </style>
</head>
<body>
    <div class="form-card">
        <h2><i class="fas fa-plus-circle"></i> Create New Event</h2>
        <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="form-group"><label>Event Name <span class="required">*</span></label><input type="text" name="title" required></div>
                <div class="form-group">
                    <label>Type (Category) <span class="required">*</span></label>
                    <select name="category_id" required>
                        <option value="1">Workshop</option>
                        <option value="2">Seminar</option>
                        <option value="3">Competition</option>
                        <option value="4">Festival</option>
                        <option value="5">Sport</option>
                        <option value="6">Course</option>
                    </select>
                </div>
            </div>

            <div class="form-group"><label>Description <span class="required">*</span></label><textarea name="description" rows="2" required></textarea></div>
            
            <div class="form-grid">
                <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" name="date" required></div>
                <div class="form-group"><label>Time <span class="required">*</span></label><input type="time" name="time" required></div>
            </div>

            <div class="form-grid">
                <div class="form-group"><label>Venue <span class="required">*</span></label><input type="text" name="location" required></div>
                <div class="form-group">
                    <label>Mode <span class="required">*</span></label>
                    <select name="mode" required>
                        <option value="Physical">Physical</option>
                        <option value="Online">Online</option>
                        <option value="Hybrid">Hybrid</option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group"><label>Fee (RM) <span class="required">*</span></label><input type="text" name="fee" required placeholder="e.g. 10.00 or Free"></div>
                <div class="form-group"><label>Reg. Close Date <span class="required">*</span></label><input type="date" name="reg_close_date" required></div>
            </div>

            <div class="form-grid">
                <div class="form-group"><label>Contact No (Optional)</label><input type="text" name="contact_no" placeholder="e.g. 012-3456789"></div>
                <div class="form-group">
                    <label>Visibility <span class="required">*</span></label>
                    <select name="visibility" required>
                        <option value="Public">Public (Everyone)</option>
                        <option value="Private">Private (Within University)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Max Participants (Optional)</label>
                <input type="number" name="max_participants" placeholder="Leave empty for unlimited">
            </div>

            <div class="form-group"><label>Remarks / Notes</label><textarea name="remarks" rows="2" placeholder="Internal notes or instructions"></textarea></div>

            <div class="form-group">
                <label>Event Poster</label>
                <input type="file" name="poster" accept="image/*">
                <small style="color: #64748b;">Max 10MB. JPG, PNG allowed.</small>
            </div>

            <button type="submit" class="btn-submit">Publish Event</button>
            <a href="dashboard.php" style="display:block; text-align:center; margin-top:10px; color:#64748b; text-decoration:none;">Cancel</a>
        </form>
    </div>
</body>
</html>
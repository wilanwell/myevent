<?php
require_once 'config.php';

// 1. Validate ID
if (empty($_GET['id'])) {
    redirect('index.php');
}

$event_id = intval($_GET['id']);

//2. Fetch Event Details
$sql = "SELECT e.*, c.categoryName AS category, u.email as organizer_email, u.name as organizer_name,
               (SELECT COUNT(*) FROM registrations r
                 WHERE r.event_id = e.event_id AND r.approval_status <> 'Rejected') AS taken
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.user_id
        LEFT JOIN event_category c ON e.category_id = c.category_id 
        WHERE e.event_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    render_error_page('Event not found', 'This event does not exist or has been removed.');
}

$event = $result->fetch_assoc();

// Registration availability (same rules as users/register_event.php)
$close_date = $event['reg_close_date'] ?: $event['event_date'];
$is_closed  = $close_date && $close_date < date('Y-m-d');
$is_full    = (int) $event['max_participants'] > 0 && (int) $event['taken'] >= (int) $event['max_participants'];
$is_owner   = is_logged_in() && (int) $event['created_by'] === current_user_id();

// 3. CHECK USER REGISTRATION STATUS
$status = null;
if (is_logged_in()) {
    $user_id = current_user_id();
    
    $check_sql = "SELECT approval_status FROM registrations WHERE user_id = ? AND event_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $event_id);
    $check_stmt->execute();
    $check_res = $check_stmt->get_result();
    
    if($r = $check_res->fetch_assoc()) {
        $status = $r['approval_status']; 
    }
    $check_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($event['event_name']); ?> | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .back-link { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #4f46e5; font-weight: 600; }
        .detail-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .detail-img { width: 100%; height: 400px; object-fit: cover; }
        .detail-content { padding: 40px; }
        .d-title { font-size: 2.5rem; font-weight: 800; color: #1e293b; margin: 10px 0; }
        .meta-info { display: flex; gap: 20px; color: #64748b; margin-bottom: 30px; font-size: 1rem; }
        .meta-info i { color: #4f46e5; }
        .badge-cat { background: #e0e7ff; color: #4338ca; padding: 5px 12px; border-radius: 20px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; }
        .action-area { margin-top: 40px; padding-top: 30px; border-top: 1px solid #f1f5f9; }
        .btn-common { width: 100%; padding: 15px; border-radius: 10px; border: none; font-weight: 700; cursor: pointer; font-size: 1.1rem; transition: 0.3s; }
        .btn-register { background: #4f46e5; color: white; text-decoration: none; display: block; text-align: center; }
        .btn-joined { background: #dcfce7; color: #16a34a; cursor: default; }
        .btn-pending { background: #fef3c7; color: #d97706; cursor: default; }
    </style>
</head>
<body>

<?php include "include/topNav.php"; ?>

<main style="padding: 60px 0;">
    <div style="max-width: 1100px; margin: 0 auto 20px; padding: 0 20px;"><?php include ROOT_PATH . '/include/flash.php'; ?></div>
    <div class="container" style="max-width: 600px; margin: 0 auto;">
        <a href="index.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Events
        </a>

        <div class="detail-card">
            <img src="<?= e(poster_url($event['poster_path'])) ?>"  class="detail-img" alt="<?= e($event['event_name'] ?? 'Event poster') ?>" loading="lazy">

            <div class="detail-content">
                <?php if(!empty($event['category'])): ?>
                    <span class="badge-cat"><?= e($event['category']); ?></span>
                <?php endif; ?>

                <h1 class="d-title"><?= e($event['event_name']); ?></h1>

                <div class="meta-info">
                    <div><i class="fa-regular fa-calendar"></i> <?= date('F d, Y', strtotime($event['event_date'])); ?></div>
                    <div><i class="fa-solid fa-location-dot"></i> <?= e($event['venue']); ?></div>
                    <?php if (!empty($event['event_time'])): ?>
                        <div><i class="fa-regular fa-clock"></i> <?= date('h:i A', strtotime($event['event_time'])) ?></div>
                    <?php endif; ?>
                    <div><i class="fa-solid fa-tag"></i> <?= e(format_fee($event['fee'])) ?></div>
                    <?php if ((int) $event['max_participants'] > 0): ?>
                        <div><i class="fa-solid fa-users"></i> <?= max(0, (int) $event['max_participants'] - (int) $event['taken']) ?> seats left</div>
                    <?php endif; ?>
                    <div><i class="fa-regular fa-user"></i> By: <?= e($event['organizer_name'] ?? $event['organizer_email']); ?></div>
                </div>

                <div class="d-desc" style="line-height: 1.8; color: #334155;">
                    <h4 style="margin-bottom: 10px; font-size: 1.2rem; color: #1e293b;">About this Event</h4>
                    <?= nl2br(e($event['description'])); ?>
                </div>

                <div class="action-area">
                    <?php if(is_logged_in()): ?>
                        
                        <?php if($status === 'Approved'): ?>
                            <div class="btn-common btn-joined" style="text-align: center;">
                                <i class="fa-solid fa-circle-check"></i> You are registered & Approved
                            </div>
                        
                        <?php elseif($status === 'Pending'): ?>
                            <div class="btn-common btn-pending" style="text-align: center;">
                                <i class="fa-regular fa-clock"></i> Waiting for Approval
                            </div>
                        
                        <?php elseif($status === 'Rejected'): ?>
                            <div class="btn-common btn-pending" style="text-align: center; background:#fee2e2; color:#991b1b;">
                                <i class="fa-solid fa-circle-xmark"></i> Your registration was not approved
                            </div>

                        <?php elseif($is_owner): ?>
                            <a href="users/manage_participants.php?event_id=<?= (int) $event_id ?>" class="btn-common btn-register">
                                <i class="fa-solid fa-users"></i> Manage Participants
                            </a>

                        <?php elseif($is_closed || $is_full): ?>
                            <div class="btn-common btn-pending" style="text-align: center; background:#f1f5f9; color:#475569;">
                                <i class="fa-solid fa-lock"></i> <?= $is_closed ? 'Registration closed' : 'Event is full' ?>
                            </div>

                        <?php else: ?>
                            <form action="users/register_event.php" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="event_id" value="<?= e($event_id) ?>">
                                <button type="submit" class="btn-common btn-register">
                                    Register Now
                                </button>
                            </form>
                        <?php endif; ?>

                    <?php else: ?>
                        <a href="auth/login.php" class="btn-common btn-register">
                            Login to Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "include/footer.php"; ?>

</body>
</html>
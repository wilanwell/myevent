<?php
session_start();
require_once 'config.php';

// 1. Validate ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: index.php");
    exit;
}

$event_id = intval($_GET['id']);

//2. Fetch Event Details
$sql = "SELECT e.*, u.email as organizer_email, u.name as organizer_name 
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.user_id 
        WHERE e.event_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Event not found.";
    exit;
}

$event = $result->fetch_assoc();

// 3. CHECK USER REGISTRATION STATUS
$status = null;
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $user_id = $_SESSION['user_id'] ?? $_SESSION['id'];
    
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
    <title><?= htmlspecialchars($event['event_name']); ?> | <?php echo APP_NAME; ?></title>
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
    <div class="container" style="max-width: 600px; margin: 0 auto;">
        <a href="index.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Events
        </a>

        <div class="detail-card">
            <?php if(!empty($event['poster_path'])): ?>
                <img src="uploads/<?= htmlspecialchars($event['poster_path']); ?>" alt="Event Poster" class="detail-img">
            <?php else: ?>
                <div class="detail-img" style="background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#cbd5e1;">
                    <i class="fa-regular fa-image" style="font-size:5rem;"></i>
                </div>
            <?php endif; ?>

            <div class="detail-content">
                <?php if(!empty($event['category'])): ?>
                    <span class="badge-cat"><?= htmlspecialchars($event['category']); ?></span>
                <?php endif; ?>

                <h1 class="d-title"><?= htmlspecialchars($event['event_name']); ?></h1>

                <div class="meta-info">
                    <div><i class="fa-regular fa-calendar"></i> <?= date('F d, Y', strtotime($event['event_date'])); ?></div>
                    <div><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($event['venue']); ?></div>
                    <div><i class="fa-regular fa-user"></i> By: <?= htmlspecialchars($event['organizer_name'] ?? $event['organizer_email']); ?></div>
                </div>

                <div class="d-desc" style="line-height: 1.8; color: #334155;">
                    <h4 style="margin-bottom: 10px; font-size: 1.2rem; color: #1e293b;">About this Event</h4>
                    <?= nl2br(htmlspecialchars($event['description'])); ?>
                </div>

                <div class="action-area">
                    <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                        
                        <?php if($status === 'Approved'): ?>
                            <div class="btn-common btn-joined" style="text-align: center;">
                                <i class="fa-solid fa-circle-check"></i> You are registered & Approved
                            </div>
                        
                        <?php elseif($status === 'Pending'): ?>
                            <div class="btn-common btn-pending" style="text-align: center;">
                                <i class="fa-regular fa-clock"></i> Waiting for Approval
                            </div>
                        
                        <?php else: ?>
                            <form action="users/register_event.php" method="POST">
                                <input type="hidden" name="event_id" value="<?= $event_id; ?>">
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
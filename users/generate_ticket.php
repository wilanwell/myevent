<?php
session_start();

require_once '../config.php';

if (!isset($_SESSION['loggedin'])) { exit; }

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'];
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Fetch user and event details to verify approval
$sql = "SELECT e.*, u.name as user_name, r.registration_date 
        FROM registrations r
        JOIN events e ON r.event_id = e.event_id
        JOIN users u ON r.user_id = u.user_id
        WHERE r.event_id = ? AND r.user_id = ? AND r.approval_status = 'Approved'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $event_id, $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) { die("Ticket not available or not yet approved."); }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ticket - <?= htmlspecialchars($data['event_name']) ?></title>
    <style>
        body { background: #f1f5f9; padding: 40px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .ticket { border: 2px dashed #4f46e5; background: #ffffff; padding: 30px; width: 500px; margin: auto; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .header { text-align: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px; }
        .qr { float: right; width: 100px; height: 100px; background: #f8fafc; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; text-align: center; color: #64748b; }
        .ticket-info p { margin: 10px 0; color: #1e293b; }
        .label { color: #64748b; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; display: block; }
        @media print { 
            .no-print { display: none; } 
            body { background: white; padding: 0; } 
            .ticket { box-shadow: none; border: 2px solid #4f46e5; } 
        }
    </style>
</head>
<body>

    <div class="no-print" style="text-align:center; margin-bottom: 20px;">
        <button onclick="window.print()" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">
            <i class="fas fa-print"></i> Print Ticket
        </button>
    </div>

    <div class="ticket">
        <div class="header">
            <h2 style="margin:0; color:#4f46e5;"><?= APP_NAME ?> Official Ticket</h2>
            <p style="margin:5px 0 0; color:#64748b; font-size:0.9rem;">Registration Date: <?= date('d M Y', strtotime($data['registration_date'])) ?></p>
        </div>
        
        <div class="qr">SCAN AT<br>ENTRANCE</div>
        
        <div class="ticket-info">
            <div style="margin-bottom: 15px;">
                <span class="label">Event Name</span>
                <h3 style="margin:0; color:#1e293b;"><?= htmlspecialchars($data['event_name']) ?></h3>
            </div>

            <div style="margin-bottom: 15px;">
                <span class="label">Participant</span>
                <p style="margin:0; font-weight:600;"><?= htmlspecialchars($data['user_name']) ?></p>
            </div>

            <div style="display:flex; gap:30px; margin-bottom: 15px;">
                <div>
                    <span class="label">Date</span>
                    <p style="margin:0; font-weight:600;"><?= date('l, d M Y', strtotime($data['event_date'])) ?></p>
                </div>
                <div>
                    <span class="label">Time</span>
                    <p style="margin:0; font-weight:600;"><?= date('h:i A', strtotime($data['event_time'])) ?></p>
                </div>
            </div>

            <div>
                <span class="label">Venue</span>
                <p style="margin:0; font-weight:600;"><?= htmlspecialchars($data['venue']) ?></p>
            </div>
        </div>

        <div style="margin-top:30px; border-top:1px solid #f1f5f9; padding-top:10px; text-align:center;">
            <small style="color:#94a3b8;">Please bring a digital or printed copy of this pass.</small>
        </div>
    </div>
</body>
</html>
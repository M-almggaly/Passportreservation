<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1) {
    http_response_code(403);
    exit;
}

// عدد الإشعارات غير المقروءة
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM reservations 
    WHERE status = 'قيد مراجعة الطلب' AND is_read = 0
");
$stmt->execute();
$notificationCount = (int) $stmt->fetchColumn();

// آخر 5 إشعارات غير مقروءة مع أسماء المستخدمين والسيارات
$stmt = $pdo->prepare("
    SELECT 
        r.id,
        r.date,
        r.time_from,
        r.time_to,
        u.name AS user_name,
        c.name AS car_name
    FROM reservations r
    JOIN users u ON r.user_id = u.id
    JOIN cars c ON r.car_id = c.id
    WHERE r.status = 'قيد مراجعة الطلب' AND r.is_read = 0 
    ORDER BY r.created_at DESC 
    LIMIT 5
");
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'notificationCount' => $notificationCount,
    'notifications'     => $notifications,
]);

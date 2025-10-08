<?php
session_start();
require 'db.php';

$userId = $_SESSION['user_id'];

$update = $pdo->prepare("
    UPDATE passport_requests pr
    INNER JOIN applicants a ON pr.applicant_id = a.id
    SET pr.notified = 1
    WHERE a.user_id = :user_id
");
$update->execute(['user_id' => $userId]);

echo 'success';
?>

<?php
session_start();
require 'db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // استلام البيانات من النموذج
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // التحقق من أن الحقول ليست فارغة
    if ($email === '' || $password === '') {
        $errors[] = "يرجى إدخال البريد الإلكتروني وكلمة المرور.";
    } else {
        // استعلام التحقق من المستخدم
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND password = :password");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user) {
            // حفظ بيانات المستخدم في الجلسة
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['user_name']    = $user['name'];
            $_SESSION['last_activity']= time();
            $_SESSION['user_type']    = $user['user_type'];

            // التوجيه حسب نوع المستخدم
            if ($user['user_type'] == 1) {
                header("Location: dashboar.php");
            } else {
                header("Location: reservation.php");
            }
            exit;
        } else {
            $errors[] = "بيانات الدخول غير صحيحة.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول</title>
    <link rel="shortcut icon" href="images/logo%20(1).png">
    <link href="src/output.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200..1000&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">

<div class="card shadow-sm border-0" style="max-width: 400px; width:100%;">
    <div class="card-body p-4">
        <!-- Logo -->
        <div class="text-center mb-3">
            <a href="/">
                <h1 style="font-weight: bold;">
                    <a dir="rtl" href="index.php"><span>.</span>JAWAZI</a>
                </h1>

            </a>
        </div>

        <!-- Title -->
        <h5 style="font-family: 'Cairo', 'Almarai', 'Rubik', 'Montserrat', sans-serif" class="text-center mb-4 fw-bold text-dark">تسجيل الدخول</h5>

        <!-- Error Alert -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="" onsubmit="showLoading()">
            <div class="mb-3">
                <label for="email" class="form-label fw-semibold">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" required class="form-control">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-semibold">كلمة المرور</label>
                <input type="password" id="password" name="password" required class="form-control">
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="register.php" class="small text-decoration-underline text-muted">ليس لديك حساب؟</a>
                <button id="submitBtn" type="submit" style="background-color:#fdc134; color: white" class="btn  d-flex align-items-center gap-2 fw-bold">
                    <svg id="loadingIcon" class="d-none spinner-border spinner-border-sm text-light" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </svg>
                    دخول
                </button>
            </div>
        </form>
    </div>
</div>
<!-- Vendor JS Files -->
<script src="assets/vendor/aos/aos.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
<script src="assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>
<script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>

<!-- Template Main JS File -->
<script src="assets/js/main.js"></script>
</body>
</html>
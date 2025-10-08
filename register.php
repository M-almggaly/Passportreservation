<?php
session_start();
require 'db.php';
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name         = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email        = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
    $password     = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm      = isset($_POST['password_confirmation']) ? $_POST['password_confirmation'] : '';


    if (empty($name)) $errors[] = "الاسم مطلوب.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "بريد إلكتروني غير صالح.";
        if (empty($phone_number)) $errors[] = "رقم الجوال مطلوب.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "هذا البريد الإلكتروني مستخدم بالفعل.";
        }
    }

    if (strlen($password) < 6) $errors[] = "كلمة المرور يجب أن تكون 6 أحرف على الأقل.";
    if ($password !== $confirm) $errors[] = "تأكيد كلمة المرور غير مطابق.";
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone_number,  password, user_type) VALUES (?, ?,?, ?, 2)");
        $stmt->execute([$name, $email, $phone_number, $password]);
        $_SESSION['success'] = "تم إنشاء الحساب بنجاح! يمكنك الآن تسجيل الدخول.";
        header('Location: login.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إنشاء حساب</title>
    <link rel="shortcut icon" href="images/logo (1).svg">
    <link href="src/output.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200..1000&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Raleway:300,300i,400,400i,500,500i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 px-3">

<div class="card shadow border-0" style="max-width: 400px; width: 100%;">
    <div class="card-body p-4">
        <div class="text-center mb-3">
            <a href="/">
                <h1 style="font-weight: bold;">
                    <a dir="rtl" href="index.php"><span>.</span>JAWAZI</a>
                </h1>

            </a>
        </div>
        <h1 style="font-family: 'Almarai', sans-serif;"  class="h5 text-center fw-bold mb-4 text-dark">إنشاء حساب عميل</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger mt-4" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-1"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action=""  onsubmit="showLoading()">
            <!-- الاسم -->
            <div class="mb-3">
                <label for="name" class="form-label fw-medium">الاسم</label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
            </div>

            <!-- البريد الإلكتروني -->
            <div class="mb-3">
                <label for="email" class="form-label fw-medium">البريد الإلكتروني</label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
            </div>

            <!-- رقم الجوال -->
            <div class="mb-3">
                <label for="phone_number" class="form-label fw-medium">رقم الجوال</label>
                <input type="number" class="form-control" id="phone_number" name="phone_number"
                       value="<?= isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : '' ?>" required>
            </div>


            <!-- كلمة المرور -->
            <div class="mb-3">
                <label for="password" class="form-label fw-medium">كلمة المرور</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <!-- تأكيد كلمة المرور -->
            <div class="mb-4">
                <label for="password_confirmation" class="form-label fw-medium">تأكيد كلمة المرور</label>
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
            </div>

            <!-- أزرار -->
            <div class="d-flex justify-content-between align-items-center">
                <a href="login.php" class="small text-decoration-underline text-dark">تم التسجيل بالفعل؟</a>
                <button id="submitBtn" type="submit" style="background-color:#fdc134; color: white" class="btn fw-bold d-flex align-items-center gap-2">
                    <span id="loadingIcon" class="spinner-border spinner-border-sm text-light d-none" role="status"></span>
                    تسجيل
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    // عند الضغط على زر التسجيل يظهر أيقونة التحميل
    const form = document.querySelector('form');
    const submitBtn = document.getElementById('submitBtn');
    const loadingIcon = document.getElementById('loadingIcon');

    form.addEventListener('submit', (e) => {
        loadingIcon.classList.remove('d-none');
        submitBtn.setAttribute('disabled', true);
    });
</script>
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

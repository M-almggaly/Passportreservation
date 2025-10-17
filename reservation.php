<?php
session_start();
require 'db.php'; // يحتوي على كود الاتصال بـ PDO فقط

// تحقق من الجلسة وصلاحيات المستخدم
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 2) {
    header("Location: login.php");
    exit;
}

// تسجيل الخروج
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['logout'])) {
    session_destroy(); // حذف الجلسة
    header("Location: login.php");
    exit;
}

// جلب اسم وبريد المستخدم الحالي
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
$userId = $_SESSION['user_id'];

// جلب الطلبات الخاصة بالمستخدم
$stmtRequests = $pdo->prepare("
    SELECT pr.id, pr.status, pr.request_date, a.full_name
    FROM passport_requests pr
    INNER JOIN applicants a ON pr.applicant_id = a.id
    WHERE a.user_id = :user_id
    ORDER BY pr.request_date DESC
");
$stmtRequests->execute(['user_id' => $userId]);
$requests = $stmtRequests->fetchAll(PDO::FETCH_ASSOC);

// جلب عدد الإشعارات
$stmtNotif = $pdo->prepare("
    SELECT COUNT(*) AS new_count
    FROM passport_requests pr
    INNER JOIN applicants a ON pr.applicant_id = a.id
 WHERE a.user_id = :user_id 
  AND pr.status NOT IN ('في انتظار الموافقة', 'انتظار التجديد')

");
$stmtNotif->execute(['user_id' => $userId]);
$notifCount = $stmtNotif->fetch(PDO::FETCH_ASSOC)['new_count'];

// جلب تفاصيل الإشعارات
$stmtNotifs = $pdo->prepare("
    SELECT pr.id, pr.status, pr.request_date, a.full_name , pr.rejection_reason
    FROM passport_requests pr
    INNER JOIN applicants a ON pr.applicant_id = a.id
  WHERE a.user_id = :user_id 
  AND pr.status NOT IN ('في انتظار الموافقة', 'انتظار التجديد')
    ORDER BY pr.request_date DESC
");
$stmtNotifs->execute(['user_id' => $userId]);
$notifs = $stmtNotifs->fetchAll(PDO::FETCH_ASSOC);


// ✅ معالجة طلب تجديد الجواز
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['passport_number'])) {

    $passport_number = trim($_POST['passport_number']);
    $phone_number = trim($_POST['phone_number']);

    if (empty($passport_number) || empty($phone_number)) {
        echo "<script>alert('❌ يرجى إدخال رقم الجواز ورقم الجوال.');</script>";
    } else {
        // تحقق من وجود رقم الجواز في جدول passports
        $stmtCheck = $pdo->prepare("SELECT applicant_id FROM passports WHERE passport_number = :passport_number");
        $stmtCheck->execute(['passport_number' => $passport_number]);
        $passport = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($passport) {
            $applicant_id = $passport['applicant_id'];

            // 🔒 تحقق من وجود طلب تجديد سابق غير مكتمل
            $stmtExisting = $pdo->prepare("
                SELECT COUNT(*) FROM passport_requests 
                WHERE applicant_id = :applicant_id 
                AND status IN ('انتظار التجديد', 'قيد المراجعة', 'في الانتظار')
            ");
            $stmtExisting->execute(['applicant_id' => $applicant_id]);
            $hasPendingRequest = $stmtExisting->fetchColumn();

            if ($hasPendingRequest > 0) {
                echo "<script>alert('⚠️ لديك طلب تجديد قيد المعالجة حالياً، لا يمكن تقديم طلب جديد حتى يتم البت فيه.');window.location.href='reservation.php';</script>";
                exit;
            }

            // مجلد رفع الصور
            $uploadDir = __DIR__ . "/uploads/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $photoPath = null;
            if (!empty($_FILES["photo"]["name"])) {
                $photoName = time() . "_renew_photo_" . basename($_FILES["photo"]["name"]);
                $photoPath = "uploads/" . $photoName;
                move_uploaded_file($_FILES["photo"]["tmp_name"], $photoPath);
            }

            $identityImagePath = null;
            if (!empty($_FILES["identity_image"]["name"])) {
                $identityImageName = time() . "_renew_id_" . basename($_FILES["identity_image"]["name"]);
                $identityImagePath = "uploads/" . $identityImageName;
                move_uploaded_file($_FILES["identity_image"]["tmp_name"], $identityImagePath);
            }

            // تحديث بيانات الصور في جدول applicants
            $updateApplicant = $pdo->prepare("
                UPDATE applicants 
                SET photo = :photo, identity_image = :identity_image , 	phone_number =  :phone_number 
                WHERE id = :applicant_id
            ");
            $updateApplicant->execute([
                    'photo' => $photoPath,
                    'identity_image' => $identityImagePath,
                    'phone_number' => $phone_number,
                    'applicant_id' => $applicant_id
            ]);

            // إدخال طلب جديد في passport_requests بالحالة انتظار التجديد
            $request_date = date('Y-m-d');
            $status = "انتظار التجديد";

            $insertRequest = $pdo->prepare("
                INSERT INTO passport_requests (applicant_id, status, request_date) 
                VALUES (:applicant_id, :status, :request_date)
            ");
            $insertRequest->execute([
                    'applicant_id' => $applicant_id,
                    'status' => $status,
                    'request_date' => $request_date
            ]);

            echo "<script>alert('✅ تم تقديم طلب التجديد بنجاح.'); window.location.href='reservation.php';</script>";
            exit;
        } else {
            echo "<script>alert('❌ رقم الجواز غير موجود في النظام.');window.location.href='reservation.php';</script>";
        }
    }
}

// ✅ معالجة نموذج الطلب الجديد (الموجود مسبقًا)
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['logout']) && !isset($_POST['passport_number'])) {

    // استقبال البيانات من النموذج
    $full_name       = isset($_POST['full_name']) ? $_POST['full_name'] : '';
    $identity_number = isset($_POST['identity_number']) ? $_POST['identity_number'] : '';
    $date_of_birth   = isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : '';
    $place_of_birth  = isset($_POST['place_of_birth']) ? $_POST['place_of_birth'] : '';
    $gender          = isset($_POST['gender']) ? $_POST['gender'] : '';
    $address         = isset($_POST['address']) ? $_POST['address'] : '';
    $phone_number    = isset($_POST['phone_number']) ? $_POST['phone_number'] : '';
    $email           = isset($_POST['email']) ? $_POST['email'] : '';

    // تحقق من الحقول المطلوبة
    if (empty($full_name) || empty($identity_number)) {
        echo "<script>alert('❌ يجب إدخال الاسم الكامل ورقم الهوية على الأقل.');</script>";
    } else {
        // تحقق من تكرار رقم الهوية
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM applicants WHERE identity_number = :identity_number");
        $checkStmt->execute(['identity_number' => $identity_number]);
        $exists = $checkStmt->fetchColumn();

        if($exists) {
            // رقم الهوية موجود مسبقاً
            echo "<script>alert('❌ رقم الهوية موجود مسبقاً، لا يمكن تكراره.');</script>";
        } else {
            // مجلد رفع الصور
            $uploadDir = __DIR__ . "/uploads/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // رفع الصورة الشخصية
            $photoPath = null;
            if (!empty($_FILES["photo"]["name"])) {
                $photoName = time() . "_photo_" . basename($_FILES["photo"]["name"]);
                $photoPath = "uploads/" . $photoName;
                move_uploaded_file($_FILES["photo"]["tmp_name"], $photoPath);
            }

            // رفع صورة الهوية
            $identityImagePath = null;
            if (!empty($_FILES["identity_image"]["name"])) {
                $identityImageName = time() . "_id_" . basename($_FILES["identity_image"]["name"]);
                $identityImagePath = "uploads/" . $identityImageName;
                move_uploaded_file($_FILES["identity_image"]["tmp_name"], $identityImagePath);
            }

            // حفظ البيانات في applicants
            $sql_applicant = "INSERT INTO applicants 
            (user_id, full_name, identity_number, date_of_birth, place_of_birth, gender, address, phone_number, email, photo, identity_image)
            VALUES 
            (:user_id, :full_name, :identity_number, :date_of_birth, :place_of_birth, :gender, :address, :phone_number, :email, :photo, :identity_image)";

            $stmt = $pdo->prepare($sql_applicant);
            $stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'full_name' => $full_name,
                    'identity_number' => $identity_number,
                    'date_of_birth' => $date_of_birth,
                    'place_of_birth' => $place_of_birth,
                    'gender' => $gender,
                    'address' => $address,
                    'phone_number' => $phone_number,
                    'email' => $email,
                    'photo' => $photoPath,
                    'identity_image' => $identityImagePath
            ]);

            // الحصول على رقم المتقدم
            $applicant_id = $pdo->lastInsertId();

            // إدخال الطلب في passport_requests
            $request_date = date('Y-m-d');
            $status = "في انتظار الموافقة";

            $stmt2 = $pdo->prepare("INSERT INTO passport_requests (applicant_id, status, request_date) VALUES (:applicant_id, :status, :request_date)");
            $stmt2->execute([
                    'applicant_id' => $applicant_id,
                    'status' => $status,
                    'request_date' => $request_date
            ]);

            // رسالة نجاح
            echo "<script>alert('✅ تم إرسال الطلب بنجاح'); window.location.href='reservation.php';</script>";
            exit;
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>JAWAZI</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

    <!--Favicons-->
    <link href="assets/img/logo.png" rel="icon">
    <link href="assets/img/logo.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
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

<body>

<!-- ======= Header ======= -->
<header id="header" style="position: fixed; right: 0; top: 0; width: 100%" >
    <div class="container d-flex align-items-center justify-content-between" dir="rtl">

        <div class="logo">
            <h1><a href="index.html"><span>.</span>jawazi</a></h1>
        </div>
        <!-- زر طلباتي -->
        <div class="dropdown me-3">
            <div class="d-flex align-items-center gap-3">
                <!-- زر طلباتي -->
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle"
                            type="button"
                            id="requestsDropdown"
                            data-bs-toggle="dropdown"
                            data-bs-display="static"
                            aria-expanded="false">
                        طلباتي
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow p-2 text-end" aria-labelledby="requestsDropdown" style="min-width: 300px;">
                        <?php
                        if ($requests) {
                            foreach ($requests as $req) {
                                echo '<li class="dropdown-item d-flex justify-content-between align-items-center flex-column flex-sm-row">';
                                echo '<div class="d-flex flex-column flex-sm-row gap-2">';
                                echo '<span><strong>رقم الطلب:</strong> ' . $req['id'] . '</span>';
                                echo '<span><strong>الاسم:</strong> ' . htmlspecialchars($req['full_name']) . '</span>';
                                echo '<span><strong>تاريخ الطلب:</strong> ' . $req['request_date'] . '</span>';
                                echo '</div>';
                                echo '<span class="badge bg-primary mt-1 mt-sm-0">' . $req['status'] . '</span>';
                                echo '</li>';
                            }
                        } else {
                            echo '<li class="dropdown-item text-muted">لا توجد طلبات حالياً</li>';
                        }
                        ?>
                    </ul>
                </div>

                <!-- زر الإشعارات -->
                <div class="dropdown position-relative">
                    <button class="btn btn-outline-secondary position-relative"
                            id="notificationsBtn"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                        <i class="bi bi-bell"></i>
                        <?php if ($notifCount > 0): ?>
                            <span id="notifDot" class="blink-dot"></span>
                        <?php endif; ?>
                    </button>

                    <ul id="notifMenu" class="dropdown-menu dropdown-menu-end shadow p-2 text-end" style="min-width: 300px;">
                        <?php
                        if ($notifCount > 0) {
                            foreach ($notifs as $notif) {
                                // تحديد لون البادج حسب الحالة
                                $badgeClass = 'bg-primary';
                                if ($notif['status'] === 'مرفوض') {
                                    $badgeClass = 'bg-danger';
                                }

                                echo '<li class="dropdown-item">';
                                echo '<div class="d-flex justify-content-center align-items-center gap-2">';
                                echo '<span>طلب #' . $notif['id'] . ' - ' . htmlspecialchars($notif['full_name']) . '</span>';
                                echo '<span class="text-muted small">' . $notif['request_date'] . '</span>';
                                echo '<span class="badge ' . $badgeClass . '">' . $notif['status'] . '</span>';
                                echo '</div>';

                                // الرسائل حسب الحالة
                                if ($notif['status'] === 'تمت الموافقة') {
                                    echo '<div class="mt-1 text-success small fw-bold">يرجى الذهاب بعد أسبوع من إصدار الموافقة إلى أقرب مقر للخدمة المدنية لاستلام جوازك.</div>';
                                } elseif ($notif['status'] === 'تمت التجديد') {
                                    echo '<div class="mt-1 text-primary small fw-bold">يرجى الذهاب بعد أسبوع من إصدار التجديد إلى أقرب مقر للخدمة المدنية لاستلام جوازك المجدد.</div>';
                                } elseif ($notif['status'] === 'مرفوض' && !empty($notif['rejection_reason'])) {
                                    echo '<div class="mt-1 text-danger small fw-bold">' . htmlspecialchars($notif['rejection_reason']) . '</div>';
                                }

                                echo '</li>';
                            }
                        } else {
                            echo '<li class="dropdown-item text-muted">لا توجد إشعارات جديدة</li>';
                        }
                        ?>
                    </ul>

                </div>

            </div>

        </div>

        <nav id="navbar" class="navbar d-flex align-items-center">
            <ul class="me-3 mb-0">
                <li><a class="nav-link scrollto active" href="#">الرئيسية</a></li>
                <li><a class="nav-link scrollto" href="index.php">عنا</a></li>
                <li><a class="nav-link scrollto"  href="index.php">خدماتنا</a></li>
            </ul>

            <!-- قائمة المستخدم الجانبية -->
            <div class="dropdown">
                <!-- زر القائمة -->
                <button
                        class="btn p-0 border-0 bg-transparent"
                        id="userMenuButton"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                    <img src="assets/img/profile.png"
                         alt="Profile"
                         class="rounded-circle"
                         style="width: 40px; height: 40px; cursor:pointer;">
                    <span class="visually-hidden">فتح قائمة المستخدم</span>
                </button>

                <ul id="userMenuDropdown" class="dropdown-menu dropdown-menu-start shadow text-center"
                    style="min-width:220px; display: none;">
                    <li class="px-3 py-2">
                        <div class="fw-semibold">  <?php echo htmlspecialchars($currentUser['name']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($currentUser['email']); ?></small>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="" method="POST" class="m-0">
                            <button type="submit" name="logout"
                                    class="dropdown-item text-danger fw-semibold">
                                تسجيل الخروج
                            </button>
                        </form>
                    </li>
                </ul>

            </div>
            <i style="margin-right: 12px" class="bi bi-list mobile-nav-toggle"></i>
        </nav>
    </div>
</header>
<section style="margin-top: 4rem">
    <div class="container">
        <div class="text-center mb-4">
            <button id="renewPassportBtn" class="btn btn-success me-2">تجديد جواز</button>
            <button id="newPassportBtn" class="btn btn-primary ">طلب جواز جديد</button>

        </div>

        <!-- نموذج طلب جواز جديد -->
        <div id="newPassportForm" class="card shadow p-4" style="max-width: 600px; margin: auto;">
            <h5 class="mb-3 text-primary text-center" style="font-family: 'Almarai', sans-serif;">
                نموذج طلب جواز جديد
            </h5>
            <form action="reservation.php" method="POST" enctype="multipart/form-data" dir="rtl">

                <div class="mb-3">
                    <label class="form-label">الاسم الكامل</label>
                    <input type="text" name="full_name" class="form-control" placeholder="أدخل اسمك الكامل" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">رقم الهوية</label>
                    <input type="text" name="identity_number" class="form-control" placeholder="أدخل رقم الهوية" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">تاريخ الميلاد</label>
                    <input type="date" name="date_of_birth" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">مكان الميلاد</label>
                    <input type="text" name="place_of_birth" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">الجنس</label>
                    <select name="gender" class="form-control" required>
                        <option value="">اختر الجنس</option>
                        <option value="ذكر">ذكر</option>
                        <option value="أنثى                                             ">أنثى</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">العنوان</label>
                    <input type="text" name="address" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">رقم الهاتف</label>
                    <input type="text" name="phone_number" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">الصورة الشخصية</label>
                    <input type="file" name="photo" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">صورة الهوية</label>
                    <input type="file" name="identity_image" class="form-control" required>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary">إرسال الطلب</button>
                </div>
            </form>
        </div>

        <!-- نموذج تجديد جواز (مخفي افتراضيًا) -->
        <div id="renewPassportForm" class="card shadow p-4" style="display: none;">
            <h5 class="mb-3 text-success text-center" style="font-family: 'Almarai', sans-serif;">نموذج تجديد الجواز</h5>
            <form dir="rtl" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">رقم الجواز الحالي</label>
                    <input type="text" name="passport_number" class="form-control" placeholder="أدخل رقم الجواز" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">رقم الجوال</label>
                    <input type="text" name="phone_number" class="form-control" placeholder="أدخل رقم الجوال" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">الصورة الشخصية الجديدة</label>
                    <input type="file" name="photo" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">صورة الهوية الجديدة</label>
                    <input type="file" name="identity_image" class="form-control">
                </div>

                <div class="text-center">
                    <button type="submit" name="renew_passport" class="btn btn-success">طلب تجديد</button>
                </div>
            </form>
        </div>
    </div>
</section>

</div>
      <!-- ======= Footer ======= -->
  <footer id="footer">

    <div class="footer-top">

      <div class="container">

        <div class="row  justify-content-center">
            <div class="col-lg-6">
                <h3>JAWAZI</h3>
                <p dir="rtl" style="font-family: 'Almarai', sans-serif;" >
                    نعمل جاهدين لتسهيل حصولك على جواز السفر بسرعة وأمان. يسعدنا تواصلك معنا أو اشتراكك في تنبيهات الموقع
                    لمتابعة أحدث التحديثات والخدمات. آراؤك واقتراحاتك تهمنا لتحسين تجربتك دائمًا.
                </p>
            </div>

        </div>

        <div class="row footer-newsletter justify-content-center">
          <div class="col-lg-6">
            <form action="" method="post">
              <input class="" type="email" name="email" placeholder="Enter your Email"><input type="submit" value="Subscribe">
            </form>
          </div>
        </div>

        <div class="social-links">
          <a href="#" class="twitter"><i class="bx bxl-twitter"></i></a>
          <a href="#" class="facebook"><i class="bx bxl-facebook"></i></a>
          <a href="#" class="instagram"><i class="bx bxl-instagram"></i></a>
          <a href="#" class="google-plus"><i class="bx bxl-skype"></i></a>
          <a href="#" class="linkedin"><i class="bx bxl-linkedin"></i></a>
        </div>

      </div>
    </div>

    <div class="container footer-bottom clearfix">
      <div class="copyright">
        &copy; Copyright <strong><span>jawazi</span></strong>. All Rights Reserved
      </div>
      <div class="credits">

      </div>
    </div>
  </footer><!-- End Footer -->

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
<script>
    // قائمة المستخدم
    const userBtn = document.getElementById('userMenuButton');
    const userMenu = document.getElementById('userMenuDropdown'); // أعطِ قائمة المستخدم id="userMenuDropdown"

    userBtn.addEventListener('click', function(event) {
        event.stopPropagation(); // منع انتشار الحدث للـ document
        userMenu.style.display = (userMenu.style.display === 'block') ? 'none' : 'block';
    });

    // قائمة الطلبات
    const requestsBtn = document.getElementById('requestsDropdown');
    const requestsMenu = document.getElementById('requestsDropdownMenu'); // أعطِ القائمة id="requestsDropdownMenu"

    requestsBtn.addEventListener('click', function(event) {
        event.stopPropagation();
        requestsMenu.style.display = (requestsMenu.style.display === 'block') ? 'none' : 'block';
    });

    // لإخفاء أي قائمة عند الضغط في أي مكان خارجها
    document.addEventListener('click', function() {
        userMenu.style.display = 'none';
        requestsMenu.style.display = 'none';
    });

    // الفورمات الجديدة والتجديد (كما في كودك)
    const newBtn = document.getElementById("newPassportBtn");
    const renewBtn = document.getElementById("renewPassportBtn");
    const newForm = document.getElementById("newPassportForm");
    const renewForm = document.getElementById("renewPassportForm");

    newBtn.addEventListener("click", () => {
        newForm.style.display = "block";
        renewForm.style.display = "none";
    });

    renewBtn.addEventListener("click", () => {
        renewForm.style.display = "block";
        newForm.style.display = "none";
    });
    document.getElementById('notificationsBtn').addEventListener('click', function() {
        const dot = document.getElementById('notifDot');
        if(dot) {
            dot.style.display = 'none'; // اخفاء النقطة الحمراء فور فتح القائمة

            // إرسال طلب AJAX لتحديث notified = 1 في قاعدة البيانات
            fetch('mark_notified.php', {
                method: 'POST'
            }).then(res => res.text())
                .then(data => {
                    console.log('تم تحديث الإشعارات');
                });
        }
    });
</script>
  <!-- Vendor JS Files -->
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>

  <!-- Template Main JS File -->
  <script src="assets/js/main.js"></script>

</body>

</html>
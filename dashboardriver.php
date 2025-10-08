<?php
session_start();
require 'db.php'; // الاتصال بـ PDO

// تحقق من الجلسة وصلاحيات المستخدم
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 1) {
    header("Location: login.php");
    exit;
}
// جلب اسم وبريد المستخدم الحالي
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

// تسجيل الخروج
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
$stmt = $pdo->prepare("
    SELECT pr.id, a.full_name, pr.status, pr.created_at
    FROM passport_requests pr
    JOIN applicants a ON a.id = pr.applicant_id
    WHERE pr.status IN ('انتظار التجديد', 'في انتظار الموافقة')
    ORDER BY pr.created_at DESC
");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_modal'])) {

    $request_id       = isset($_POST['request_id']) ? $_POST['request_id'] : '';
    $passport_number  = isset($_POST['passport_number']) ? $_POST['passport_number'] : '';
    $place_of_issue   = isset($_POST['place_of_issue']) ? $_POST['place_of_issue'] : '';
    $issue_date  = isset($_POST['issue_date']) ? $_POST['issue_date'] : '';
    $expiry_date  = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : '';
    $status           = isset($_POST['status']) ? $_POST['status'] : '';

    if(!empty($request_id)) {
        // جلب applicant_id من passport_requests
        $stmt = $pdo->prepare("SELECT applicant_id FROM passport_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

        if($applicant) {
            $applicant_id = $applicant['applicant_id'];

            // تحقق إذا السجل موجود في passports
            $check = $pdo->prepare("SELECT id FROM passports WHERE applicant_id = ?");
            $check->execute([$applicant_id]);
            $exists = $check->fetch(PDO::FETCH_ASSOC);

            if($exists) {
                // تحديث بيانات الجواز
                $stmt2 = $pdo->prepare("
                    UPDATE passports SET 
                        passport_number = ?, 
                        place_of_issue = ?, 
                        issue_date = ?, 
                        expiry_date = ?
                    WHERE applicant_id = ?
                ");
                $stmt2->execute([$passport_number, $place_of_issue, $issue_date, $expiry_date, $applicant_id]);
            } else {
                // إدخال سجل جديد
                $stmt2 = $pdo->prepare("
                    INSERT INTO passports (applicant_id, passport_number, place_of_issue, issue_date, expiry_date)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt2->execute([$applicant_id, $passport_number, $place_of_issue, $issue_date, $expiry_date]);
            }

            // تحديث الحالة في passport_requests
            $stmt3 = $pdo->prepare("UPDATE passport_requests SET status = ? WHERE id = ?");
            $stmt3->execute([$status, $request_id]);

            echo "<script>alert('✅ تم حفظ التغييرات بنجاح'); window.location.href=window.location.href;</script>";
            exit;
        }
    }
}

// ✅ جلب الطلبات التي حالتها "في انتظار الموافقة" مع بيانات applicant
$query = "
    SELECT 
        a.full_name,
        a.identity_number,
        a.date_of_birth,
        a.place_of_birth,
        a.address,
        a.phone_number,
        a.photo AS applicant_photo,
        a.identity_image AS applicant_identity_image,
        pr.id AS request_id,
        pr.status
    FROM passport_requests pr
    INNER JOIN applicants a ON pr.applicant_id = a.id
    WHERE pr.status = 'في انتظار الموافقة'
";
$stmt = $pdo->prepare($query);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JAWAZI</title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f8f9fa;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 5rem;
            right: 0;
            height: 100vh;
            width: 240px;
            background: #fff;
            border-left: 1px solid #ddd;
            padding: 0 1rem;
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.05);
            z-index: 1030;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin-top: 2rem;
        }

        .sidebar ul li {
            padding: 0.8rem 1rem;
            margin-bottom: 0.6rem;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #262161;
            font-weight: 500;
            transition: 0.3s;
        }

        .sidebar ul li:hover {
            background: #f2f2ff;
        }

        .profile-img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                display: none;
            }

            main {
                margin-right: 0 !important;
            }
        }

        main {
            margin-right: 260px;
            padding: 6rem 2rem 2rem;
        }

        .table th {
            background-color: #f7f7f7;
        }
        /* بشكل افتراضي: لا يوجد هامش (للجوال والتابلت) */
        .custom-table {
            margin-right: 0;
        }

        /* للشاشات الكبيرة (ابتداءً من 992px) */
        @media (min-width: 992px) {
            .custom-table {
                margin-right: 15rem;
            }
        }
        /* الخلفية وراء المودال تصبح زجاجية شفافة */
        .modal-backdrop.show {
            backdrop-filter: blur(100%);
            /*background-color: rgba(255, 255, 255,255); !* شفافة قليلاً *!*/
        }

        /* المودال نفسه أبيض مع ظل ناعم */
        .white-modal {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.2);
            border: none;
        }
        .modal-header {
            direction: rtl; /* يجعل الاتجاه من اليمين إلى اليسار */
        }
        .modal-header .btn-close {
            margin-right: auto !important; /* يدفعه إلى اليسار */
            margin-left: 0 !important;
        }
    </style>
</head>

<body>
<!-- ======= Header ======= -->
<header id="header" style="position: fixed; right: 0; top: 0; width: 100%" >
    <div class="container d-flex align-items-center justify-content-between" dir="rtl">

        <div class="logo">
            <h1><a href="dashboardriver.php"><span>.</span>jawazi</a></h1>
        </div>

        <!-- Dropdown الإشعارات -->
        <div class="dropdown me-3">
            <button class="btn btn-outline-secondary position-relative"
                    id="notificationsBtn"
                    data-bs-toggle="dropdown"
                    aria-expanded="false">
                <i class="bi bi-bell"></i>
                <?php if(!empty($requests)): ?>
                    <span id="notifDot" class="blink-dot position-absolute top-0 start-100 translate-middle p-1 bg-danger rounded-circle"></span>
                <?php endif; ?>
            </button>

            <ul id="notifMenu" class="dropdown-menu dropdown-menu-end shadow p-2 text-end" style="min-width: 300px;">
                <?php if(!empty($requests)): ?>
                    <?php foreach($requests as $req): ?>
                        <li class="dropdown-item d-flex justify-content-between align-items-center">
                            <span><?= htmlspecialchars($req['full_name']); ?></span>
                            <span class="badge bg-warning"><?= $req['status']; ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="dropdown-item text-center text-muted">لا توجد إشعارات جديدة</li>
                <?php endif; ?>
            </ul>
        </div>
        <nav id="navbar" class="navbar d-flex align-items-center">


            <!-- قائمة المستخدم الجانبية -->
            <div class="dropdown">
                <!-- زر القائمة -->
                <button
                        class="btn p-0 border-0 bg-transparent"
                        id="userMenuButton"
                        type="button"
                        data-bs-toggle="dropdown"
                        data-bs-display="static"
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

<!-- Sidebar -->
<div class="sidebar d-none d-lg-block">

    <ul>
        <li onclick="window.location='dashboar.php'">
            <i class="bi bi-house-door"></i> الرئيسية
        </li>
        <li onclick="window.location='dashboardriver.php'">
            <i class="bi bi-calendar2-check"></i> طلبات الجوازات
        </li>
        <li onclick="window.location='renewpassport.php'">
            <i class="bi bi-arrow-repeat"></i> تجديد الجوازات
        </li>
        <li onclick="window.location='dashboarduser.php'">
            <i class="bi bi-people"></i> المستخدمين
        </li>

        <li>
            <form action="" method="POST" class="w-100">
                <button type="submit" name="logout" class="btn w-100 text-danger text-start d-flex align-items-center gap-2 border-0 bg-transparent">
                    <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
                </button>
            </form>
        </li>
    </ul>
</div>

<!-- Main Content -->
<main class="container-fluid">
    <div class="table-responsive bg-white shadow-sm rounded p-3 custom-table">
        <h5 class="fw-bold mb-3 text-primary" style="font-family: 'Cairo', 'Almarai', 'Rubik', 'Montserrat', sans-serif">قائمة الحجوزات</h5>
        <table class="table table-bordered text-center align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>صورة الشخصية</th>
                <th>الاسم</th>
                <th>رقم الهوية</th>
                <th>رقم الجوال</th>
                <th>تاريخ الميلاد</th>
                <th>مكان الميلاد</th>
                <th>العنوان</th>
                <th>الحالة</th>
                <th>الإجراءات</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($requests) > 0): ?>
                <?php foreach ($requests as $row): ?>
                    <tr>
                        <td>
                            <?php if (!empty($row['applicant_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($row['applicant_photo']); ?>" alt="الصورة الشخصية" width="80" height="80">

                            <?php else: ?>
                                <span class="text-muted">لا توجد صورة</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['identity_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['date_of_birth']); ?></td>
                        <td><?php echo htmlspecialchars($row['place_of_birth']); ?></td>
                        <td><?php echo htmlspecialchars($row['address']); ?></td>
                        <td><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td>
                            <button class="btn btn-primary view-request-btn"
                                    data-request-id="<?= $row['request_id']; ?>"
                                    data-fullname="<?= $row['full_name']; ?>"
                                    data-identity="<?= $row['identity_number']; ?>"
                                    data-photo="<?= $row['applicant_photo']; ?>"
                                    data-id-image="<?= $row['applicant_identity_image']; ?>"
                                    data-passport-number="<?= isset($row['passport_number']) ? $row['passport_number'] : ''; ?>"
                                    data-passport-issued="<?= isset($row['issue_date']) ? $row['issue_date'] : ''; ?>"
                                    data-passport-expiry="<?= isset($row['expiry_date']) ? $row['expiry_date'] : ''; ?>"
                                    data-passport-place="<?= isset($row['place_of_issue']) ? $row['place_of_issue'] : ''; ?>"
                                    data-status="<?= $row['status']; ?>">
                                عرض الطلب
                            </button>







                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="text-muted fs-5 py-4">لا يوجد طلبات في انتظار الموافقة حالياً</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

    </div>

    <!-- مودال عرض الطلب -->
    <div class="modal fade" id="viewRequestModal" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-white rounded-3 shadow">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="viewRequestModalLabel" style="font-family: 'Cairo', 'Almarai', 'Rubik', 'Montserrat', sans-serif">تفاصيل الطلب</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="save_modal" value="1">
                        <input type="hidden" name="request_id" id="modal-request-id">

                        <div class="text-center mb-3">
                            <img id="modal-photo" src="" alt="الصورة الشخصية" width="140" height="140" class="rounded shadow-sm mx-2">
                            <img id="modal-id-image" src="" alt="صورة الهوية" width="140" height="140" class="rounded shadow-sm mx-2">
                        </div>

                        <div class="row g-3">
                            <!-- الاسم الكامل -->
                            <div class="col-md-6 text-center">
                                <label class="form-label fw-bold">الاسم الكامل</label>
                                <input type="text" id="modal-fullname" class="form-control text-center" readonly>
                            </div>

                            <!-- رقم الهوية -->
                            <div class="col-md-6 text-center">
                                <label class="form-label fw-bold">رقم الهوية</label>
                                <input type="text" id="modal-identity" class="form-control text-center" readonly>
                            </div>

                            <!-- رقم الجواز -->
                            <div class="col-md-6 text-center">
                                <label class="form-label fw-bold">رقم الجواز</label>
                                <input type="number" name="passport_number" id="modal-passport-number" class="form-control text-center">
                            </div>

                            <!-- مكان إصدار الجواز -->
                            <div class="col-md-6 text-center">
                                <label class="form-label fw-bold">مكان إصدار الجواز</label>
                                <input type="text" name="place_of_issue" id="modal-passport-place" class="form-control text-center">
                            </div>

                            <!-- تاريخ إصدار الجواز -->
                            <div class="col-md-6 text-center">
                                <label class="form-label fw-bold">تاريخ إصدار الجواز</label>
                                <input type="date" name="issue_date" id="modal-passport-issued" class="form-control text-center">
                            </div>

                            <!-- تاريخ انتهاء الجواز -->
                            <div class="col-md-6 text-center">
                                <label class="form-label fw-bold">تاريخ انتهاء الجواز</label>
                                <input type="date" name="expiry_date" id="modal-passport-expiry" class="form-control text-center">
                            </div>

                            <!-- تغيير حالة الطلب -->
                            <div class="col-md-6 text-center">
                                <label class="form-label fw-bold">تغيير حالة الطلب</label>
                                <select name="status" id="modal-status" class="form-select text-center">
                                    <option value="في انتظار الموافقة">في انتظار الموافقة</option>
                                    <option value="قيد المراجعة">قيد المراجعة</option>
                                    <option value="تمت الموافقة">تمت الموافقة</option>
                                    <option value="مرفوض">مرفوض</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                        <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const viewButtons = document.querySelectorAll('.view-request-btn');
            const modalEl = document.getElementById('viewRequestModal');
            const modal = new bootstrap.Modal(modalEl);

            viewButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    document.getElementById('modal-request-id').value = btn.dataset.requestId;
                    document.getElementById('modal-fullname').value = btn.dataset.fullname;
                    document.getElementById('modal-identity').value = btn.dataset.identity;
                    document.getElementById('modal-photo').src = btn.dataset.photo;
                    document.getElementById('modal-id-image').src = btn.dataset.idImage;
                    document.getElementById('modal-passport-number').value = btn.dataset.passportNumber;
                    document.getElementById('modal-passport-issued').value = btn.dataset.passportIssued;
                    document.getElementById('modal-passport-expiry').value = btn.dataset.passportExpiry;
                    document.getElementById('modal-passport-place').value = btn.dataset.passportPlace;
                    document.getElementById('modal-status').value = btn.dataset.status;


                // فتح المودل
                    modal.show();
                });
            });
        });
    </script>
</main>
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
<script src="assets/js/main.js"></script>
</body>

</html>

<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// --- การตั้งค่าการเชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink";

$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) {
    die("Connection failed: " . $condb->connect_error);
}
$condb->set_charset("utf8mb4");

// --- ดึงข้อมูลพื้นฐาน ---
$loggedInUserName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Designer';
// --- ดึงชื่อผู้ใช้ที่ล็อกอิน ---
if (isset($_SESSION['user_id'])) {
    $loggedInUserName = $_SESSION['username'] ?? $_SESSION['full_name'] ?? '';
    if (empty($loggedInUserName)) {
        $user_id = $_SESSION['user_id'];
        $sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
        $stmt_user = $condb->prepare($sql_user);
        if ($stmt_user) {
            $stmt_user->bind_param("i", $user_id);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();
            if ($result_user->num_rows === 1) {
                $user_info = $result_user->fetch_assoc();
                $loggedInUserName = $user_info['first_name'] . ' ' . $user_info['last_name'];
            }
            $stmt_user->close();
        }
    }
}

// --- ดึงข้อมูลหมวดหมู่ทั้งหมดสำหรับ Filter ---
$categories = [];
$sql_categories = "SELECT category_id, category_name FROM job_categories ORDER BY category_name";
$result_categories = $condb->query($sql_categories);
if ($result_categories) {
    $categories = $result_categories->fetch_all(MYSQLI_ASSOC);
}

// --- สร้าง Logic สำหรับการ Filter และ Search ---
$search_keyword = $_GET['search'] ?? '';
$filter_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$type = $_GET['type'] ?? 'postings'; // Default to 'postings' if not set

$jobs = [];
$params = [];
$types = '';

if ($type === 'postings') {
    $page_title = "ประกาศรับงานทั้งหมด";
    $sql = "SELECT
                jp.post_id AS id,
                jp.title,
                jp.description,
                jp.price_range,
                jp.posted_date,
                'posting' AS type,
                u.first_name,
                u.last_name,
                jc.category_name,
                uf.file_path AS job_image_path
            FROM job_postings AS jp
            JOIN users AS u ON jp.designer_id = u.user_id
            LEFT JOIN job_categories AS jc ON jp.category_id = jc.category_id
            LEFT JOIN uploaded_files AS uf ON jp.main_image_id = uf.file_id
            WHERE jp.status = 'active'";

    if (!empty($search_keyword)) {
        $sql .= " AND (jp.title LIKE ? OR jp.description LIKE ?)";
        $keyword_param = "%" . $search_keyword . "%";
        array_push($params, $keyword_param, $keyword_param);
        $types .= 'ss';
    }

    if ($filter_category > 0) {
        $sql .= " AND jp.category_id = ?";
        $params[] = $filter_category;
        $types .= 'i';
    }
    $sql .= " ORDER BY jp.posted_date DESC";

} else { // Default to 'requests'
    $page_title = "ประกาศหางานทั้งหมด";
     $sql = "SELECT
                cjr.request_id AS id,
                cjr.title,
                cjr.description,
                cjr.budget AS price_range,
                cjr.posted_date,
                'request' AS type,
                u.first_name,
                u.last_name,
                jc.category_name,
                NULL AS job_image_path
            FROM client_job_requests AS cjr
            JOIN users AS u ON cjr.client_id = u.user_id
            LEFT JOIN job_categories AS jc ON cjr.category_id = jc.category_id
            WHERE cjr.status = 'open'";
    
    if (!empty($search_keyword)) {
        $sql .= " AND (cjr.title LIKE ? OR cjr.description LIKE ?)";
        $keyword_param = "%" . $search_keyword . "%";
        array_push($params, $keyword_param, $keyword_param);
        $types .= 'ss';
    }

    if ($filter_category > 0) {
        $sql .= " AND cjr.category_id = ?";
        $params[] = $filter_category;
        $types .= 'i';
    }
     $sql .= " ORDER BY cjr.posted_date DESC";
}

$stmt = $condb->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $jobs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    error_log("SQL Error: " . $condb->error);
}

$condb->close();
?>
<?php include 'includes/header.php'; ?>
<body class="min-h-screen flex flex-col">

    <nav class="bg-white/90 backdrop-blur-sm p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="index.php"><img src="dist/img/logo.png" alt="PixelLink Logo" class="h-12 transition-transform hover:scale-105"></a>
            <div class="space-x-4 flex items-center">
                <?php if (isset($_SESSION['user_id'])) : ?>
                    <span class="font-medium text-slate-700">สวัสดี, <?= htmlspecialchars($loggedInUserName) ?>!</span>
                    <?php
                        // Determine the correct path to the profile page based on user type
                        $profile_path = 'designer/view_profile.php'; // Default path
                        if ($_SESSION['user_type'] === 'client') {
                            $profile_path = 'client/view_profile.php';
                        }
                    ?>
                    <a href="<?= $profile_path ?>?user_id=<?= $_SESSION['user_id']; ?>" class="btn-primary text-white px-5 py-2 rounded-lg font-medium shadow-md">ดูโปรไฟล์</a>
                    <a href="logout.php" class="btn-danger text-white px-5 py-2 rounded-lg font-medium shadow-md">ออกจากระบบ</a>
                <?php else : ?>
                    <a href="login.php" class="px-3 py-1.5 sm:px-5 sm:py-2 rounded-lg font-medium border-2 border-transparent hover:border-blue-300 hover:text-blue-600 transition duration-300 text-gray-700">เข้าสู่ระบบ</a>
                    <a href="register.php" class="btn-primary text-white px-5 py-2 rounded-lg font-semibold shadow-md">สมัครสมาชิก</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="flex-grow">
        <section id="job-listings" class="py-12 md:py-16 bg-gradient-to-br from-blue-50 to-gray-50">
            <div class="container mx-auto px-4 md:px-6">

                <div class="text-center mb-12">
                    <h1 class="text-4xl md:text-5xl font-bold text-gradient"><?= htmlspecialchars($page_title) ?></h1>
                    <p class="mt-4 text-lg text-slate-600">ค้นหางานที่ใช่ หรือนักออกแบบที่โดนใจคุณได้ที่นี่</p>
                </div>

                <form action="job_listings.php" method="GET" class="mb-12 p-6 bg-white rounded-xl shadow-lg">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div class="col-span-1 md:col-span-2">
                            <label for="search-keyword" class="block text-sm font-medium text-slate-700 mb-1">ค้นหางาน</label>
                            <input type="text" name="search" id="search-keyword" value="<?= htmlspecialchars($search_keyword) ?>" placeholder="เช่น 'โลโก้', 'วาดภาพประกอบ'" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="filter-category" class="block text-sm font-medium text-slate-700 mb-1">หมวดหมู่</label>
                            <select name="category" id="filter-category" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">ทุกหมวดหมู่</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>" <?= ($filter_category == $cat['category_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary text-white px-6 py-2 rounded-lg font-semibold shadow-md w-full">ค้นหา</button>
                    </div>
                </form>

                <?php if (empty($jobs)): ?>
                    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 rounded-lg text-center mt-12">
                        <p class="font-bold text-xl">ไม่พบผลลัพธ์ที่ตรงกัน</p>
                        <p class="mt-2">กรุณาลองเปลี่ยนคำค้นหาหรือตัวกรองของคุณ</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                        <?php foreach ($jobs as $job): ?>
                            <div class="card-item flex flex-col">
                                <?php
                                $image_source = 'dist/img/pdpa02.jpg'; // Default image
                                if (!empty($job['job_image_path'])) {
                                    $correct_path = str_replace('../', '', $job['job_image_path']);
                                    if (file_exists($correct_path)) {
                                        $image_source = htmlspecialchars($correct_path);
                                    }
                                }
                                ?>
                                <a href="job_detail.php?id=<?= $job['id'] ?>&type=<?= $job['type'] ?>">
                                    <img src="<?= $image_source ?>" alt="ภาพประกอบงาน: <?= htmlspecialchars($job['title']) ?>" class="card-image">
                                </a>
                                <div class="p-4 md:p-6 flex-grow flex flex-col justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900 line-clamp-2"><?= htmlspecialchars($job['title']) ?></h3>
                                        
                                        <p class="text-sm text-gray-600 my-2">
                                            <i class="fas fa-user mr-1 text-gray-400"></i>
                                            <?= htmlspecialchars($job['first_name'] . ' ' . $job['last_name']) ?>
                                        </p>
                                        
                                        <p class="text-sm text-gray-500 mb-2">หมวดหมู่: <?= htmlspecialchars($job['category_name'] ?? 'ไม่ระบุ') ?></p>
                                        <p class="text-sm text-gray-700 line-clamp-3 font-light"><?= htmlspecialchars($job['description']) ?></p>
                                    </div>
                                    <div class="mt-4">
                                        <p class="text-lg font-semibold text-green-700">ราคา: <?= htmlspecialchars($job['price_range'] ?? 'สอบถาม') ?></p>
                                        <p class="text-xs text-gray-500">ประกาศเมื่อ: <?= date('d M Y', strtotime($job['posted_date'])) ?></p>
                                        <a href="job_detail.php?id=<?= $job['id'] ?>&type=<?= $job['type'] ?>" class="mt-2 inline-block btn-primary text-white px-4 py-2 rounded-lg font-medium text-sm shadow-lg w-full text-center">ดูรายละเอียด</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    

<?php include 'includes/footer.php'; ?>
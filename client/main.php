<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// 1. เรียกใช้ไฟล์ connect.php เพื่อเชื่อมต่อฐานข้อมูล
require_once '../connect.php';

// 2. ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    header("Location: ../login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];

// 3. --- [เพิ่มส่วนนี้] --- ดึงข้อมูลจำนวนข้อความที่ยังไม่อ่าน
$unread_count = 0;
$sql_unread = "SELECT COUNT(message_id) AS total_unread FROM messages WHERE to_user_id = ? AND is_read = 0";
$stmt_unread = $conn->prepare($sql_unread);
if ($stmt_unread) {
    $stmt_unread->bind_param("i", $current_user_id);
    $stmt_unread->execute();
    $result_unread = $stmt_unread->get_result();
    if ($row_unread = $result_unread->fetch_assoc()) {
        $unread_count = $row_unread['total_unread'];
    }
    $stmt_unread->close();
}

// --- [เพิ่มส่วนนี้] --- ดึงข้อมูลจำนวนคำขอจ้างงานที่รอการพิจารณา (status = 'proposed')
$proposed_count = 0;
$sql_proposed = "SELECT COUNT(request_id) AS total_proposed FROM client_job_requests WHERE client_id = ? AND status = 'proposed'";
$stmt_proposed = $conn->prepare($sql_proposed);
if ($stmt_proposed) {
    $stmt_proposed->bind_param("i", $current_user_id);
    $stmt_proposed->execute();
    $result_proposed = $stmt_proposed->get_result();
    if ($row_proposed = $result_proposed->fetch_assoc()) {
        $proposed_count = $row_proposed['total_proposed'];
    }
    $stmt_proposed->close();
}

// 4. ดึงข้อมูลอื่นๆ ที่จำเป็นสำหรับหน้านี้
// ดึงข้อมูลหมวดหมู่
$categories = [];
$sql_categories = "SELECT category_id, category_name FROM job_categories ORDER BY RAND() LIMIT 6";
$result_cat = $conn->query($sql_categories);
if ($result_cat) {
    $categories = $result_cat->fetch_all(MYSQLI_ASSOC);
}

// ดึงชื่อผู้ใช้ที่ล็อกอิน
$loggedInUserName = $_SESSION['username'] ?? '';
if (empty($loggedInUserName)) {
    $sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
    $stmt_user = $conn->prepare($sql_user);
    if ($stmt_user) {
        $stmt_user->bind_param("i", $current_user_id);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        if ($user_info = $result_user->fetch_assoc()) {
            $loggedInUserName = $user_info['first_name'] . ' ' . $user_info['last_name'];
        }
        $stmt_user->close();
    }
}

// ดึงข้อมูล Job Postings
$job_postings_from_others = [];
$sql_job_postings = "SELECT
                        jp.post_id, jp.title, jp.description, jp.price_range, jp.posted_date,
                        u.first_name, u.last_name, jc.category_name,
                        uf.file_path AS job_image_path
                    FROM job_postings AS jp
                    JOIN users AS u ON jp.designer_id = u.user_id
                    LEFT JOIN job_categories AS jc ON jp.category_id = jc.category_id
                    LEFT JOIN uploaded_files AS uf ON jp.main_image_id = uf.file_id
                    WHERE jp.status = 'active'
                    ORDER BY jp.posted_date DESC
                    LIMIT 12";
$result_job_postings = $conn->query($sql_job_postings);
if ($result_job_postings) {
    $job_postings_from_others = $result_job_postings->fetch_all(MYSQLI_ASSOC);
}

// 5. เรียกใช้ Header
include '../includes/header.php';
?>
<main class="flex-grow">
    <nav class="bg-white/80 backdrop-blur-sm p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="main.php">
                <img src="../dist/img/logo.png" alt="PixelLink Logo" class="h-12 transition-transform hover:scale-105">
            </a>
            <div class="space-x-4 flex items-center">
                <span class="font-medium text-slate-700">สวัสดี, <?= htmlspecialchars($loggedInUserName) ?>!</span>
                <!-- <a href="view_profile.php?user_id=<?= $_SESSION['user_id']; ?>" class="btn-primary text-white px-5 py-2 rounded-lg font-medium shadow-md">ดูโปรไฟล์</a> -->
                <a href="../logout.php" class="btn-danger text-white px-5 py-2 rounded-lg font-medium shadow-md">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <header class="hero-section flex-grow flex items-center justify-center text-white py-16 relative overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('../dist/img/cover.png');"></div>
        <div class="text-center text-white p-6 md:p-10 rounded-xl shadow-2xl max-w-4xl relative z-10 mx-4">
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-extralight mb-4 md:mb-6 leading-tight">ค้นหาฟรีแลนซ์มืออาชีพ</h1>
            <p class="text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed opacity-90 font-light">เปลี่ยนไอเดียของคุณให้เป็นจริง กับนักออกแบบกว่าพันคนบน PixelLink</p>
            <form action="../job_listings.php" method="GET" class="mt-8 max-w-2xl mx-auto">
                <input type="hidden" name="type" value="postings">
                <div class="relative">
                    <input type="search" name="search" placeholder="คุณกำลังมองหางานดีไซน์ประเภทไหน? (เช่น 'ออกแบบโลโก้', 'วาดภาพประกอบ')" class="w-full p-4 pr-12 text-gray-900 rounded-full shadow-lg focus:ring-4 focus:ring-blue-300 focus:outline-none border-0">
                    <button type="submit" class="absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 hover:text-blue-600">
                        <i class="fas fa-search fa-lg"></i>
                    </button>
                </div>
            </form>
        </div>
    </header>

    <br><br><br><br><br>
    <div class="container mx-auto px-4 md:px-6 -mt-20">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center space-x-4 hover:shadow-xl transition-shadow duration-300">
                <div class="bg-blue-100 text-blue-600 p-4 rounded-full"><i class="fas fa-tasks fa-lg"></i></div>
                <div>
                    <h3 class="font-bold text-gray-800">จัดการคำขอจ้างงานของคุณ</h3>
                    <?php if ($proposed_count > 0): ?>
                        <a href="my_requests.php" class="text-sm text-red-600 font-bold hover:underline">
                            มี <?= $proposed_count ?> ข้อเสนอรอการพิจารณา &rarr;
                        </a>
                    <?php else: ?>
                        <a href="my_requests.php" class="text-sm text-blue-600 hover:underline">
                            ดูคำขอจ้างงานทั้งหมดของคุณ &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            </div>


            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center space-x-4 hover:shadow-xl transition-shadow duration-300 relative">
                <div class="bg-green-100 text-green-600 p-4 rounded-full"><i class="fas fa-comments fa-lg"></i></div>
                <div>
                    <h3 class="font-bold text-gray-800">กล่องข้อความ</h3>
                    <?php if ($unread_count > 0): ?>
                        <a href="../messages.php" class="text-sm text-red-600 font-bold hover:underline">
                            คุณมี <?php echo $unread_count; ?> ข้อความใหม่ &rarr;
                        </a>
                    <?php else: ?>
                        <a href="../messages.php" class="text-sm text-blue-600 hover:underline">
                            ดูข้อความทั้งหมด &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center space-x-4 hover:shadow-xl transition-shadow duration-300">
                <div class="bg-purple-100 text-purple-600 p-4 rounded-full"><i class="fas fa-user-edit fa-lg"></i></div>
                <div>
                    <h3 class="font-bold text-gray-800">โปรไฟล์ของคุณ</h3>
                    <a href="edit_profile.php" class="text-sm text-blue-600 hover:underline">แก้ไขข้อมูลส่วนตัว &rarr;</a>
                </div>
            </div>
        </div>
        <section id="categories" class="mb-16">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">หมวดหมู่น่าสนใจ</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php
                $icons = ['fas fa-palette', 'fas fa-vector-square', 'fas fa-desktop', 'fas fa-camera-retro', 'fas fa-pen-nib', 'fas fa-bullhorn'];
                $i = 0;
                foreach ($categories as $cat): ?>
                    <a href="job_listings_client.php?type=postings&category=<?= $cat['category_id'] ?>" class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300 text-center">
                        <i class="<?= $icons[$i++] ?? 'fas fa-star' ?> fa-2x text-blue-500 mb-2"></i>
                        <span class="font-semibold text-gray-700 text-sm"><?= htmlspecialchars($cat['category_name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>



    <section id="available-jobs" class="py-12 md:py-16 bg-white">
        <div class="container mx-auto px-4 md:px-6">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-8 md:mb-10">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-semibold text-gray-800 mb-4 sm:mb-0 text-center sm:text-left text-gradient">บริการแนะนำ</h2>
                <a href="job_listings_client.php?type=postings" class="btn-secondary px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg font-medium text-sm md:text-base">
                    ดูทั้งหมด <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>

            <?php if (empty($job_postings_from_others)): ?>
                <div class="bg-blue-100 text-blue-700 p-4 rounded-lg text-center">ยังไม่มีประกาศรับงานในขณะนี้</div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                    <?php foreach ($job_postings_from_others as $job): ?>
                        <div class="card-item flex flex-col">
                            <?php
                            $image_path = str_replace('', '', $job['job_image_path']);
                            $image_source = (!empty($image_path) && file_exists($image_path)) ? htmlspecialchars($image_path) : '../dist/img/pdpa02.jpg';
                            ?>
                            <a href="../job_detail_client.php?id=<?= $job['post_id'] ?>&type=posting">
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
                                    <a href="../job_detail_client.php?id=<?= $job['post_id'] ?>&type=posting" class="mt-2 inline-block btn-primary text-white px-4 py-2 rounded-lg font-medium text-sm shadow-lg w-full text-center">ดูรายละเอียด</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    </div>
</main>

<?php
include '../includes/footer.php';
?>
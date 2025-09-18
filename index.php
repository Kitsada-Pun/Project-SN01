<?php
// public/index.php
session_start();
date_default_timezone_set('Asia/Bangkok');

// --- การตั้งค่าการเชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink";

$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) {
    error_log("Connection failed: " . $condb->connect_error);
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล");
}
$condb->set_charset("utf8mb4");

// ตรวจสอบว่าล็อกอินอยู่แล้วหรือไม่ และ redirect ไปยังหน้า main ของ user type นั้นๆ
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_type']) {
        case 'admin':
            header("Location: admin/main.php");
            break;
        case 'designer':
            header("Location: designer/main.php");
            break;
        case 'client':
            header("Location: client/main.php");
            break;
        default:
            // หากมี session แต่ user_type ไม่ถูกต้อง ให้เคลียร์ session แล้วไปหน้า login
            session_unset();
            session_destroy();
            header("Location: login.php");
            break;
    }
    exit();
}

// ================================================================================= //
// ========= จุดที่ 1: แก้ไข SQL ของ $job_postings เพื่อดึง Path รูปภาพ ========= //
// ================================================================================= //
$job_postings = [];
$sql_job_postings = "SELECT
                        jp.post_id,
                        jp.title,
                        jp.description,
                        jp.price_range,
                        jp.posted_date,
                        u.first_name,
                        u.last_name,
                        u.username, -- << เพิ่มบรรทัดนี้เพื่อดึง username
                        jc.category_name,
                        uf.file_path AS job_image_path
                    FROM job_postings AS jp
                    JOIN users AS u ON jp.designer_id = u.user_id
                    LEFT JOIN job_categories AS jc ON jp.category_id = jc.category_id
                    LEFT JOIN uploaded_files AS uf ON jp.main_image_id = uf.file_id
                    WHERE jp.status = 'active'
                    ORDER BY jp.posted_date DESC
                    LIMIT 12";

$result_job_postings = $condb->query($sql_job_postings);
if ($result_job_postings) {
    $job_postings = $result_job_postings->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("SQL Error (job_postings): " . $condb->error);
}


// --- (โค้ดดึงข้อมูล Client Job Requests เหมือนเดิม) ---
$client_job_requests = [];
// ... (สามารถใส่โค้ดดึง $client_job_requests เดิมของคุณไว้ตรงนี้ได้เลย) ...


$condb->close();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PixelLink | เชื่อมโยงโลกแห่งการออกแบบและผู้ว่าจ้าง</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />

    <style>
    * { font-family: 'Kanit', sans-serif; font-style: normal; font-weight: 400; }
body { background: linear-gradient(135deg, #f0f4f8 0%, #e8edf3 100%); color: #2c3e50; overflow-x: hidden; }
.navbar { background-color: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-bottom: 1px solid rgba(0, 0, 0, 0.05); }
.btn-primary { background: linear-gradient(45deg, #0a5f97 0%, #0d96d2 100%); color: white; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(13, 150, 210, 0.3); }
.btn-primary:hover { background: linear-gradient(45deg, #0d96d2 0%, #0a5f97 100%); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(13, 150, 210, 0.5); }
.btn-secondary { background-color: #6c757d; color: white; transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(108, 117, 125, 0.2); }
.btn-secondary:hover { background-color: #5a6268; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(108, 117, 125, 0.4); }
.text-gradient { background: linear-gradient(45deg, #0a5f97, #0d96d2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.text-gradient-light { background: linear-gradient(45deg, #87ceeb, #add8e6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
.pixellink-logo { font-weight: 700; font-size: 2.25rem; background: linear-gradient(45deg, #0a5f97, #0d96d2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.pixellink-logo b { color: #0d96d2; }
.card-item { background: white; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); transition: all 0.3s ease; display: flex; flex-direction: column; }
.card-item:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.12); }
.card-image { width: 100%; aspect-ratio: 16/9; object-fit: cover; border-top-left-radius: 1rem; border-top-right-radius: 1rem; }
.hero-section { background-image: url('dist/img/cover.png'); background-size: cover; background-position: center; position: relative; z-index: 1; padding: 8rem 0; }
.hero-section::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.4); z-index: -1; }
.feature-icon { color: #0d96d2; transition: transform 0.3s ease; }
.card-item:hover .feature-icon { transform: translateY(-3px); }

@media (max-width: 768px) {
  .hero-section { padding: 6rem 0; }
  .hero-section h1 { font-size: 2.8rem; }
  .hero-section p { font-size: 1rem; }
  .hero-section .space-x-0 { flex-direction: column; gap: 1rem; }
  .hero-section .btn-primary, .hero-section .btn-secondary { width: 90%; max-width: none; font-size: 0.9rem; padding: 0.75rem 1.25rem; }
  .pixellink-logo { font-size: 1.6rem; }
  .navbar .px-5 { padding-left: 0.5rem; padding-right: 0.5rem; }
  .navbar .py-2 { padding-top: 0.3rem; padding-bottom: 0.3rem; }
  h2 { font-size: 1.8rem; }
  .card-item { border-radius: 0.75rem; padding: 1rem; }
  .card-image { height: 160px; }
  .sm\:grid-cols-2 { grid-template-columns: 1fr; }
  .flex-col.sm\:flex-row { flex-direction: column; }
  .flex-col.sm\:flex-row>*:not(:last-child) { margin-bottom: 1rem; }
  .md\:mb-0 { margin-bottom: 1rem; }
  .footer-links { flex-direction: column; gap: 0.5rem; }
}

@media (max-width: 480px) {
  .hero-section h1 { font-size: 2.2rem; }
  .hero-section p { font-size: 0.875rem; }
  .pixellink-logo { font-size: 1.4rem; }
  h2 { font-size: 1.5rem; }
  .container { padding-left: 1rem; padding-right: 1rem; }
  .px-6 { padding-left: 1rem; padding-right: 1rem; }
  .p-10 { padding: 1.5rem; }
  .card-item { padding: 0.75rem; }
  .card-image { height: 120px; }
}
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex flex-col">

    <nav class="navbar p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="index.php" class="transition duration-300 hover:opacity-80">
                <img src="dist/img/logo.png" alt="PixelLink Logo" class="h-12"> </a>
            <div class="space-x-2 sm:space-x-4 flex items-center">
                <a href="login.php"
                    class="px-3 py-1.5 sm:px-5 sm:py-2 rounded-lg font-medium border-2 border-transparent hover:border-blue-300 hover:text-blue-600 transition duration-300 text-gray-700">เข้าสู่ระบบ</a>
                <a href="register.php"
                    class="btn-primary px-4 py-2 sm:px-5 sm:py-2 rounded-lg font-medium shadow-lg">สมัครสมาชิก</a>
            </div>
        </div>
    </nav>

    <header class="hero-section flex-grow flex items-center justify-center">
        <div
            class="text-center text-white p-6 md:p-10 rounded-xl shadow-2xl max-w-4xl animate-fade-in relative z-10 mx-4">
            <h1
                class="text-4xl sm:text-5xl md:text-6xl font-extralight mb-4 md:mb-6 text-gradient-light drop-shadow-lg leading-tight">
                เชื่อมโยงโอกาส<br>สร้างสรรค์ผลงานระดับมืออาชีพ</h1>
            <p
                class="text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed opacity-90 drop-shadow-md font-light">
                PixelLink แพลตฟอร์มชั้นนำที่เชื่อมต่อนักออกแบบมากฝีมือและผู้ว่าจ้างที่ต้องการงานดีไซน์คุณภาพสูง
                สร้างสรรค์อนาคตแห่งการออกแบบไปพร้อมกัน</p>
            <div class="space-x-0 sm:space-x-4 flex flex-col sm:flex-row justify-center items-center">
                <a href="register.php"
                    class="btn-primary px-6 py-3 sm:px-8 sm:py-4 text-base sm:text-lg rounded-lg font-semibold shadow-xl hover:scale-105 w-full sm:w-auto mb-3 sm:mb-0">เริ่มต้นใช้งาน
                    <i class="fas fa-arrow-right ml-2"></i></a>
                <a href="#job-postings"
                    class="btn-secondary px-6 py-3 sm:px-8 sm:py-4 text-base sm:text-lg rounded-lg font-semibold shadow-xl hover:scale-105 w-full sm:w-auto">ค้นหางาน
                    <i class="fas fa-search ml-2"></i></a>
            </div>
        </div>
    </header>

    <section id="job-postings" class="py-12 md:py-16 bg-gradient-to-br from-blue-50 to-gray-50">
        <div class="container mx-auto px-4 md:px-6">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-8 md:mb-10">
                <h2
                    class="text-2xl sm:text-3xl md:text-4xl font-semibold text-gray-800 mb-4 sm:mb-0 text-center sm:text-left text-gradient">
                    ประกาศรับงานล่าสุด</h2>
                <a href="job_listings.php?type=postings"
                    class="btn-secondary px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg font-medium text-sm md:text-base">
                    ดูทั้งหมด <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>

            <?php if (empty($job_postings)) : ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg relative text-center">
                <span class="block sm:inline">ยังไม่มีงานประกาศรับงานในขณะนี้</span>
            </div>
            <?php else : ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                <?php foreach ($job_postings as $job) : ?>
                    <div class="card-item flex  flex-col">

                        <?php
                        $image_source = 'dist/img/pdpa02.jpg'; // รูปสำรอง
                        if (!empty($job['job_image_path'])) {
                            $correct_path = str_replace('../', '', $job['job_image_path']);
                            if (file_exists(htmlspecialchars($correct_path))) {
                                $image_source = htmlspecialchars($correct_path);
                            }
                        }
                        ?>
                        <a href="job_detail.php?id=<?= $job['post_id'] ?>&type=posting">
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
                                <a href="job_detail.php?id=<?= $job['post_id'] ?>&type=posting" class="mt-2 inline-block btn-primary text-white px-4 py-2 rounded-lg font-medium text-sm shadow-lg w-full text-center">ดูรายละเอียด</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>



    <section id="features" class="py-12 md:py-16 bg-gradient-to-br from-gray-50 to-blue-50">
        <div class="container mx-auto px-4 md:px-6 text-center">
            <h2 class="text-3xl md:text-4xl font-semibold mb-8 md:mb-12 text-gradient">PixelLink:
                พันธมิตรทางธุรกิจของคุณ</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-10">
                <div class="card-item p-6 md:p-8 flex flex-col items-center">
                    <i class="fas fa-search fa-3x feature-icon mb-4 md:mb-6"></i>
                    <h3 class="text-xl md:text-2xl font-semibold mb-2 md:mb-4 text-gray-800">ค้นหานักออกแบบที่เหมาะสม
                    </h3>
                    <p class="text-sm md:text-base text-gray-600 font-light">
                        เข้าถึงเครือข่ายนักออกแบบผู้เชี่ยวชาญจากหลากหลายสาขา พร้อมโปรไฟล์และผลงานที่เชื่อถือได้
                        เพื่อคัดเลือกบุคลากรที่ตรงกับความต้องการของคุณ</p>
                </div>
                <div class="card-item p-6 md:p-8 flex flex-col items-center">
                    <i class="fas fa-lightbulb fa-3x feature-icon mb-4 md:mb-6" style="color: #0d96d2;"></i>
                    <h3 class="text-xl md:text-2xl font-semibold mb-2 md:mb-4 text-gray-800">สร้างสรรค์นวัตกรรมดีไซน์
                    </h3>
                    <p class="text-sm md:text-base text-gray-600 font-light">
                        แพลตฟอร์มที่สนับสนุนการทำงานร่วมกันอย่างมีประสิทธิภาพระหว่างผู้ว่าจ้างและนักออกแบบ
                        เพื่อสร้างสรรค์ผลงานที่โดดเด่นและตอบโจทย์ธุรกิจ</p>
                </div>
                <div class="card-item p-6 md:p-8 flex flex-col items-center">
                    <i class="fas fa-handshake fa-3x feature-icon mb-4 md:mb-6" style="color: #28a745;"></i>
                    <h3 class="text-xl md:text-2xl font-semibold mb-2 md:mb-4 text-gray-800">ความร่วมมือที่โปร่งใส</h3>
                    <p class="text-sm md:text-base text-gray-600 font-light">ระบบจัดการโครงการ, การสื่อสาร,
                        และการชำระเงินที่ครบวงจร เพื่อความราบรื่นและโปร่งใสในทุกขั้นตอนของกระบวนการทำงาน</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 md:py-16 bg-gradient-to-r from-blue-700 to-indigo-800 text-white text-center">
        <div class="container mx-auto px-4 md:px-6">
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold mb-4 md:mb-6 drop-shadow-lg leading-tight">
                ยกระดับโครงการของคุณ<br>ด้วย PixelLink วันนี้</h2>
            <p class="text-lg sm:text-xl md:text-2xl mb-6 md:mb-8 opacity-95 drop-shadow-md font-light">
                ไม่ว่าคุณจะเป็นองค์กรที่กำลังมองหานักออกแบบ หรือนักออกแบบมืออาชีพที่กำลังแสวงหาโอกาสใหม่ๆ</p>
            <div class="space-x-0 sm:space-x-4 flex flex-col sm:flex-row justify-center items-center">
                <a href="register.php"
                    class="btn-primary px-6 py-3 sm:px-8 sm:py-4 text-base sm:text-lg rounded-lg font-semibold shadow-xl hover:scale-105 w-full sm:w-auto mb-3 sm:mb-0">เริ่มต้นใช้งานทันที!</a>
                <a href="login.php"
                    class="px-6 py-3 sm:px-8 sm:py-4 text-base sm:text-lg rounded-lg font-semibold bg-white text-blue-700 shadow-xl hover:bg-gray-100 hover:scale-105 transition duration-300 w-full sm:w-auto">เข้าสู่ระบบ</a>
            </div>
        </div>
    </section>

    <footer class="bg-gray-900 text-gray-300 py-8">
        <div class="container mx-auto px-4 md:px-6 text-center">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <a href="index.php"
                    class="text-2xl sm:text-3xl font-bold pixellink-logo mb-4 md:mb-0 transition duration-300 hover:opacity-80">Pixel<b>Link</b></a>
                <div class="flex flex-wrap justify-center space-x-2 md:space-x-6 text-sm md:text-base footer-links">
                    <a href="#"
                        class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">เกี่ยวกับเรา</a>
                    <a href="#" class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">ติดต่อเรา</a>
                    <a href="#"
                        class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">เงื่อนไขการใช้งาน</a>
                    <a href="#"
                        class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">นโยบายความเป็นส่วนตัว</a>
                </div>
            </div>
            <hr class="border-gray-700 my-6">
            <p class="text-xs md:text-sm font-light">&copy; <?php echo date('Y'); ?> PixelLink. All rights reserved.</p>
        </div>
    </footer>
    

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    document.addEventListener('DOMContentLoaded', () => {
        const heroContent = document.querySelector('.animate-fade-in');
        heroContent.style.opacity = '0';
        setTimeout(() => {
            heroContent.style.transition = 'opacity 1s ease-out';
            heroContent.style.opacity = '1';
        }, 100);

        const cards = document.querySelectorAll('.animate-card-appear');
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '0';
                    entry.target.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        entry.target.style.transition =
                            'opacity 0.6s ease-out, transform 0.6s ease-out';
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, 200); 
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        cards.forEach(card => {
            observer.observe(card);
        });
    });
    </script>
</body>

</html>
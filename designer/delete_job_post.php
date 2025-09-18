<?php
session_start();

// --- ตรวจสอบการล็อกอินและสิทธิ์ ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'designer') {
    header("Location: ../login.php");
    exit();
}

// --- ตรวจสอบว่ามี ID ส่งมาหรือไม่ ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: view_profile.php?user_id=" . $_SESSION['user_id']); // กลับหน้าโปรไฟล์ตัวเอง
    exit();
}

// --- การเชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink";
$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) { die("Connection Failed: " . $condb->connect_error); }
$condb->set_charset("utf8mb4");

$post_id_to_delete = (int)$_GET['id'];
$designer_id = $_SESSION['user_id'];

$condb->begin_transaction();

try {
    // --- 1. ดึงข้อมูลโพสต์เพื่อตรวจสอบความเป็นเจ้าของ และหา ID รูปภาพ ---
    $sql_fetch = "SELECT main_image_id, uf.file_path FROM job_postings jp 
                  LEFT JOIN uploaded_files uf ON jp.main_image_id = uf.file_id
                  WHERE post_id = ? AND designer_id = ?";
    $stmt_fetch = $condb->prepare($sql_fetch);
    $stmt_fetch->bind_param("ii", $post_id_to_delete, $designer_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    
    if ($result->num_rows !== 1) {
        throw new Exception("ไม่พบโพสต์ที่ต้องการลบ หรือคุณไม่มีสิทธิ์ในการลบโพสต์นี้");
    }
    
    $post_data = $result->fetch_assoc();
    $main_image_id = $post_data['main_image_id'];
    $file_path = $post_data['file_path'];
    $stmt_fetch->close();

    // --- 2. ลบโพสต์ออกจากตาราง job_postings ---
    $sql_delete_post = "DELETE FROM job_postings WHERE post_id = ?";
    $stmt_delete_post = $condb->prepare($sql_delete_post);
    $stmt_delete_post->bind_param("i", $post_id_to_delete);
    if (!$stmt_delete_post->execute()) {
        throw new Exception("Error deleting job post: " . $stmt_delete_post->error);
    }
    $stmt_delete_post->close();

    // --- 3. (ถ้ามีรูปภาพ) ลบข้อมูลรูปภาพออกจากตาราง uploaded_files และลบไฟล์จริง ---
    if ($main_image_id) {
        // ลบจากตาราง
        $sql_delete_file = "DELETE FROM uploaded_files WHERE file_id = ?";
        $stmt_delete_file = $condb->prepare($sql_delete_file);
        $stmt_delete_file->bind_param("i", $main_image_id);
        $stmt_delete_file->execute();
        $stmt_delete_file->close();

        // ลบไฟล์ออกจากเซิร์ฟเวอร์
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // --- 4. ยืนยันการทำรายการ ---
    $condb->commit();
    $_SESSION['success_message'] = 'ลบโพสต์สำเร็จแล้ว';

} catch (Exception $e) {
    // --- หากเกิดข้อผิดพลาด ให้ย้อนกลับการทำรายการทั้งหมด ---
    $condb->rollback();
    $_SESSION['error_message'] = 'เกิดข้อผิดพลาดในการลบโพสต์: ' . $e->getMessage();
}

$condb->close();

// --- 5. กลับไปยังหน้าโปรไฟล์ ---
header("Location: view_profile.php?user_id=" . $designer_id);
exit();
?>
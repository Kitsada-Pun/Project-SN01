<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Development</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * {
            font-family: 'Kanit', sans-serif;
            font-style: normal;
            font-weight: 400; /* Apply the specified font weight, changed from 100 to 400 to match the previous response for consistency with Kanit weights in the link */
        }

        body {
            background-image: url('../dist/img/cover.png'); /* ตรวจสอบที่อยู่ของรูปภาพพื้นหลังของคุณ */
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-position: center center;
            margin: 0;
            height: 100vh; /* กำหนดความสูงของ body ให้เต็ม viewport */
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column; /* เปลี่ยนเป็น column เพื่อให้เนื้อหาหลักอยู่ตรงกลางและ footer อยู่ด้านล่าง */
        }

        /* เพิ่ม style สำหรับ backdrop-filter หาก Tailwind ไม่รองรับโดยตรง หรือต้องการควบคุมเพิ่มเติม */
        .backdrop-filter-blur {
            backdrop-filter: blur(8px); /* ค่า blur ที่คุณต้องการ */
            -webkit-backdrop-filter: blur(8px); /* สำหรับ Safari */
        }
    </style>
</head>
<body class="flex flex-col h-screen justify-between items-center">
    <div class="flex-grow flex justify-center items-center">
        <div class="
            bg-white bg-opacity-80 
            p-8 md:p-12 
            rounded-2xl 
            shadow-2xl 
            text-center 
            backdrop-filter-blur 
            transform 
            transition-all duration-300 
            hover:scale-105
            border border-white /* เพิ่ม class สำหรับกรอบสีขาว */
        ">
            <h1 class="text-gray-800 text-5xl md:text-7xl font-bold mb-4">
                กำลังพัฒนา...
            </h1>
            
        </div>
    </div>

    
</body>
</html>
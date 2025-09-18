<?php
// No need for session_start() here if it's already in header.php.
// If not, and you need sessions for full_name/RoleName, ensure it's at the very top of your main script.

$activePage = basename($_SERVER['PHP_SELF']);
?>

<style>
/* Sidebar Background Color */
.main-sidebar.sidebar-light-primary {
    background:rgb(252, 252, 252) !important;
    color: black; /* Default text color for contrast */
}

/* Brand Link / Logo Section Styling */
.brand-link {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    padding: 20px 15px;
    /* Adjusted padding */
    border-bottom: 1px solid rgba(0, 0, 0, 0.1); /* Thin black line below logo */
    color: black !important; /* Ensure brand text is black */
    line-height: 1.2;
}

.brand-link .brand-image-sut {
    max-height: 45px;
    width: auto;
    margin-right: 10px;
    object-fit: contain;
}

.brand-text-sut {
    font-size: 1.1rem;
    font-weight: 500;
    color: black; /* Ensure brand text is black */
}

.brand-text-sut small {
    display: block;
    font-size: 0.8rem;
    font-weight: 300;
    color: rgba(0, 0, 0, 0.7); /* Slightly lighter for secondary text */
}

/* Remove User Panel Space */
.sidebar .user-panel {
    display: none !important;
}

/* General Nav Link Styling */
.main-sidebar .nav-link {
    color: rgba(0, 0, 0, 0.8) !important; /* Lighter black for default links */
    font-size: 1rem;
    padding: 12px 15px;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.main-sidebar .nav-icon {
    color: rgba(0, 0, 0, 0.8) !important; /* Icon color matches link color */
    font-size: 1rem;
    margin-right: 10px;
    width: 24px;
    text-align: center;
}

/* Hover State */
.main-sidebar .nav-link:hover {
    background-color: rgba(0, 0, 0, 0.05); /* Very light gray on hover */
    color: rgba(0, 0, 0, 0.9) !important; /* Slightly darker black on hover */
}

.main-sidebar .nav-link:hover .nav-icon {
    color: rgba(0, 0, 0, 0.9) !important; /* Slightly darker black on hover */
}

/* Active State (Solid dark background like in image) */
.nav-link.active {
    background:rgb(21, 88, 189) !important; /* Blue for the active state */
    color: white !important; /* White text for active state */
}

.nav-link.active .nav-icon {
    color: white !important; /* White icon for active state */
}

/* Remove any unwanted borders/lines from previous configs */
.user-panel,
.main-sidebar .nav-pills.nav-sidebar,
.sidebar-toggler-item,
hr.sidebar-divider {
    border: none !important;
    margin: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
}

.sidebar>nav.mt-2 {
    margin-top: 0 !important;
}

/* Ensure treeview icons are not affected by color changes for main items */
.nav-sidebar .nav-item>.nav-link i.right {
    color: inherit;
}

.nav-sidebar .nav-item>.nav-link.active i.right {
    color: white !important;
}

/* Specific CSS for the thin line below the logo/header */
.brand-link::after {
    content: "";
    display: block;
    width: calc(100% - 30px);
    height: 1px;
    background-color: rgba(0, 0, 0, 0.1); /* Thin black line */
    position: absolute;
    bottom: 0;
    left: 15px;
}

/* Ensure the brand-link is positioned relative for the ::after pseudo-element */
.brand-link {
    position: relative;
}
</style>

<aside class="main-sidebar sidebar-light-primary elevation-4">

    <a href="main.php" class="brand-link">
        <img src="dist/img/logo.png" alt="" class="brand-image-sut">
        <?php /* <span class="brand-text-sut font-weight-light">KITCHAMONGOL<small>ระบบการจัดการหลังบ้าน</small></span> */ ?>
    </a>
    <br>
    <div class="sidebar">

        <div class="user-panel mt-3 pb-3 mb-3 d-flex justify-content-center">
            <div class="user-show-pc text-center">
            </div>
        </div>

        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <li class="nav-item">
                    <a href="main.php" class="nav-link <?= $activePage == 'main.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-home"></i>
                        <p>หน้าหลัก</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="manage_users.php"
                        class="nav-link <?= $activePage == 'manage_users.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>จัดการข้อมูลผู้ใช้งาน</p>
                    </a>
                </li>

                <!-- <li class="nav-item">
                    <a href="manage_editpost.php"
                        class="nav-link <?= $activePage == 'manage_editpost.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>จัดการประกาศงาน</p>
                    </a>
                </li> -->

                <li class="nav-item">
                    <a href="how_to_use.php" class="nav-link <?= $activePage == 'how_to_use.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-book"></i>
                        <p>ทีมพัฒนาระบบ</p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside>
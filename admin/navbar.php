<?php
// It's recommended that session_start() is called in header.php or an initial config file.
// If it's not, and you need $_SESSION here, uncomment:
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<style>
/* Applying styles directly to the main header navigation */
.main-header.navbar { /* Target the main nav element with these classes */
    background: white; /* Set background to white */
    padding-left: 20px; /* Add left padding */
    padding-right: 20px; /* Add right padding */
    height: 60px; /* Set fixed height for the navbar */
    /* Ensure no unwanted shadows or borders if AdminLTE adds them by default */
    box-shadow: none !important; /* Remove default shadow */
    border-bottom: none !important; /* Remove default bottom border */
    display: flex; /* Use flexbox for alignment */
    align-items: center; /* Vertically center items */
    justify-content: space-between; /* Space out items */
}

/* Hide default AdminLTE user-panel-mini elements if not used */
/* This might not be strictly necessary if you remove the HTML, but good for cleanup */
.user-avatar-circle,
.user-name-text,
.user-panel-mini .fa-caret-down {
    display: none;
}


/* New styles for the buttons */
.header-buttons {
    display: flex;
    align-items: center;
    gap: 10px; /* Space between buttons */
    margin-left: auto; /* Push buttons to the right */
}

.user-button,
.logout-button {
    padding: 8px 15px;
    border-radius: 5px; /* Slightly rounded corners */
    font-family: 'Kanit', sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none; /* Remove underline for links */
    display: inline-flex; /* Use flex to align icon and text */
    align-items: center; /* Vertically center icon and text */
    justify-content: center;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}

.user-button {
    background-color: #ff8c00; /* Orange color */
    color: white;
    border: 1px solid #ff8c00;
}

.user-button:hover {
    background-color: #e67d00; /* Darker orange on hover */
    color: white; /* Ensure text remains white on hover */
}

.logout-button {
    background-color: white;
    color: #333; /* Dark text color */
    border: 1px solid #d9d9d9; /* Light gray border */
}

.logout-button:hover {
    background-color: #f0f0f0; /* Light gray on hover */
    color: #333; /* Ensure text remains dark on hover */
}

/* Optional: Adjust if you still want the pushmenu icon */
.navbar-nav .nav-item .nav-link {
    height: 100%;
    display: flex;
    align-items: center;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
}

/* If you need to keep the burger icon on the left */
.navbar-nav:first-child {
    margin-right: auto; /* Allow space for other elements */
}
.font_admin {
    font-family: 'Kanit', sans-serif;

}

</style>
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data    -widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
    </ul>

    <div class="header-buttons">
        <a href="#" class="user-button font_admin">
            ผู้ใช้งาน: <?= isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Guest'; ?>
        </a>
        <a href="logout.php" class="logout-button">
            ออกจากระบบ
        </a>
    </div>
</nav>

<script>
// Remove existing dropdown specific JS as it's no longer a dropdown
// No custom jQuery logic needed for these simple buttons unless you want specific animations.
// The existing code for the pushmenu (fas fa-bars) will still work if data-widget="pushmenu" is part of AdminLTE.
</script>
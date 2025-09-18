<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'conn.php';

if (isset($_SESSION['UserID']) && isset($_SESSION['LogID'])) {
    $UserID = $_SESSION['UserID'];
    $LogID = $_SESSION['LogID'];
    $LogoutTime = date('Y-m-d H:i:s');

    $sql = "UPDATE loginhistory SET LogoutTime = ? WHERE LogID = ? AND UserID = ?";
    $stmt = $condb->prepare($sql);
    $stmt->bind_param("sis", $LogoutTime, $LogID, $UserID);
    $stmt->execute();
    $stmt->close();
}

session_unset();
session_destroy();
?>

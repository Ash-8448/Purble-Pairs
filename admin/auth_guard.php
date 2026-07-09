<?php
/**
 * Admin Auth Guard
 * Include at the top of every admin page.
 * Redirects non-admins back to the main site.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['auth']) || empty($_SESSION['is_admin'])) {
    header('Location: ../index.php');
    exit;
}

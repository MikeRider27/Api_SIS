<?php
function auth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user'])) {
        header("Location: /");
        exit;
    }
    
    // Puedes añadir más validaciones aquí (roles, permisos, etc.)
    return true;
}
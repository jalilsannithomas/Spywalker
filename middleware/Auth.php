<?php

class Auth {
    public static function requireLogin() {
        session_start();
        require_once __DIR__ . '/../config/db.php';
        if (!isset($_SESSION['user_id'])) {
            header('Location: /Spywalker/login');
            exit();
        }
    }

    public static function requireRole($roles) {
        session_start();
        require_once __DIR__ . '/../config/db.php';
        if (!isset($_SESSION['role_id'])) {
            header('Location: /Spywalker/login');
            exit();
        }

        $database = new Database();
        $db = $database->getConnection();
        $roleModel = new Role($db);
        
        $userRole = $roleModel->getById($_SESSION['role_id']);
        
        if (!in_array($userRole['name'], (array)$roles)) {
            header('Location: /Spywalker/dashboard?error=unauthorized');
            exit();
        }
    }

    public static function isLoggedIn() {
        session_start();
        require_once __DIR__ . '/../config/db.php';
        return isset($_SESSION['user_id']);
    }

    public static function getUserRole() {
        if (!isset($_SESSION['role_id'])) {
            return null;
        }

        require_once __DIR__ . '/../config/db.php';

        $database = new Database();
        $db = $database->getConnection();
        $roleModel = new Role($db);
        
        $role = $roleModel->getById($_SESSION['role_id']);
        return $role ? $role['name'] : null;
    }

    public static function getCurrentUser() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        require_once __DIR__ . '/../config/db.php';

        $database = new Database();
        $db = $database->getConnection();
        $userModel = new User($db);
        
        return $userModel->getById($_SESSION['user_id']);
    }
}
?>

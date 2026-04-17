<?php

function check_access($page) {

    if (!isset($_SESSION['user_role'], $_SESSION['permissions'])) {
        exit('Unauthorized');
    }

    // Admin has full access
    if ($_SESSION['user_role'] === 'admin') {
        return true;
    }

    // Decode JSON permissions
    $permissions = json_decode($_SESSION['permissions'], true);

    // Safety check
    if (!is_array($permissions)) {
        exit('Permission format error');
    }

    if (in_array($page, $permissions, true)) {
        return true;
    }

    exit('Access Denied');
}


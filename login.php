<?php
session_start();
include 'config.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    die("Email and password required");
}

$sql = "
    SELECT users.id, users.username, users.email, users.password,
           roles.role_name, roles.permissions
    FROM users
    INNER JOIN roles ON users.role_id = roles.id
    WHERE users.email = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($user = mysqli_fetch_assoc($result)) {
    
    if ($password == $user['password']) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role_name'];
        $_SESSION['permissions'] = $user['permissions'];

        header("Location: /dashboard/dashboard.php");
        exit;

    } else {
        echo "Invalid email or password";
    }

} else {
    echo "Invalid email or password";
}
?>

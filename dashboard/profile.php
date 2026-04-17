<?php
include '../config.php';
include "login_check.php";

$user_id = $_SESSION['user_id'];


$sql = "
    SELECT 
        u.username,
        u.email,
        r.role_name,
        r.permissions
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

$permissions = json_decode($user['permissions'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rifat Enterprise | Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
<div class="container">

<?php include "sidebar.php"; ?>

<main class="main">

<div class="header">
    <h1>My Profile</h1>
    <div class="profile" onclick="window.location.href='/dashboard/profile.php'">
        <div class="avatar"></div>
        <span ><?php echo $_SESSION['username']; ?></span>
    </div>
</div>

<section class="stats-grid">

    <div class="stat-card">
        <div class="stat-top">
            <div class="stat-icon used">👤</div>
            <div>Username</div>
        </div>
        <div class="stat-number"><?php echo $user['username']; ?></div>
        <div class="stat-label">Account holder</div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <div class="stat-icon recent">📧</div>
            <div>Email</div>
        </div>
        <div class="stat-number" style="font-size:16px;">
            <?php echo $user['email']; ?>
        </div>
        <div class="stat-label">Registered email</div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <div class="stat-icon stats">🛡️</div>
            <div>Role</div>
        </div>
        <div class="stat-number">
            <?php echo ucfirst($user['role_name']); ?>
        </div>
        <div class="stat-label">Access level</div>
    </div>

</section>

<section class="recent-section">

<div class="section-header">
    <h3>Account Details</h3>
</div>

<div class="design-card" style="max-width:700px;">
    <table>
        <tr>
            <th style="text-align:left;">Username</th>
            <td><?php echo $user['username']; ?></td>
        </tr>
        <tr>
            <th style="text-align:left;">Email</th>
            <td><?php echo $user['email']; ?></td>
        </tr>
        <tr>
            <th style="text-align:left;">Role</th>
            <td><?php echo ucfirst($user['role_name']); ?></td>
        </tr>
    </table>
</div>

</section>

<section class="recent-section"style="margin-top:40px;">

<div class="section-header">
    <h3>Permissions</h3>
</div>

<div class="design-card">
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
        <?php if (!empty($permissions)): ?>
            <?php foreach ($permissions as $perm): ?>
                <div class="stat-card" style="padding:16px;">
                    ✅ <?php echo ucfirst($perm); ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No permissions assigned</p>
        <?php endif; ?>
    </div>
</div>

</section>

</main>
</div>
</body>
</html>

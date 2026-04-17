<?php
include '../config.php';
include "login_check.php";
$page = 'settings';
include "access.php";
check_access($page);

$current_user_id = (int)$_SESSION['user_id'];
$success_msg     = '';
$error_msg       = '';

$all_pages = ['dashboard', 'products', 'stock', 'sell', 'suppliers', 'reports', 'settings'];

if (isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $current_pw = trim($_POST['current_password']);
    $new_pw     = trim($_POST['new_password']);
    $confirm_pw = trim($_POST['confirm_password']);

    $user_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM users WHERE id = $current_user_id"));

    if ($user_row['password'] != $current_pw) {
        $error_msg = 'Current password is incorrect.';
    } else if (strlen($new_pw) < 4) {
        $error_msg = 'New password must be at least 4 characters.';
    } else if ($new_pw != $confirm_pw) {
        $error_msg = 'New passwords do not match.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $new_pw, $current_user_id);
        $stmt->execute();
        $success_msg = 'Password updated successfully.';
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role_id  = (int)$_POST['role_id'];

    $exists = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE username = '" . mysqli_real_escape_string($conn, $username) . "'"))[0];

    if ($exists) {
        $error_msg = "Username '$username' already exists.";
    } else if (strlen($username) < 3) {
        $error_msg = 'Username must be at least 3 characters.';
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role_id) VALUES (?,?,?,?)");
        $stmt->bind_param("sssi", $username, $email, $password, $role_id);
        $stmt->execute();
        $success_msg = "User '$username' created successfully.";
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'update_user') {
    $uid      = (int)$_POST['user_id'];
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $role_id  = (int)$_POST['role_id'];
    $new_pw   = trim($_POST['new_password']);

    $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role_id=? WHERE id=?");
    $stmt->bind_param("ssii", $username, $email, $role_id, $uid);
    $stmt->execute();

    if ($new_pw != '') {
        $stmt2 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt2->bind_param("si", $new_pw, $uid);
        $stmt2->execute();
    }

    $success_msg = "User '$username' updated.";
}

if (isset($_GET['delete_user'])) {
    $uid = (int)$_GET['delete_user'];
    if ($uid == $current_user_id) {
        $error_msg = 'You cannot delete your own account.';
    } else {
        $del = $conn->prepare("DELETE FROM users WHERE id=?");
        $del->bind_param("i", $uid);
        $del->execute();
        $success_msg = 'User deleted.';
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'add_role') {
    $role_name   = trim($_POST['role_name']);
    $role_type   = trim($_POST['role_type']);
    $permissions = isset($_POST['permissions']) ? json_encode($_POST['permissions']) : '[]';

    $stmt = $conn->prepare("INSERT INTO roles (role_name, roles, permissions) VALUES (?,?,?)");
    $stmt->bind_param("sss", $role_name, $role_type, $permissions);
    $stmt->execute();
    $success_msg = "Role '$role_name' created.";
}

if (isset($_POST['action']) && $_POST['action'] == 'update_role') {
    $role_id     = (int)$_POST['role_id'];
    $role_name   = trim($_POST['role_name']);
    $role_type   = trim($_POST['role_type']);
    $permissions = isset($_POST['permissions']) ? json_encode($_POST['permissions']) : '[]';

    $stmt = $conn->prepare("UPDATE roles SET role_name=?, roles=?, permissions=? WHERE id=?");
    $stmt->bind_param("sssi", $role_name, $role_type, $permissions, $role_id);
    $stmt->execute();
    $success_msg = "Role '$role_name' updated.";
}

if (isset($_GET['delete_role'])) {
    $rid  = (int)$_GET['delete_role'];
    $used = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role_id=$rid"))[0];
    if ($used > 0) {
        $error_msg = 'Cannot delete role — ' . $used . ' user(s) are assigned to it.';
    } else {
        $del = $conn->prepare("DELETE FROM roles WHERE id=?");
        $del->bind_param("i", $rid);
        $del->execute();
        $success_msg = 'Role deleted.';
    }
}

$users = mysqli_fetch_all(mysqli_query($conn, "SELECT u.id, u.username, u.email, u.role_id, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.id ASC"), MYSQLI_ASSOC);

$roles = mysqli_fetch_all(mysqli_query($conn, "SELECT r.*, COUNT(u.id) AS user_count FROM roles r LEFT JOIN users u ON u.role_id = r.id GROUP BY r.id ORDER BY r.id ASC"), MYSQLI_ASSOC);

$current_user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id=r.id WHERE u.id = $current_user_id"));

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rifat Enterprise | Settings</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .settings-tabs { display:flex; gap:4px; background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:6px; margin-bottom:28px; flex-wrap:wrap; }
        .tab-btn { padding:9px 20px; border-radius:10px; border:none; background:transparent; color:var(--text-secondary); font-family:'DM Sans',sans-serif; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; transition:all var(--transition-smooth); white-space:nowrap; }
        .tab-btn:hover  { background:var(--bg-hover); color:var(--text-primary); }
        .tab-btn.active { background:linear-gradient(135deg,var(--accent-1),#4f46e5); color:#fff; box-shadow:0 4px 14px rgba(108,99,255,0.35); }

        .settings-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
        .settings-full { grid-column:span 2; }

        .s-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:28px; box-shadow:var(--shadow-card); }
        .s-card h3 { font-family:'Syne',sans-serif; font-size:15px; font-weight:700; color:var(--text-primary); margin-bottom:20px; display:flex; align-items:center; gap:8px; }
        .s-card h4 { font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; color:var(--accent-2); margin:20px 0 14px; }

        .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        .form-group label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; color:var(--text-muted); margin-bottom:0; }
        .form-row { display:flex; gap:10px; margin-top:20px; }

        .s-table { width:100%; border-collapse:collapse; font-size:13px; }
        .s-table th { padding:10px 12px; border-bottom:1px solid var(--border); text-align:left; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; color:var(--text-muted); }
        .s-table td { padding:12px 12px; border-bottom:1px solid rgba(255,255,255,.04); color:var(--text-secondary); }
        .s-table tr:hover td { color:var(--text-primary); background:var(--bg-hover); }
        .s-table tr:last-child td { border-bottom:none; }

        .badge { display:inline-block; padding:3px 10px; border-radius:100px; font-size:11px; font-weight:600; }
        .badge-purple { background:rgba(108,99,255,.15); color:#a78bfa; }
        .badge-green  { background:rgba(52,211,153,.12);  color:#34d399; }
        .badge-blue   { background:rgba(96,165,250,.12);  color:#60a5fa; }
        .badge-you    { background:rgba(244,114,182,.12); color:#f472b6; }

        .action-group { display:flex; gap:6px; flex-wrap:wrap; }
        .btn-sm { padding:5px 12px; border-radius:7px; font-size:11px; font-weight:600; border:none; cursor:pointer; text-decoration:none; display:inline-block; white-space:nowrap; color:#fff; font-family:'DM Sans',sans-serif; }
        .btn-edit   { background:linear-gradient(135deg,#f59e0b,#d97706); }
        .btn-delete { background:linear-gradient(135deg,#ef4444,#dc2626); }
        .btn-save   { background:linear-gradient(135deg,var(--accent-1),#4f46e5); }
        .btn-cancel { background:var(--bg-elevated); color:var(--text-secondary); border:1px solid var(--border); }

        .perm-grid { display:flex; flex-wrap:wrap; gap:8px; }
        .perm-check { display:flex; align-items:center; gap:7px; padding:7px 14px; background:var(--bg-overlay); border:1px solid var(--border); border-radius:100px; cursor:pointer; font-size:12px; font-weight:600; color:var(--text-secondary); transition:all var(--transition-smooth); user-select:none; }
        .perm-check:hover { background:var(--bg-hover); color:var(--text-primary); }
        .perm-check input { display:none; }
        .perm-check.checked { background:rgba(108,99,255,.2); border-color:var(--accent-1); color:var(--accent-2); }

        .ov-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
        .ov-card { background:var(--bg-elevated); border:1px solid var(--border); border-radius:var(--radius-md); padding:20px; text-align:center; }
        .ov-num  { font-family:'Syne',sans-serif; font-size:34px; font-weight:700; color:var(--text-primary); }
        .ov-lbl  { font-size:12px; color:var(--text-muted); margin-top:4px; }

        .profile-big { display:flex; align-items:center; gap:20px; padding:20px; background:var(--bg-elevated); border-radius:var(--radius-md); border:1px solid var(--border); margin-bottom:24px; }
        .avatar-big  { width:64px; height:64px; border-radius:50%; background:linear-gradient(135deg,var(--accent-1),var(--accent-warm)); flex-shrink:0; }
        .profile-info h4 { font-family:'Syne',sans-serif; font-size:18px; font-weight:700; color:var(--text-primary); }
        .profile-info p  { font-size:13px; color:var(--text-muted); margin-top:3px; }

        .s-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.65); backdrop-filter:blur(8px); z-index:1000; align-items:center; justify-content:center; }
        .s-modal.open { display:flex; }
        .s-modal-box { background:var(--bg-elevated); border:1px solid var(--border-strong); border-radius:var(--radius-xl); padding:32px; width:90%; max-width:560px; position:relative; max-height:90vh; overflow-y:auto; box-shadow:var(--shadow-hover); }
        .s-modal-box::-webkit-scrollbar { display:none; }
        .s-modal-close { position:absolute; top:16px; right:18px; background:transparent; border:none; color:var(--text-muted); font-size:20px; cursor:pointer; }
        .s-modal-close:hover { color:var(--text-primary); }
        .s-modal-box h3 { font-family:'Syne',sans-serif; font-size:17px; font-weight:700; color:var(--text-primary); margin-bottom:22px; }

        .divider { border:none; border-top:1px solid var(--border); margin:22px 0; }

        @media(max-width:900px) {
            .settings-grid { grid-template-columns:1fr; }
            .settings-full { grid-column:span 1; }
            .form-grid-2, .form-grid-3 { grid-template-columns:1fr; }
            .ov-grid { grid-template-columns:1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="container">

    <?php include "sidebar.php"; ?>

    <main class="main">

        <div class="header">
            <h1>Settings</h1>
            <div class="profile" onclick="window.location.href='/dashboard/profile.php'" style="cursor:pointer;">
                <div class="avatar"></div>
                <span><?php echo $_SESSION["username"]; ?></span>
            </div>
        </div>

        <?php
        if ($success_msg != '') {
            echo '<div style="background:rgba(52,211,153,.12); color:#34d399; padding:12px 20px; border-radius:10px; margin-bottom:20px; border:1px solid rgba(52,211,153,.2);">✅ ' . htmlspecialchars($success_msg) . '</div>';
        }
        if ($error_msg != '') {
            echo '<div style="background:rgba(248,113,113,.12); color:#f87171; padding:12px 20px; border-radius:10px; margin-bottom:20px; border:1px solid rgba(248,113,113,.2);">⚠️ ' . htmlspecialchars($error_msg) . '</div>';
        }
        ?>

        <div class="settings-tabs">
            <?php
            $tabs = [
                'overview' => '🏠 Overview',
                'users'    => '👤 Manage Users',
                'roles'    => '🛡 Roles & Permissions',
                'password' => '🔑 Change Password',
            ];
            foreach ($tabs as $key => $label) {
                $is_active = ($tab == $key) ? 'active' : '';
                echo '<a href="?tab=' . $key . '" class="tab-btn ' . $is_active . '">' . $label . '</a>';
            }
            ?>
        </div>

        <?php if ($tab == 'overview') { ?>

        <div class="s-card" style="margin-bottom:24px;">
            <h3>👤 Your Account</h3>
            <div class="profile-big">
                <div class="avatar-big"></div>
                <div class="profile-info">
                    <h4><?php echo $current_user['username']; ?></h4>
                    <p><?php echo $current_user['email']; ?></p>
                    <p style="margin-top:6px;"><span class="badge badge-purple"><?php echo $current_user['role_name']; ?></span></p>
                </div>
            </div>
            <div class="ov-grid">
                <div class="ov-card">
                    <div class="ov-num"><?php echo count($users); ?></div>
                    <div class="ov-lbl">Total Users</div>
                </div>
                <div class="ov-card">
                    <div class="ov-num"><?php echo count($roles); ?></div>
                    <div class="ov-lbl">Roles Defined</div>
                </div>
                <div class="ov-card">
                    <div class="ov-num"><?php echo count($all_pages); ?></div>
                    <div class="ov-lbl">System Pages</div>
                </div>
            </div>
        </div>

        <div class="settings-grid">
            <div class="s-card" style="cursor:pointer;" onclick="window.location.href='?tab=users'">
                <h3>👤 Manage Users</h3>
                <p style="color:var(--text-muted); font-size:13px; line-height:1.6;">Add, edit, or remove system users. Assign roles to control access. Currently <strong style="color:var(--text-primary);"><?php echo count($users); ?> user(s)</strong> registered.</p>
                <div style="margin-top:16px;" class="design-item">➡ Go to Users</div>
            </div>
            <div class="s-card" style="cursor:pointer;" onclick="window.location.href='?tab=roles'">
                <h3>🛡 Roles & Permissions</h3>
                <p style="color:var(--text-muted); font-size:13px; line-height:1.6;">Create roles and fine-tune which pages each role can access. Currently <strong style="color:var(--text-primary);"><?php echo count($roles); ?> role(s)</strong> defined.</p>
                <div style="margin-top:16px;" class="design-item">➡ Go to Roles</div>
            </div>
            <div class="s-card" style="cursor:pointer;" onclick="window.location.href='?tab=password'">
                <h3>🔑 Change Password</h3>
                <p style="color:var(--text-muted); font-size:13px; line-height:1.6;">Update your own login password. We recommend using a strong, unique password.</p>
                <div style="margin-top:16px;" class="design-item">➡ Change Password</div>
            </div>
        </div>

        <?php } else if ($tab == 'users') { ?>

        <div class="s-card settings-full" style="margin-bottom:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                <h3 style="margin-bottom:0;">👤 All Users</h3>
                <button class="upload-btn" onclick="openModal('addUserModal')">➕ Add User</button>
            </div>
            <table class="s-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    for ($i = 0; $i < count($users); $i++) {
                        $u = $users[$i];
                    ?>
                    <tr>
                        <td style="color:var(--text-muted);"><?php echo $i + 1; ?></td>
                        <td style="font-weight:600; color:var(--text-primary);">
                            <?php echo $u['username']; ?>
                            <?php if ($u['id'] == $current_user_id) { echo '<span class="badge badge-you" style="margin-left:6px;">You</span>'; } ?>
                        </td>
                        <td><?php echo $u['email']; ?></td>
                        <td><span class="badge badge-purple"><?php echo isset($u['role_name']) ? $u['role_name'] : '—'; ?></span></td>
                        <td>
                            <div class="action-group">
                                <button class="btn-sm btn-edit" onclick="openEditUser(<?php echo json_encode($u); ?>)">✏️ Edit</button>
                                <?php if ($u['id'] != $current_user_id) { ?>
                                <a href="?tab=users&delete_user=<?php echo (int)$u['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete user <?php echo addslashes($u['username']); ?>?')">🗑️ Delete</a>
                                <?php } ?>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="s-modal" id="addUserModal">
            <div class="s-modal-box">
                <button class="s-modal-close" onclick="closeModal('addUserModal')">✕</button>
                <h3>➕ Add New User</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Username</label>
                            <input class="input" name="username" placeholder="Enter username" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input class="input" type="email" name="email" placeholder="Enter email">
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input class="input" type="password" name="password" placeholder="Set password" required>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select class="input" name="role_id" required>
                                <?php
                                for ($i = 0; $i < count($roles); $i++) {
                                    $r = $roles[$i];
                                    echo '<option value="' . $r['id'] . '">' . $r['role_name'] . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <button type="submit" class="upload-btn">💾 Create User</button>
                        <button type="button" class="btn-sm btn-cancel" style="padding:9px 18px;" onclick="closeModal('addUserModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="s-modal" id="editUserModal">
            <div class="s-modal-box">
                <button class="s-modal-close" onclick="closeModal('editUserModal')">✕</button>
                <h3>✏️ Edit User</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="eu_id">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Username</label>
                            <input class="input" name="username" id="eu_username" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input class="input" type="email" name="email" id="eu_email">
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select class="input" name="role_id" id="eu_role">
                                <?php
                                for ($i = 0; $i < count($roles); $i++) {
                                    $r = $roles[$i];
                                    echo '<option value="' . $r['id'] . '">' . $r['role_name'] . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>New Password <span style="color:var(--text-muted); font-size:10px;">(leave blank to keep)</span></label>
                            <input class="input" type="password" name="new_password" placeholder="New password">
                        </div>
                    </div>
                    <div class="form-row">
                        <button type="submit" class="upload-btn">💾 Save Changes</button>
                        <button type="button" class="btn-sm btn-cancel" style="padding:9px 18px;" onclick="closeModal('editUserModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <?php } else if ($tab == 'roles') { ?>

        <div class="s-card settings-full" style="margin-bottom:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                <h3 style="margin-bottom:0;">🛡 Roles</h3>
                <button class="upload-btn" onclick="openModal('addRoleModal')">➕ Add Role</button>
            </div>
            <table class="s-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Role Name</th>
                        <th>Type</th>
                        <th>Users</th>
                        <th>Permissions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    for ($i = 0; $i < count($roles); $i++) {
                        $r     = $roles[$i];
                        $perms = json_decode($r['permissions'], true);
                        if (!$perms) $perms = [];
                    ?>
                    <tr>
                        <td style="color:var(--text-muted);"><?php echo $i + 1; ?></td>
                        <td style="font-weight:600; color:var(--text-primary);"><?php echo $r['role_name']; ?></td>
                        <td><span class="badge badge-blue"><?php echo $r['roles']; ?></span></td>
                        <td><span class="badge badge-green"><?php echo $r['user_count']; ?> user(s)</span></td>
                        <td>
                            <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                <?php
                                if (count($perms) == 0) {
                                    echo '<span style="color:var(--text-muted); font-size:12px;">None</span>';
                                } else {
                                    for ($j = 0; $j < count($perms); $j++) {
                                        echo '<span class="badge badge-purple">' . $perms[$j] . '</span>';
                                    }
                                }
                                ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-group">
                                <button class="btn-sm btn-edit" onclick="openEditRole(<?php echo json_encode($r); ?>)">✏️ Edit</button>
                                <?php if ($r['user_count'] == 0) { ?>
                                <a href="?tab=roles&delete_role=<?php echo (int)$r['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete role <?php echo addslashes($r['role_name']); ?>?')">🗑️ Delete</a>
                                <?php } ?>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="s-modal" id="addRoleModal">
            <div class="s-modal-box">
                <button class="s-modal-close" onclick="closeModal('addRoleModal')">✕</button>
                <h3>➕ Add New Role</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_role">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Role Name</label>
                            <input class="input" name="role_name" placeholder="e.g. Sales Manager" required>
                        </div>
                        <div class="form-group">
                            <label>Role Type</label>
                            <input class="input" name="role_type" placeholder="e.g. sales">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:16px;">
                        <label>Page Permissions</label>
                        <div class="perm-grid" style="margin-top:8px;">
                            <?php
                            for ($i = 0; $i < count($all_pages); $i++) {
                                $pg = $all_pages[$i];
                                echo '<label class="perm-check" onclick="togglePerm(this)">';
                                echo '<input type="checkbox" name="permissions[]" value="' . $pg . '">';
                                echo ucfirst($pg);
                                echo '</label>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <button type="submit" class="upload-btn">💾 Create Role</button>
                        <button type="button" class="btn-sm btn-cancel" style="padding:9px 18px;" onclick="closeModal('addRoleModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="s-modal" id="editRoleModal">
            <div class="s-modal-box">
                <button class="s-modal-close" onclick="closeModal('editRoleModal')">✕</button>
                <h3>✏️ Edit Role Permissions</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="role_id" id="er_id">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Role Name</label>
                            <input class="input" name="role_name" id="er_name" required>
                        </div>
                        <div class="form-group">
                            <label>Role Type</label>
                            <input class="input" name="role_type" id="er_type">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:16px;">
                        <label>Page Permissions</label>
                        <div class="perm-grid" style="margin-top:8px;" id="er_perms">
                            <?php
                            for ($i = 0; $i < count($all_pages); $i++) {
                                $pg = $all_pages[$i];
                                echo '<label class="perm-check" onclick="togglePerm(this)" id="er_perm_' . $pg . '">';
                                echo '<input type="checkbox" name="permissions[]" value="' . $pg . '">';
                                echo ucfirst($pg);
                                echo '</label>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <button type="submit" class="upload-btn">💾 Save Permissions</button>
                        <button type="button" class="btn-sm btn-cancel" style="padding:9px 18px;" onclick="closeModal('editRoleModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <?php } else if ($tab == 'password') { ?>

        <div class="settings-grid">
            <div class="s-card">
                <h3>🔑 Change Your Password</h3>
                <div class="profile-big">
                    <div class="avatar-big"></div>
                    <div class="profile-info">
                        <h4><?php echo $current_user['username']; ?></h4>
                        <p><?php echo $current_user['email']; ?></p>
                        <p style="margin-top:6px;"><span class="badge badge-purple"><?php echo $current_user['role_name']; ?></span></p>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input class="input" type="password" name="current_password" placeholder="Enter current password" required>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input class="input" type="password" name="new_password" id="new_pw" placeholder="Enter new password" required>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input class="input" type="password" name="confirm_password" id="confirm_pw" placeholder="Re-enter new password" required>
                        </div>
                        <div id="pw_match_msg" style="font-size:12px; display:none;"></div>
                    </div>
                    <div class="form-row">
                        <button type="submit" class="upload-btn">🔒 Update Password</button>
                    </div>
                </form>
            </div>

            <div class="s-card">
                <h3>💡 Password Tips</h3>
                <div style="display:flex; flex-direction:column; gap:10px; margin-top:4px;">
                    <?php
                    $tips = [
                        ['✅', 'Use at least 8 characters'],
                        ['✅', 'Mix uppercase and lowercase letters'],
                        ['✅', 'Include numbers and symbols'],
                        ['✅', 'Avoid using your name or "password"'],
                        ['✅', 'Use a unique password for this system'],
                        ['⚠️', 'Do not share your password with anyone'],
                        ['⚠️', 'Change it regularly for better security'],
                    ];
                    for ($i = 0; $i < count($tips); $i++) {
                        $tip = $tips[$i];
                        echo '<div style="display:flex; align-items:center; gap:10px; padding:10px 14px; background:var(--bg-overlay); border-radius:8px; font-size:13px; color:var(--text-secondary);">';
                        echo '<span>' . $tip[0] . '</span>';
                        echo '<span>' . $tip[1] . '</span>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <?php } ?>

    </main>
</div>

<script src="js/script.js"></script>
<script>
    function openModal(id) {
        document.getElementById(id).classList.add('open');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    var modals = document.querySelectorAll('.s-modal');
    for (var i = 0; i < modals.length; i++) {
        modals[i].addEventListener('click', function(e) {
            if (e.target == this) {
                this.classList.remove('open');
            }
        });
    }

    function togglePerm(label) {
        setTimeout(function() {
            var cb = label.querySelector('input');
            if (cb.checked) {
                label.classList.add('checked');
            } else {
                label.classList.remove('checked');
            }
        }, 0);
    }

    function openEditUser(u) {
        document.getElementById('eu_id').value       = u.id;
        document.getElementById('eu_username').value = u.username;
        document.getElementById('eu_email').value    = u.email;
        document.getElementById('eu_role').value     = u.role_id;
        openModal('editUserModal');
    }

    function openEditRole(r) {
        document.getElementById('er_id').value   = r.id;
        document.getElementById('er_name').value = r.role_name;
        document.getElementById('er_type').value = r.roles;

        var perms = [];
        try {
            perms = JSON.parse(r.permissions || '[]');
        } catch(e) {
            perms = [];
        }

        <?php
        for ($i = 0; $i < count($all_pages); $i++) {
            $pg = $all_pages[$i];
            echo 'var el_' . $pg . ' = document.getElementById("er_perm_' . $pg . '");';
            echo 'if (el_' . $pg . ') {';
            echo '    var cb_' . $pg . ' = el_' . $pg . '.querySelector("input");';
            echo '    var has_' . $pg . ' = perms.indexOf("' . $pg . '") !== -1;';
            echo '    cb_' . $pg . '.checked = has_' . $pg . ';';
            echo '    if (has_' . $pg . ') { el_' . $pg . '.classList.add("checked"); } else { el_' . $pg . '.classList.remove("checked"); }';
            echo '}';
        }
        ?>

        openModal('editRoleModal');
    }

    var newPwField     = document.getElementById('new_pw');
    var confirmPwField = document.getElementById('confirm_pw');
    var matchMsg       = document.getElementById('pw_match_msg');

    if (newPwField && confirmPwField) {
        function checkPasswordMatch() {
            if (confirmPwField.value == '') {
                matchMsg.style.display = 'none';
                return;
            }
            matchMsg.style.display = 'block';
            if (newPwField.value == confirmPwField.value) {
                matchMsg.textContent = '✅ Passwords match';
                matchMsg.style.color = '#34d399';
            } else {
                matchMsg.textContent = '❌ Passwords do not match';
                matchMsg.style.color = '#f87171';
            }
        }
        newPwField.addEventListener('input', checkPasswordMatch);
        confirmPwField.addEventListener('input', checkPasswordMatch);
    }
</script>
</body>
</html>
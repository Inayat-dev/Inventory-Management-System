<?php
include '../config.php';
include "login_check.php";
$page = 'suppliers';
include "access.php";
check_access($page);

/* =====================
   ADD SUPPLIER
===================== */
if (isset($_POST['add_supplier'])) {
    $name     = trim($_POST['name']);
    $contact  = trim($_POST['contact']);
    $location = trim($_POST['location']);

    $stmt = $conn->prepare("INSERT INTO suppliers (name, phone, address) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $contact, $location);
    $stmt->execute();

    header("Location: suppliers.php?success=added");
    exit;
}

/* =====================
   UPDATE SUPPLIER
===================== */
if (isset($_POST['update_supplier'])) {
    $id       = (int)$_POST['supplier_id'];
    $name     = trim($_POST['name']);
    $contact  = trim($_POST['contact']);
    $location = trim($_POST['location']);

    $stmt = $conn->prepare("UPDATE suppliers SET name=?, phone=?, address=? WHERE id=?");
    $stmt->bind_param("sssi", $name, $contact, $location, $id);
    $stmt->execute();

    header("Location: suppliers.php?success=updated");
    exit;
}

/* =====================
   DELETE SUPPLIER
===================== */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Prevent delete if supplier has purchase records
    $check = $conn->prepare("SELECT COUNT(*) FROM material_perchases WHERE supplier_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $linked = $check->get_result()->fetch_row()[0];

    if ($linked > 0) {
        header("Location: suppliers.php?error=linked");
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM suppliers WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: suppliers.php?success=deleted");
    exit;
}

/* =====================
   EXPORT CSV
===================== */
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="suppliers_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Name', 'Contact', 'Location', 'Created At']);
    $rows = mysqli_query($conn, "SELECT id, name, phone, address, created_at FROM suppliers ORDER BY id DESC");
    while ($r = mysqli_fetch_row($rows)) fputcsv($out, $r);
    fclose($out);
    exit;
}

/* =====================
   SEARCH + PAGINATION
===================== */
$s_name = isset($_GET['s_name']) ? trim($_GET['s_name']) : '';

$where  = "WHERE 1=1";
$params = []; $types = '';
if ($s_name) { $like = "%$s_name%"; $where .= " AND (name LIKE ? OR phone LIKE ? OR address LIKE ?)"; $params = [$like,$like,$like]; $types = 'sss'; }

$per_page   = 15;
$page_num   = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
$offset     = ($page_num - 1) * $per_page;

$count_stmt = $conn->prepare("SELECT COUNT(*) FROM suppliers $where");
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows  = $count_stmt->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total_rows / $per_page));

$sup_stmt = $conn->prepare("SELECT id, name, phone, address FROM suppliers $where ORDER BY id DESC LIMIT ? OFFSET ?");
$fp = $params; $ft = $types . 'ii'; $fp[] = $per_page; $fp[] = $offset;
$sup_stmt->bind_param($ft, ...$fp);
$sup_stmt->execute();
$suppliers = $sup_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Total count (unfiltered) for stat card
$total_all = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM suppliers"))[0];

function paginate_url($p) {
    $params = $_GET; $params['page'] = $p;
    unset($params['delete'], $params['export_csv'], $params['success'], $params['error']);
    return 'suppliers.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rifat Enterprise | Suppliers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* ── Filter bar ── */
        .filter-bar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:18px; align-items:flex-end; }
        .filter-bar .fi { display:flex; flex-direction:column; gap:4px; }
        .filter-bar label { font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; }
        .filter-bar input { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); color:#e2e8f0;
                            border-radius:8px; padding:7px 12px; font-size:13px; min-width:220px; }
        .filter-bar input:focus { outline:none; border-color:#7c3aed; }
        .filter-btn { padding:8px 18px; border-radius:8px; border:none; cursor:pointer; font-weight:600; font-size:13px; align-self:flex-end; }
        .btn-filter { background:linear-gradient(135deg,#7c3aed,#4f46e5); color:#fff; }
        .btn-reset  { background:rgba(255,255,255,.08); color:#e2e8f0; text-decoration:none; text-align:center; }

        /* ── Pagination ── */
        .pagination { display:flex; gap:6px; justify-content:center; margin-top:20px; flex-wrap:wrap; }
        .pagination a, .pagination span { padding:6px 14px; border-radius:8px; font-size:13px; font-weight:600;
                                          text-decoration:none; border:1px solid rgba(255,255,255,.1); color:#e2e8f0; }
        .pagination a:hover      { background:rgba(124,58,237,.2); }
        .pagination span.current { background:linear-gradient(135deg,#7c3aed,#4f46e5); border-color:transparent; }

        /* ── Action buttons ── */
        .action-group { display:flex; gap:6px; }
        .btn-sm { padding:5px 11px; border-radius:7px; font-size:12px; font-weight:600; border:none;
                  cursor:pointer; text-decoration:none; display:inline-block; white-space:nowrap; color:#fff; }
        .btn-edit   { background:linear-gradient(135deg,#f59e0b,#d97706); }
        .btn-delete { background:linear-gradient(135deg,#ef4444,#dc2626); }

        /* ── Modal ── */
        #editModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.65);
                     z-index:1000; align-items:center; justify-content:center; }
        #editModal.active { display:flex; }
        .modal-box { background:#1a1a2e; border:1px solid rgba(255,255,255,.1); border-radius:16px;
                     padding:32px; width:90%; max-width:520px; position:relative; }
        .modal-close { position:absolute; top:16px; right:16px; background:transparent; border:none;
                       color:#94a3b8; font-size:18px; cursor:pointer; }
        .modal-close:hover { color:#e2e8f0; }
        .modal-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .modal-grid label { font-size:12px; color:#94a3b8; display:block; margin-bottom:4px; }
    </style>
</head>

<body>
<div class="container">

<?php include "sidebar.php"; ?>

<main class="main">

<!-- HEADER -->
<div class="header">
    <h1>Suppliers Management</h1>
    <div class="profile" onclick="window.location.href='/dashboard/profile.php'" style="cursor:pointer;">
        <div class="avatar"></div>
        <span><?php echo htmlspecialchars($_SESSION["username"]); ?></span>
    </div>
</div>

<!-- ALERTS -->
<?php if (isset($_GET['success'])): ?>
    <?php $msgs = ['added'=>'Supplier added successfully.','updated'=>'Supplier updated successfully.','deleted'=>'Supplier deleted.']; ?>
    <div style="background:rgba(16,185,129,.15); color:#34d399; padding:12px 20px; border-radius:8px; margin-bottom:16px;">
        ✅ <?php echo $msgs[$_GET['success']] ?? 'Action completed.'; ?>
    </div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <?php $errs = ['linked'=>'Cannot delete — this supplier has purchase records linked to them.']; ?>
    <div style="background:rgba(239,68,68,.15); color:#f87171; padding:12px 20px; border-radius:8px; margin-bottom:16px;">
        ⚠️ <?php echo $errs[$_GET['error']] ?? 'An error occurred.'; ?>
    </div>
<?php endif; ?>

<!-- STATS -->
<section class="stats-grid">
    <div class="stat-card">
        <div class="stat-top">
            <div class="stat-icon used">🏭</div>
            <div>Total Suppliers</div>
        </div>
        <div class="stat-number"><?php echo $total_all; ?></div>
        <div class="stat-label">Registered vendors</div>
    </div>
</section>

<!-- SUPPLIERS LIST -->
<section class="recent-section">

<div class="section-header">
    <h3>Supplier List
        <span style="font-size:13px;color:#94a3b8;font-weight:400;margin-left:8px;">
            (<?php echo $total_rows; ?><?php echo $s_name ? ' matching' : ''; ?> records)
        </span>
    </h3>
    <div style="display:flex; gap:10px;">
        <a href="suppliers.php?export_csv=1<?php echo $s_name ? '&s_name='.urlencode($s_name) : ''; ?>"
           class="upload-btn" style="background:linear-gradient(135deg,#10b981,#059669); text-decoration:none;">
            📥 Export CSV
        </a>
        <button class="upload-btn" onclick="toggleAddForm()">➕ Add Supplier</button>
    </div>
</div>

<!-- ADD FORM -->
<div id="addForm" style="display:none; margin-top:20px;">
    <div class="design-card">
        <h3 style="margin-bottom:16px;">Add Supplier</h3>
        <form method="post">
            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
                <div>
                    <label>Supplier Name</label>
                    <input class="input" name="name"   oninput="this.value=this.value.replace(/[^A-Za-z ]/g,'')"  placeholder="Enter name" required>
                </div>
                <div>
                    <label>Contact / Phone</label>
                    <input class="input" name="contact" placeholder="Enter phone">
                </div>
                <div>
                    <label>Location / Address</label>
                    <input class="input" name="location" placeholder="Enter address">
                </div>
            </div>
            <div style="margin-top:16px; display:flex; gap:10px;">
                <button class="upload-btn" name="add_supplier">💾 Save Supplier</button>
                <button type="button" class="upload-btn" style="background:rgba(255,255,255,.1); box-shadow:none;"
                        onclick="toggleAddForm()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- SEARCH BAR -->
<form method="GET" action="suppliers.php">
    <div class="filter-bar">
        <div class="fi">
            <label>Search</label>
            <input type="text" name="s_name" placeholder="Name, phone, or location…"
                   value="<?php echo htmlspecialchars($s_name); ?>">
        </div>
        <button type="submit" class="filter-btn btn-filter">🔍 Search</button>
        <a href="suppliers.php" class="filter-btn btn-reset">✖ Reset</a>
    </div>
</form>

<!-- TABLE -->
<table>
<thead>
<tr>
    <th>#</th>
    <th>Name</th>
    <th>Contact</th>
    <th>Location</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (empty($suppliers)): ?>
    <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:30px;">No suppliers found.</td></tr>
<?php else: ?>
    <?php $i = ($page_num - 1) * $per_page + 1; foreach ($suppliers as $s): ?>
    <tr>
        <td><?php echo $i++; ?></td>
        <td><?php echo htmlspecialchars($s['name']); ?></td>
        <td><?php echo htmlspecialchars($s['phone']); ?></td>
        <td><?php echo htmlspecialchars($s['address']); ?></td>
        <td>
            <div class="action-group">
                <button class="btn-sm btn-edit"
                    onclick="openEdit(<?php echo htmlspecialchars(json_encode($s)); ?>)">
                    ✏️ Edit
                </button>
                <a href="?delete=<?php echo (int)$s['id']; ?>"
                   class="btn-sm btn-delete"
                   onclick="return confirm('Delete this supplier?')">
                    🗑️ Delete
                </a>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>

<!-- PAGINATION -->
<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($page_num > 1): ?>
        <a href="<?php echo paginate_url(1); ?>">«</a>
        <a href="<?php echo paginate_url($page_num - 1); ?>">‹</a>
    <?php endif; ?>
    <?php for ($p = max(1, $page_num - 2); $p <= min($total_pages, $page_num + 2); $p++): ?>
        <?php if ($p == $page_num): ?>
            <span class="current"><?php echo $p; ?></span>
        <?php else: ?>
            <a href="<?php echo paginate_url($p); ?>"><?php echo $p; ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page_num < $total_pages): ?>
        <a href="<?php echo paginate_url($page_num + 1); ?>">›</a>
        <a href="<?php echo paginate_url($total_pages); ?>">»</a>
    <?php endif; ?>
</div>
<?php endif; ?>

</section>
</main>

<!-- ── EDIT MODAL ─────────────────────────────────────────────────────── -->
<div id="editModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeEdit()">✕</button>
        <h3 style="margin-bottom:20px;">✏️ Edit Supplier</h3>
        <form method="post">
            <input type="hidden" name="supplier_id" id="edit_id">
            <div class="modal-grid">
                <div style="grid-column:span 2;">
                    <label>Supplier Name</label>
                    <input class="input"  oninput="this.value=this.value.replace(/[^A-Za-z ]/g,'')" name="name" id="edit_name" style="width:100%;" required>
                </div>
                <div>
                    <label>Contact / Phone</label>
                    <input class="input" name="contact" id="edit_contact" style="width:100%;">
                </div>
                <div>
                    <label>Location / Address</label>
                    <input class="input" name="location" id="edit_location" style="width:100%;">
                </div>
            </div>
            <div style="margin-top:20px; display:flex; gap:10px;">
                <button class="upload-btn" name="update_supplier">💾 Update</button>
                <button type="button" class="upload-btn" style="background:rgba(255,255,255,.1); box-shadow:none;"
                        onclick="closeEdit()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="js/script.js"></script>
<script>
    function toggleAddForm() {
        const f = document.getElementById('addForm');
        f.style.display = (f.style.display === 'none' || f.style.display === '') ? 'block' : 'none';
    }

    function openEdit(s) {
        document.getElementById('edit_id').value       = s.id;
        document.getElementById('edit_name').value     = s.name;
        document.getElementById('edit_contact').value  = s.phone;
        document.getElementById('edit_location').value = s.address;
        document.getElementById('editModal').classList.add('active');
    }

    function closeEdit() {
        document.getElementById('editModal').classList.remove('active');
    }

    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEdit();
    });
</script>

</div>
</body>
</html>
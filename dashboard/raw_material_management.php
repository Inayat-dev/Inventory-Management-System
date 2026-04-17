<?php
include '../config.php';
include "login_check.php";
$page = 'raw_material_management';
include "access.php";
check_access($page);

$data_raw = [];
$rm_result = mysqli_query($conn, "SELECT * FROM raw_material ORDER BY id");
while ($rm = mysqli_fetch_assoc($rm_result)) {
    $data_raw[$rm['id']] = $rm;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {

    $material_id    = (int)$_POST['material_id'];
    $supplier_id    = (int)$_POST['supplier_id'];
    $purchase_date  = $_POST['purchase_date'];
    $quantity       = (int)$_POST['quantity'];
    $rate           = (float)$_POST['rate'];
    $total_amount   = (float)$_POST['total_amount'];
    $payment_status = (int)$_POST['payment_status'];

    $stmt = $conn->prepare("INSERT INTO material_perchases (material_id, supplier_id, purchase_date, quantity, rate, total_amount, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisiddi", $material_id, $supplier_id, $purchase_date, $quantity, $rate, $total_amount, $payment_status);

    if ($stmt->execute()) {
        $upd = $conn->prepare("UPDATE raw_material SET current_stock = current_stock + ? WHERE id = ?");
        $upd->bind_param("ii", $quantity, $material_id);
        $upd->execute();
        header("Location: raw_material_management.php?success=added");
        exit();
    } else {
        $error_msg = $stmt->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {

    $purchase_id    = (int)$_POST['purchase_id'];
    $material_id    = (int)$_POST['material_id'];
    $supplier_id    = (int)$_POST['supplier_id'];
    $purchase_date  = $_POST['purchase_date'];
    $quantity       = (int)$_POST['quantity'];
    $rate           = (float)$_POST['rate'];
    $total_amount   = (float)$_POST['total_amount'];
    $payment_status = (int)$_POST['payment_status'];

    $old_stmt = $conn->prepare("SELECT quantity, material_id FROM material_perchases WHERE id = ?");
    $old_stmt->bind_param("i", $purchase_id);
    $old_stmt->execute();
    $old = $old_stmt->get_result()->fetch_assoc();

    if ($old) {

        $old_quantity    = (int)$old['quantity'];
        $old_material_id = (int)$old['material_id'];

        $upd = $conn->prepare("UPDATE material_perchases SET material_id=?, supplier_id=?, purchase_date=?, quantity=?, rate=?, total_amount=?, payment_status=? WHERE id=?");
        $upd->bind_param("iisiddii", $material_id, $supplier_id, $purchase_date, $quantity, $rate, $total_amount, $payment_status, $purchase_id);

        if ($upd->execute()) {
            $r1 = $conn->prepare("UPDATE raw_material SET current_stock = current_stock - ? WHERE id = ?");
            $r1->bind_param("ii", $old_quantity, $old_material_id);
            $r1->execute();

            $r2 = $conn->prepare("UPDATE raw_material SET current_stock = current_stock + ? WHERE id = ?");
            $r2->bind_param("ii", $quantity, $material_id);
            $r2->execute();

            header("Location: raw_material_management.php?success=updated");
            exit();
        } else {
            $error_msg = $upd->error;
        }
    }
}

if (isset($_GET['delete_purchase'])) {

    $del_id = (int)$_GET['delete_purchase'];

    $old_stmt = $conn->prepare("SELECT quantity, material_id FROM material_perchases WHERE id = ?");
    $old_stmt->bind_param("i", $del_id);
    $old_stmt->execute();
    $old = $old_stmt->get_result()->fetch_assoc();

    if ($old) {
        $qty = (int)$old['quantity'];
        $mid = (int)$old['material_id'];

        $rev = $conn->prepare("UPDATE raw_material SET current_stock = current_stock - ? WHERE id = ?");
        $rev->bind_param("ii", $qty, $mid);
        $rev->execute();

        $del = $conn->prepare("DELETE FROM material_perchases WHERE id = ?");
        $del->bind_param("i", $del_id);
        $del->execute();
    }

    header("Location: raw_material_management.php?success=deleted");
    exit();
}

if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="purchases_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Material', 'Supplier', 'Purchase Date', 'Quantity', 'Rate (₹)', 'Total (₹)', 'Payment', 'Created At']);
    $rows = mysqli_query($conn, "SELECT mp.id, rm.material_name, s.name, mp.purchase_date, mp.quantity, mp.rate, mp.total_amount, IF(mp.payment_status=1,'Paid','Pending'), mp.created_at FROM material_perchases mp JOIN raw_material rm ON rm.id = mp.material_id JOIN suppliers s ON s.id = mp.supplier_id ORDER BY mp.id DESC");
    while ($r = mysqli_fetch_row($rows)) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit();
}

$s_material = isset($_GET['s_material']) ? (int)$_GET['s_material'] : 0;
$s_supplier = isset($_GET['s_supplier']) ? (int)$_GET['s_supplier'] : 0;
$s_payment  = (isset($_GET['s_payment']) && $_GET['s_payment'] !== '') ? (int)$_GET['s_payment'] : '';
$s_from     = isset($_GET['s_from']) ? $_GET['s_from'] : '';
$s_to       = isset($_GET['s_to'])   ? $_GET['s_to']   : '';

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if ($s_material) {
    $where    .= " AND mp.material_id = ?";
    $params[]  = $s_material;
    $types    .= 'i';
}
if ($s_supplier) {
    $where    .= " AND mp.supplier_id = ?";
    $params[]  = $s_supplier;
    $types    .= 'i';
}
if ($s_payment !== '') {
    $where    .= " AND mp.payment_status = ?";
    $params[]  = $s_payment;
    $types    .= 'i';
}
if ($s_from) {
    $where    .= " AND mp.purchase_date >= ?";
    $params[]  = $s_from;
    $types    .= 's';
}
if ($s_to) {
    $where    .= " AND mp.purchase_date <= ?";
    $params[]  = $s_to;
    $types    .= 's';
}

$per_page = 15;
$page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page_num < 1) $page_num = 1;
$offset = ($page_num - 1) * $per_page;

$count_stmt = $conn->prepare("SELECT COUNT(*) FROM material_perchases mp $where");
if (count($params) > 0) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows  = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_rows / $per_page);
if ($total_pages < 1) $total_pages = 1;

$purchase_sql = "SELECT mp.id, mp.material_id, mp.supplier_id, rm.material_name, s.name AS supplier_name, mp.purchase_date, mp.quantity, mp.rate, mp.total_amount, mp.payment_status, mp.created_at FROM material_perchases mp JOIN raw_material rm ON rm.id = mp.material_id JOIN suppliers s ON s.id = mp.supplier_id $where ORDER BY mp.id DESC LIMIT ? OFFSET ?";
$purchase_stmt = $conn->prepare($purchase_sql);
$fetch_params   = $params;
$fetch_types    = $types . 'ii';
$fetch_params[] = $per_page;
$fetch_params[] = $offset;
$purchase_stmt->bind_param($fetch_types, ...$fetch_params);
$purchase_stmt->execute();
$purchase_q = $purchase_stmt->get_result();

$suppliers_list = mysqli_fetch_all(mysqli_query($conn, "SELECT id, name FROM suppliers ORDER BY name"), MYSQLI_ASSOC);
$materials_list = mysqli_fetch_all(mysqli_query($conn, "SELECT id, material_name FROM raw_material ORDER BY material_name"), MYSQLI_ASSOC);

function paginate_url($p) {
    $params = $_GET;
    $params['page'] = $p;
    unset($params['delete_purchase'], $params['export_csv'], $params['success']);
    return 'raw_material_management.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rifat Enterprise | Raw Material Inventory</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .filter-bar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:18px; align-items:flex-end; }
        .filter-bar .fi { display:flex; flex-direction:column; gap:4px; }
        .filter-bar label { font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; }
        .filter-bar input,
        .filter-bar select { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); color:#e2e8f0; border-radius:8px; padding:7px 12px; font-size:13px; min-width:130px; }
        .filter-bar input:focus,
        .filter-bar select:focus { outline:none; border-color:#7c3aed; }
        .filter-btn { padding:8px 18px; border-radius:8px; border:none; cursor:pointer; font-weight:600; font-size:13px; align-self:flex-end; }
        .btn-filter { background:linear-gradient(135deg,#7c3aed,#4f46e5); color:#fff; }
        .btn-reset  { background:rgba(255,255,255,.08); color:#e2e8f0; text-decoration:none; text-align:center; }

        .pagination { display:flex; gap:6px; justify-content:center; margin-top:20px; flex-wrap:wrap; }
        .pagination a, .pagination span { padding:6px 14px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; border:1px solid rgba(255,255,255,.1); color:#e2e8f0; }
        .pagination a:hover      { background:rgba(124,58,237,.2); }
        .pagination span.current { background:linear-gradient(135deg,#7c3aed,#4f46e5); border-color:transparent; }

        .action-group { display:flex; gap:6px; flex-wrap:wrap; }
        .btn-sm { padding:5px 11px; border-radius:7px; font-size:11px; font-weight:600; border:none; cursor:pointer; text-decoration:none; display:inline-block; white-space:nowrap; color:#fff; }
        .btn-edit   { background:linear-gradient(135deg,#f59e0b,#d97706); }
        .btn-delete { background:linear-gradient(135deg,#ef4444,#dc2626); }

        .stock-bar-bg   { background:rgba(255,255,255,.08); border-radius:4px; height:6px; margin-top:6px; }
        .stock-bar-fill { height:6px; border-radius:4px; }

        #updateModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:1000; align-items:center; justify-content:center; }
        #updateModal.active { display:flex; }
        .modal-box { background:#1a1a2e; border:1px solid rgba(255,255,255,.1); border-radius:16px; padding:32px; width:90%; max-width:580px; position:relative; max-height:90vh; overflow-y:auto; }
        .modal-close { position:absolute; top:16px; right:16px; background:transparent; border:none; color:#94a3b8; font-size:18px; cursor:pointer; }
        .modal-close:hover { color:#e2e8f0; }
    </style>
</head>

<body>
<div class="container">

    <?php include "sidebar.php"; ?>

    <main class="main">

        <div class="header">
            <h1>Raw Material Inventory</h1>
            <div class="profile" onclick="window.location.href='/dashboard/profile.php'" style="cursor:pointer;">
                <div class="avatar"></div>
                <span><?php echo $_SESSION["username"]; ?></span>
            </div>
        </div>

        <?php
        if (isset($_GET['success'])) {
            $msgs = [
                'added'   => 'Purchase added successfully.',
                'updated' => 'Purchase updated successfully.',
                'deleted' => 'Purchase deleted and stock restored.'
            ];
            $msg = isset($msgs[$_GET['success']]) ? $msgs[$_GET['success']] : 'Action completed.';
            echo '<div style="background:rgba(16,185,129,.15); color:#34d399; padding:12px 20px; border-radius:8px; margin-bottom:16px;">✅ ' . $msg . '</div>';
        }
        if (isset($error_msg)) {
            echo '<div style="background:rgba(239,68,68,.15); color:#f87171; padding:12px 20px; border-radius:8px; margin-bottom:16px;">⚠️ Error: ' . htmlspecialchars($error_msg) . '</div>';
        }
        ?>

        <section class="stats-grid">
            <?php
            $icons = ['🧱', '🏖️', '🪨', '🏗️', '📦'];
            $units = ['Bags', 'Ton', 'Ton', 'Bags', 'Units'];
            $i = 0;
            foreach ($data_raw as $rm) {
                $low = $rm['current_stock'] < $rm['min_stock'];

                $pct = 0;
                if ($rm['min_stock'] > 0) {
                    $pct = round($rm['current_stock'] / $rm['min_stock'] * 100);
                    if ($pct > 100) $pct = 100;
                } else {
                    $pct = 100;
                }

                if ($pct >= 100) {
                    $bar_color = '#10b981';
                } else if ($pct >= 50) {
                    $bar_color = '#f59e0b';
                } else {
                    $bar_color = '#ef4444';
                }

                $icon = isset($icons[$i]) ? $icons[$i] : '📦';
                $unit = isset($units[$i]) ? $units[$i] : '';
                $icon_class  = $low ? 'stats' : 'used';
                $stock_color = $low ? '#f87171' : '#34d399';
                $stock_label = $low ? '⚠️ Low Stock' : '✅ Available';
            ?>
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon <?php echo $icon_class; ?>"><?php echo $icon; ?></div>
                    <div><?php echo $rm['material_name']; ?></div>
                </div>
                <div class="stat-number"><?php echo number_format($rm['current_stock']); ?> <small style="font-size:14px;"><?php echo $unit; ?></small></div>
                <div class="stat-label" style="color:<?php echo $stock_color; ?>"><?php echo $stock_label; ?></div>
                <div class="stock-bar-bg">
                    <div class="stock-bar-fill" style="width:<?php echo $pct; ?>%; background:<?php echo $bar_color; ?>;"></div>
                </div>
            </div>
            <?php
                $i++;
            }
            ?>
        </section>

        <section class="recent-section">

            <div class="section-header">
                <h3>Purchase Records <span style="font-size:13px; color:#94a3b8; font-weight:400; margin-left:8px;">(<?php echo $total_rows; ?> records)</span></h3>
                <div style="display:flex; gap:10px;">
                    <a href="raw_material_management.php?export_csv=1&<?php echo http_build_query(array_filter(['s_material' => $s_material, 's_supplier' => $s_supplier, 's_payment' => $s_payment, 's_from' => $s_from, 's_to' => $s_to])); ?>"
                       class="upload-btn" style="background:linear-gradient(135deg,#10b981,#059669); text-decoration:none;">
                        📥 Export CSV
                    </a>
                    <button class="upload-btn" onclick="toggleProductionForm()">➕ Add Purchase</button>
                </div>
            </div>

            <div id="Form" style="display:none; margin-top:24px;">
                <div class="design-card">
                    <h3 style="margin-bottom:16px;">Add Material Purchase</h3>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="add">
                        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">

                            <div>
                                <label>Material</label>
                                <select class="input" name="material_id" required>
                                    <option value="">Select Material</option>
                                    <?php
                                    for ($j = 0; $j < count($materials_list); $j++) {
                                        $m = $materials_list[$j];
                                        echo '<option value="' . (int)$m['id'] . '">' . $m['material_name'] . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div>
                                <label>Supplier</label>
                                <select class="input" name="supplier_id" required>
                                    <option value="">Select Supplier</option>
                                    <?php
                                    for ($j = 0; $j < count($suppliers_list); $j++) {
                                        $s = $suppliers_list[$j];
                                        echo '<option value="' . (int)$s['id'] . '">' . $s['name'] . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div>
                                <label>Purchase Date</label>
                                <input type="date" class="input" name="purchase_date" required>
                            </div>

                            <div>
                                <label>Quantity</label>
                                <input type="number" class="input" name="quantity" id="add_quantity" placeholder="0" min="1" required>
                            </div>

                            <div>
                                <label>Rate (per unit ₹)</label>
                                <input type="number" class="input" name="rate" id="add_rate" placeholder="0.00" min="0" step="0.01" required>
                            </div>

                            <div>
                                <label>Total Amount (₹)</label>
                                <input type="number" class="input" name="total_amount" id="add_total_amount" placeholder="Auto-calculated" min="0" step="0.01" readonly required>
                            </div>

                            <div>
                                <label>Payment Status</label>
                                <select class="input" name="payment_status" required>
                                    <option value="1">Paid</option>
                                    <option value="0">Pending</option>
                                </select>
                            </div>

                        </div>
                        <div style="margin-top:20px; display:flex; gap:12px;">
                            <button type="submit" class="upload-btn">💾 Save Purchase</button>
                            <button type="button" class="upload-btn" style="background:rgba(255,255,255,.1); box-shadow:none;" onclick="toggleProductionForm()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <form method="GET" action="raw_material_management.php">
                <div class="filter-bar">
                    <div class="fi">
                        <label>Material</label>
                        <select name="s_material">
                            <option value="">All Materials</option>
                            <?php
                            for ($j = 0; $j < count($materials_list); $j++) {
                                $m        = $materials_list[$j];
                                $selected = ($s_material == $m['id']) ? 'selected' : '';
                                echo '<option value="' . (int)$m['id'] . '" ' . $selected . '>' . $m['material_name'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="fi">
                        <label>Supplier</label>
                        <select name="s_supplier">
                            <option value="">All Suppliers</option>
                            <?php
                            for ($j = 0; $j < count($suppliers_list); $j++) {
                                $s        = $suppliers_list[$j];
                                $selected = ($s_supplier == $s['id']) ? 'selected' : '';
                                echo '<option value="' . (int)$s['id'] . '" ' . $selected . '>' . $s['name'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="fi">
                        <label>Payment</label>
                        <select name="s_payment">
                            <option value="">All</option>
                            <option value="1" <?php echo ($s_payment === 1) ? 'selected' : ''; ?>>Paid</option>
                            <option value="0" <?php echo ($s_payment === 0) ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="fi">
                        <label>From Date</label>
                        <input type="date" name="s_from" value="<?php echo $s_from; ?>">
                    </div>
                    <div class="fi">
                        <label>To Date</label>
                        <input type="date" name="s_to" value="<?php echo $s_to; ?>">
                    </div>
                    <button type="submit" class="filter-btn btn-filter">🔍 Filter</button>
                    <a href="raw_material_management.php" class="filter-btn btn-reset">✖ Reset</a>
                </div>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Material</th>
                        <th>Supplier</th>
                        <th>Purchase Date</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($purchase_q->num_rows > 0) {
                        $row_num = ($page_num - 1) * $per_page + 1;
                        while ($row = $purchase_q->fetch_assoc()) {
                            if ($row['payment_status'] == 1) {
                                $payment_html = '<span style="color:#34d399; font-weight:600;">✅ Paid</span>';
                            } else {
                                $payment_html = '<span style="color:#f87171; font-weight:600;">⏳ Pending</span>';
                            }
                    ?>
                    <tr>
                        <td><?php echo $row_num; $row_num++; ?></td>
                        <td><?php echo $row['material_name']; ?></td>
                        <td><?php echo $row['supplier_name']; ?></td>
                        <td><?php echo $row['purchase_date']; ?></td>
                        <td><?php echo number_format((int)$row['quantity']); ?></td>
                        <td>₹<?php echo number_format((float)$row['rate'], 2); ?></td>
                        <td>₹<?php echo number_format((float)$row['total_amount'], 2); ?></td>
                        <td><?php echo $payment_html; ?></td>
                        <td><?php echo $row['created_at']; ?></td>
                        <td>
                            <div class="action-group">
                                <button class="btn-sm btn-edit" onclick="openUpdateModal(<?php echo json_encode($row); ?>)">✏️ Edit</button>
                                <a href="?delete_purchase=<?php echo (int)$row['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this purchase? Stock will be reversed.')">🗑️ Delete</a>
                            </div>
                        </td>
                    </tr>
                    <?php
                        }
                    } else {
                        echo '<tr><td colspan="10" style="text-align:center; color:#94a3b8; padding:30px;">No purchase records found.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) { ?>
            <div class="pagination">
                <?php if ($page_num > 1) { ?>
                    <a href="<?php echo paginate_url(1); ?>">«</a>
                    <a href="<?php echo paginate_url($page_num - 1); ?>">‹</a>
                <?php } ?>
                <?php
                $start = $page_num - 2;
                $end   = $page_num + 2;
                if ($start < 1) $start = 1;
                if ($end > $total_pages) $end = $total_pages;
                for ($p = $start; $p <= $end; $p++) {
                    if ($p == $page_num) {
                        echo '<span class="current">' . $p . '</span>';
                    } else {
                        echo '<a href="' . paginate_url($p) . '">' . $p . '</a>';
                    }
                }
                ?>
                <?php if ($page_num < $total_pages) { ?>
                    <a href="<?php echo paginate_url($page_num + 1); ?>">›</a>
                    <a href="<?php echo paginate_url($total_pages); ?>">»</a>
                <?php } ?>
            </div>
            <?php } ?>

        </section>

    </main>

    <div id="updateModal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeUpdateModal()">✕</button>
            <h3 style="margin-bottom:20px;">✏️ Update Material Purchase</h3>
            <form method="post" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="purchase_id" id="u_purchase_id">
                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:16px;">

                    <div>
                        <label>Material</label>
                        <select class="input" name="material_id" id="u_material_id" required>
                            <?php
                            for ($j = 0; $j < count($materials_list); $j++) {
                                $m = $materials_list[$j];
                                echo '<option value="' . (int)$m['id'] . '">' . $m['material_name'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label>Supplier</label>
                        <select class="input" name="supplier_id" id="u_supplier_id" required>
                            <?php
                            for ($j = 0; $j < count($suppliers_list); $j++) {
                                $s = $suppliers_list[$j];
                                echo '<option value="' . (int)$s['id'] . '">' . $s['name'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label>Purchase Date</label>
                        <input type="date" class="input" name="purchase_date" id="u_purchase_date" required>
                    </div>

                    <div>
                        <label>Quantity</label>
                        <input type="number" class="input" name="quantity" id="u_quantity" min="1" required>
                    </div>

                    <div>
                        <label>Rate (per unit ₹)</label>
                        <input type="number" class="input" name="rate" id="u_rate" min="0" step="0.01" required>
                    </div>

                    <div>
                        <label>Total Amount (₹)</label>
                        <input type="number" class="input" name="total_amount" id="u_total_amount" min="0" step="0.01" required>
                    </div>

                    <div style="grid-column:span 2;">
                        <label>Payment Status</label>
                        <select class="input" name="payment_status" id="u_payment_status" required>
                            <option value="1">Paid</option>
                            <option value="0">Pending</option>
                        </select>
                    </div>

                </div>
                <div style="margin-top:24px; display:flex; gap:12px;">
                    <button type="submit" class="upload-btn">💾 Save Changes</button>
                    <button type="button" class="upload-btn" style="background:rgba(255,255,255,.1); box-shadow:none;" onclick="closeUpdateModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/script.js"></script>
    <script>
        document.getElementById('add_quantity').addEventListener('input', function() {
            var qty  = parseFloat(document.getElementById('add_quantity').value) || 0;
            var rate = parseFloat(document.getElementById('add_rate').value) || 0;
            document.getElementById('add_total_amount').value = (qty * rate).toFixed(2);
        });

        document.getElementById('add_rate').addEventListener('input', function() {
            var qty  = parseFloat(document.getElementById('add_quantity').value) || 0;
            var rate = parseFloat(document.getElementById('add_rate').value) || 0;
            document.getElementById('add_total_amount').value = (qty * rate).toFixed(2);
        });

        document.getElementById('u_quantity').addEventListener('input', function() {
            var qty  = parseFloat(document.getElementById('u_quantity').value) || 0;
            var rate = parseFloat(document.getElementById('u_rate').value) || 0;
            document.getElementById('u_total_amount').value = (qty * rate).toFixed(2);
        });

        document.getElementById('u_rate').addEventListener('input', function() {
            var qty  = parseFloat(document.getElementById('u_quantity').value) || 0;
            var rate = parseFloat(document.getElementById('u_rate').value) || 0;
            document.getElementById('u_total_amount').value = (qty * rate).toFixed(2);
        });

        function openUpdateModal(row) {
            document.getElementById('u_purchase_id').value    = row.id;
            document.getElementById('u_material_id').value    = row.material_id;
            document.getElementById('u_supplier_id').value    = row.supplier_id;
            document.getElementById('u_purchase_date').value  = row.purchase_date;
            document.getElementById('u_quantity').value       = row.quantity;
            document.getElementById('u_rate').value           = row.rate;
            document.getElementById('u_total_amount').value   = row.total_amount;
            document.getElementById('u_payment_status').value = row.payment_status;
            document.getElementById('updateModal').classList.add('active');
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').classList.remove('active');
        }

        document.getElementById('updateModal').addEventListener('click', function(e) {
            if (e.target == this) {
                closeUpdateModal();
            }
        });

        function toggleProductionForm() {
            var f = document.getElementById('Form');
            if (f.style.display == 'none' || f.style.display == '') {
                f.style.display = 'block';
            } else {
                f.style.display = 'none';
            }
        }
    </script>

</div>
</body>
</html>
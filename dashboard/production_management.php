<?php
include '../config.php';
include "login_check.php";
$page = 'production_management';
include "access.php";
check_access($page);

function production_status($current, $min) {
    if ($current > $min) {
        return ['label' => 'Ready for sale', 'color' => '#34d399'];
    } else if ($current <= 0) {
        return ['label' => 'Out of Stock', 'color' => '#f87171'];
    } else {
        return ['label' => 'Low Stock', 'color' => '#fbbf24'];
    }
}

function get_raw_stock($conn) {
    $result = mysqli_query($conn, "SELECT material_name, current_stock FROM raw_material");
    $map = [];
    while ($r = mysqli_fetch_assoc($result)) {
        $map[$r['material_name']] = (int)$r['current_stock'];
    }
    return $map;
}

// ─── ADD ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {

    $product_id     = (int)$_POST['Product_id'];
    $produced_stock = (int)$_POST['Produced_stock'];
    $aggregate      = (int)$_POST['Aggregate'];
    $sand           = (int)$_POST['Sand'];
    $cement         = (int)$_POST['Cement'];
    $red_sand       = (int)$_POST['Red_sand'];

    if ($aggregate == 0 && $sand == 0 && $cement == 0 && $red_sand == 0) {
        header("Location: production_management.php?error=no_materials");
        exit();
    }

    $raw = get_raw_stock($conn);

    $not_enough = false;
    if ($aggregate > ($raw['Aggregate'] ?? 0)) $not_enough = true;
    if ($sand      > ($raw['sand']      ?? 0)) $not_enough = true;
    if ($cement    > ($raw['cement']    ?? 0)) $not_enough = true;
    if ($red_sand  > ($raw['red sand']  ?? 0)) $not_enough = true;

    if ($not_enough) {
        header("Location: production_management.php?error=insufficient_material");
        exit();
    }

    $prod_stmt = $conn->prepare("SELECT current_stock, min_stock FROM production_item WHERE id = ?");
    $prod_stmt->bind_param("i", $product_id);
    $prod_stmt->execute();
    $prod = $prod_stmt->get_result()->fetch_assoc();

    if (!$prod) {
        header("Location: production_management.php?error=invalid_product");
        exit();
    }

    $available = (int)$prod['current_stock'] + $produced_stock;
    $status    = ($available > (int)$prod['min_stock']) ? "Available stock" : "Low Stock";

    $stmt = $conn->prepare("INSERT INTO production_entries (item_id, new_stock, Available, aggregate_used, sand_used, cement_used, red_sand_used, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiiiss", $product_id, $produced_stock, $available, $aggregate, $sand, $cement, $red_sand, $status);
    $stmt->execute();

    $upd = $conn->prepare("UPDATE production_item SET current_stock = ? WHERE id = ?");
    $upd->bind_param("ii", $available, $product_id);
    $upd->execute();

    // FIX: material names now match DB exactly (capital first letter)
    $materials = [
        'Aggregate' => $aggregate,
        'sand'      => $sand,
        'cement'    => $cement,
        'red sand'  => $red_sand,
    ];

    foreach ($materials as $name => $used) {
        if ($used > 0) {
            $r = $conn->prepare("UPDATE raw_material SET current_stock = current_stock - ? WHERE material_name = ?");
            $r->bind_param("is", $used, $name);
            $r->execute();
        }
    }

    header("Location: production_management.php?success=added");
    exit();
}

// ─── UPDATE ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {

    $entry_id  = (int)$_POST['entry_id'];
    $new_stock = (int)$_POST['new_stock'];
    $aggregate = (int)$_POST['Aggregate'];
    $sand      = (int)$_POST['sand'];
    $cement    = (int)$_POST['cement'];
    $red_sand  = (int)$_POST['red sand'];

    $old_stmt = $conn->prepare("SELECT * FROM production_entries WHERE id = ?");
    $old_stmt->bind_param("i", $entry_id);
    $old_stmt->execute();
    $old = $old_stmt->get_result()->fetch_assoc();

    if ($old) {

        $item_id = (int)$old['item_id'];

        // FIX: all 4 material names match DB exactly
        $old_mats = [
            'Aggregate' => (int)$old['aggregate_used'],
            'sand'      => (int)$old['sand_used'],
            'cement'    => (int)$old['cement_used'],
            'red sand'  => (int)$old['red_sand_used'],
        ];

        // Step 1: restore old raw materials back to stock
        foreach ($old_mats as $name => $qty) {
            if ($qty > 0) {
                $r = $conn->prepare("UPDATE raw_material SET current_stock = current_stock + ? WHERE material_name = ?");
                $r->bind_param("is", $qty, $name);
                $r->execute();
            }
        }

        // Step 2: reverse old production from product stock
        $old_new_stock = (int)$old['new_stock'];
        $rev = $conn->prepare("UPDATE production_item SET current_stock = current_stock - ? WHERE id = ?");
        $rev->bind_param("ii", $old_new_stock, $item_id);
        $rev->execute();

        // Step 3: check if new raw material amounts are available
        $raw = get_raw_stock($conn);

        $not_enough = false;
        if ($aggregate > ($raw['Aggregate'] ?? 0)) $not_enough = true;
        if ($sand      > ($raw['sand']      ?? 0)) $not_enough = true;
        if ($cement    > ($raw['cement']    ?? 0)) $not_enough = true;
        if ($red_sand  > ($raw['red sand']  ?? 0)) $not_enough = true;

        if ($not_enough) {
            // rollback: put old materials back, restore product stock
            foreach ($old_mats as $name => $qty) {
                if ($qty > 0) {
                    $r = $conn->prepare("UPDATE raw_material SET current_stock = current_stock - ? WHERE material_name = ?");
                    $r->bind_param("is", $qty, $name);
                    $r->execute();
                }
            }
            $rev2 = $conn->prepare("UPDATE production_item SET current_stock = current_stock + ? WHERE id = ?");
            $rev2->bind_param("ii", $old_new_stock, $item_id);
            $rev2->execute();
            header("Location: production_management.php?error=insufficient_material");
            exit();
        }

        // Step 4: calculate new available stock
        $prod_stmt = $conn->prepare("SELECT current_stock, min_stock FROM production_item WHERE id = ?");
        $prod_stmt->bind_param("i", $item_id);
        $prod_stmt->execute();
        $prod = $prod_stmt->get_result()->fetch_assoc();

        $available = (int)$prod['current_stock'] + $new_stock;
        $status    = ($available > (int)$prod['min_stock']) ? "Available stock" : "Low Stock";

        // Step 5: update product stock
        $upd_prod = $conn->prepare("UPDATE production_item SET current_stock = ? WHERE id = ?");
        $upd_prod->bind_param("ii", $available, $item_id);
        $upd_prod->execute();

        // Step 6: deduct new raw materials
        // FIX: material names match DB exactly
        $new_mats = [
            'Aggregate' => $aggregate,
            'sand'      => $sand,
            'cement'    => $cement,
            'red sand'  => $red_sand,
        ];

        foreach ($new_mats as $name => $qty) {
            if ($qty > 0) {
                $r = $conn->prepare("UPDATE raw_material SET current_stock = current_stock - ? WHERE material_name = ?");
                $r->bind_param("is", $qty, $name);
                $r->execute();
            }
        }

        // Step 7: update the entry record
        $upd_entry = $conn->prepare("UPDATE production_entries SET new_stock=?, Available=?, aggregate_used=?, sand_used=?, cement_used=?, red_sand_used=?, status=? WHERE id=?");
        $upd_entry->bind_param("iiiiiisi", $new_stock, $available, $aggregate, $sand, $cement, $red_sand, $status, $entry_id);
        $upd_entry->execute();
    }

    header("Location: production_management.php?success=updated");
    exit();
}

// ─── DELETE ───────────────────────────────────────────────────────────────────
if (isset($_GET['delete_entry'])) {

    $del_id = (int)$_GET['delete_entry'];

    $old_stmt = $conn->prepare("SELECT * FROM production_entries WHERE id = ?");
    $old_stmt->bind_param("i", $del_id);
    $old_stmt->execute();
    $old = $old_stmt->get_result()->fetch_assoc();

    if ($old) {

        $item_id = (int)$old['item_id'];

        // FIX: material names match DB exactly
        $mats = [
            'Aggregate' => (int)$old['aggregate_used'],
            'sand'      => (int)$old['sand_used'],
            'cement'    => (int)$old['cement_used'],
            'red sand'  => (int)$old['red_sand_used'],
        ];

        foreach ($mats as $name => $qty) {
            if ($qty > 0) {
                $r = $conn->prepare("UPDATE raw_material SET current_stock = current_stock + ? WHERE material_name = ?");
                $r->bind_param("is", $qty, $name);
                $r->execute();
            }
        }

        $old_new_stock = (int)$old['new_stock'];
        $rev = $conn->prepare("UPDATE production_item SET current_stock = GREATEST(0, current_stock - ?) WHERE id = ?");
        $rev->bind_param("ii", $old_new_stock, $item_id);
        $rev->execute();

        $del = $conn->prepare("DELETE FROM production_entries WHERE id = ?");
        $del->bind_param("i", $del_id);
        $del->execute();
    }

    header("Location: production_management.php?success=deleted");
    exit();
}

// ─── CSV EXPORT ───────────────────────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="production_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Product', 'New Stock', 'Available', 'Aggregate Used', 'Sand Used', 'Cement Used', 'Red Sand Used', 'Status', 'Date']);
    $rows = mysqli_query($conn, "SELECT pi.name, pe.new_stock, pe.Available, pe.aggregate_used, pe.sand_used, pe.cement_used, pe.red_sand_used, pe.status, pe.entry_date FROM production_entries pe JOIN production_item pi ON pe.item_id = pi.id ORDER BY pe.entry_date DESC");
    while ($r = mysqli_fetch_row($rows)) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit();
}

// ─── FILTERS & PAGINATION ─────────────────────────────────────────────────────
$s_product = isset($_GET['s_product']) ? (int)$_GET['s_product'] : 0;
$s_status  = isset($_GET['s_status'])  ? $_GET['s_status']       : '';
$s_from    = isset($_GET['s_from'])    ? $_GET['s_from']         : '';
$s_to      = isset($_GET['s_to'])      ? $_GET['s_to']           : '';

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if ($s_product) {
    $where    .= " AND pe.item_id = ?";
    $params[]  = $s_product;
    $types    .= 'i';
}
if ($s_status) {
    $where    .= " AND pe.status = ?";
    $params[]  = $s_status;
    $types    .= 's';
}
if ($s_from) {
    $where    .= " AND pe.entry_date >= ?";
    $params[]  = $s_from;
    $types    .= 's';
}
if ($s_to) {
    $where    .= " AND pe.entry_date <= ?";
    $params[]  = $s_to;
    $types    .= 's';
}

$per_page = 15;
$page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page_num < 1) $page_num = 1;
$offset = ($page_num - 1) * $per_page;

$count_stmt = $conn->prepare("SELECT COUNT(*) FROM production_entries pe $where");
if (count($params) > 0) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows  = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_rows / $per_page);
if ($total_pages < 1) $total_pages = 1;

$entry_sql  = "SELECT pe.*, pi.name AS product_name FROM production_entries pe JOIN production_item pi ON pe.item_id = pi.id $where ORDER BY pe.entry_date DESC LIMIT ? OFFSET ?";
$entry_stmt = $conn->prepare($entry_sql);
$fp   = $params;
$ft   = $types . 'ii';
$fp[] = $per_page;
$fp[] = $offset;
$entry_stmt->bind_param($ft, ...$fp);
$entry_stmt->execute();
$entries_result = $entry_stmt->get_result();

$products_list = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM production_item ORDER BY id"), MYSQLI_ASSOC);
$raw           = get_raw_stock($conn);

function paginate_url($p) {
    $params = $_GET;
    $params['page'] = $p;
    unset($params['delete_entry'], $params['export_csv'], $params['success'], $params['error']);
    return 'production_management.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rifat Enterprise | Production Inventory</title>
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

        .avail-chip { font-size:11px; color:#94a3b8; margin-top:3px; }
        .avail-chip.low { color:#f87171; }
    </style>
</head>

<body>
<div class="container">

    <?php include "sidebar.php"; ?>

    <main class="main">

        <div class="header">
            <h1>Production Inventory</h1>
            <div class="profile" onclick="window.location.href='/dashboard/profile.php'" style="cursor:pointer;">
                <div class="avatar"></div>
                <span><?php echo $_SESSION["username"]; ?></span>
            </div>
        </div>

        <?php if (isset($_GET['success'])) {
            $msgs = [
                'added'   => 'Production entry added successfully.',
                'updated' => 'Entry updated successfully.',
                'deleted' => 'Entry deleted and stock restored.'
            ];
            $msg = isset($msgs[$_GET['success']]) ? $msgs[$_GET['success']] : 'Action completed.';
            echo '<div style="background:rgba(16,185,129,.15); color:#34d399; padding:12px 20px; border-radius:8px; margin-bottom:16px;">✅ ' . $msg . '</div>';
        } ?>

        <?php if (isset($_GET['error'])) {
            $errs = [
                'insufficient_material' => 'Insufficient raw material stock.',
                'no_materials'          => 'At least one raw material must be used.',
                'invalid_product'       => 'Invalid product selected.'
            ];
            $err = isset($errs[$_GET['error']]) ? $errs[$_GET['error']] : htmlspecialchars($_GET['error']);
            echo '<div style="background:rgba(239,68,68,.15); color:#f87171; padding:12px 20px; border-radius:8px; margin-bottom:16px;">⚠️ ' . $err . '</div>';
        } ?>

        <section class="stats-grid">
            <?php
            for ($i = 0; $i < count($products_list); $i++) {
                $prod = $products_list[$i];
                $s    = production_status($prod['current_stock'], $prod['min_stock']);

                $pct = 0;
                if ($prod['min_stock'] > 0) {
                    $pct = round($prod['current_stock'] / $prod['min_stock'] * 100);
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
            ?>
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon used"><?php if($prod['name'] === 'Burnt Clay Bricks') {
                            echo '<img src="../assets/images/bricks.png" alt="Bricks">';
                        } else if($prod['name'] === 'Cement Blocks') {
                            echo '<img src="../assets/images/c_block.png" alt="block">';
                        } else {
                            echo '📦';
                        }
                        ?></div>
                    <div><?php echo $prod['name']; ?></div>
                </div>
                <div class="stat-number"><?php echo number_format((int)$prod['current_stock']); ?></div>
                <div class="stat-label" style="color:<?php echo $s['color']; ?>"><?php echo $s['label']; ?></div>
                <div class="stock-bar-bg">
                    <div class="stock-bar-fill" style="width:<?php echo $pct; ?>%; background:<?php echo $bar_color; ?>;"></div>
                </div>
            </div>
            <?php } ?>
        </section>

        <section class="recent-section">

            <div class="section-header">
                <h3>Production Entries <span style="font-size:13px; color:#94a3b8; font-weight:400; margin-left:8px;">(<?php echo $total_rows; ?> records)</span></h3>
                <div style="display:flex; gap:10px;">
                    <a href="production_management.php?export_csv=1&<?php echo http_build_query(array_filter(['s_product' => $s_product, 's_status' => $s_status, 's_from' => $s_from, 's_to' => $s_to])); ?>"
                       class="upload-btn" style="background:linear-gradient(135deg,#10b981,#059669); text-decoration:none;">
                        📥 Export CSV
                    </a>
                    <button class="upload-btn" onclick="toggleProductionForm()">➕ Add Production Entry</button>
                </div>
            </div>

            <div id="Form" style="display:none; margin-top:24px;">
                <div class="design-card">
                    <h3 style="margin-bottom:16px;">Add Production Entry</h3>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="add">
                        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">

                            <div>
                                <label>Product</label>
                                <select class="input" name="Product_id" required>
                                    <?php for ($i = 0; $i < count($products_list); $i++) {
                                        $item = $products_list[$i];
                                        echo '<option value="' . (int)$item['id'] . '">' . $item['name'] . ' (Stock: ' . number_format((int)$item['current_stock']) . ')</option>';
                                    } ?>
                                </select>
                            </div>

                            <div>
                                <label>Produced Quantity</label>
                                <input type="number" class="input" name="Produced_stock" min="1" placeholder="0" required>
                            </div>

                            <?php
                            $mat_fields = [
                                ['key' => 'Aggregate', 'label' => 'Aggregate Used', 'name' => 'Aggregate'],
                                ['key' => 'sand',      'label' => 'Sand Used',      'name' => 'Sand'],
                                ['key' => 'cement',    'label' => 'Cement Used',    'name' => 'Cement'],
                                ['key' => 'red sand',  'label' => 'Red Sand Used',  'name' => 'Red_sand'],
                            ];
                            for ($i = 0; $i < count($mat_fields); $i++) {
                                $mf    = $mat_fields[$i];
                                $avail = isset($raw[$mf['key']]) ? $raw[$mf['key']] : 0;
                                $low   = $avail < 10;
                            ?>
                            <div>
                                <label><?php echo $mf['label']; ?></label>
                                <input type="number" class="input" name="<?php echo $mf['name']; ?>"
                                       value="0" min="0" max="<?php echo $avail; ?>"
                                       oninput="if(+this.value>+this.max)this.value=this.max" required>
                                <div class="avail-chip <?php echo $low ? 'low' : ''; ?>">
                                    Available: <?php echo number_format($avail); ?><?php echo $low ? ' ⚠️' : ''; ?>
                                </div>
                            </div>
                            <?php } ?>

                        </div>
                        <div style="margin-top:20px; display:flex; gap:12px;">
                            <button type="submit" class="upload-btn">💾 Save Entry</button>
                            <button type="button" class="upload-btn" style="background:rgba(255,255,255,.1); box-shadow:none;" onclick="toggleProductionForm()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <form method="GET" action="production_management.php">
                <div class="filter-bar">
                    <div class="fi">
                        <label>Product</label>
                        <select name="s_product">
                            <option value="">All Products</option>
                            <?php for ($i = 0; $i < count($products_list); $i++) {
                                $item     = $products_list[$i];
                                $selected = ($s_product == $item['id']) ? 'selected' : '';
                                echo '<option value="' . (int)$item['id'] . '" ' . $selected . '>' . $item['name'] . '</option>';
                            } ?>
                        </select>
                    </div>
                    <div class="fi">
                        <label>Status</label>
                        <select name="s_status">
                            <option value="">All</option>
                            <option value="Available stock" <?php echo $s_status == 'Available stock' ? 'selected' : ''; ?>>Available</option>
                            <option value="Low Stock"       <?php echo $s_status == 'Low Stock'       ? 'selected' : ''; ?>>Low Stock</option>
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
                    <a href="production_management.php" class="filter-btn btn-reset">✖ Reset</a>
                </div>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>New Stock</th>
                        <th>Available</th>
                        <th>Aggregate</th>
                        <th>Sand</th>
                        <th>Cement</th>
                        <th>Red Sand</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($entries_result->num_rows > 0) {
                        $row_num = ($page_num - 1) * $per_page + 1;
                        while ($entry = $entries_result->fetch_assoc()) {
                            if ($entry['status'] == 'Available stock') {
                                $status_color = '#34d399';
                            } else {
                                $status_color = '#fbbf24';
                            }
                    ?>
                        <tr>
                            <td><?php echo $row_num; $row_num++; ?></td>
                            <td><?php echo $entry['product_name']; ?></td>
                            <td><?php echo number_format((int)$entry['new_stock']); ?></td>
                            <td><?php echo number_format((int)$entry['Available']); ?></td>
                            <td><?php echo number_format((int)$entry['aggregate_used']); ?></td>
                            <td><?php echo number_format((int)$entry['sand_used']); ?></td>
                            <td><?php echo number_format((int)$entry['cement_used']); ?></td>
                            <td><?php echo number_format((int)$entry['red_sand_used']); ?></td>
                            <td style="color:<?php echo $status_color; ?>; font-weight:600;"><?php echo $entry['status']; ?></td>
                            <td><?php echo date("Y-m-d", strtotime($entry['entry_date'])); ?></td>
                            <td>
                                <div class="action-group">
                                    <a href="?delete_entry=<?php echo (int)$entry['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this entry? Raw materials will be restored.')">🗑️ Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php
                        }
                    } else {
                        echo '<tr><td colspan="11" style="text-align:center; color:#94a3b8; padding:30px;">No production entries found.</td></tr>';
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
            <button class="modal-close" onclick="closeUpdateForm()">✕</button>
            <h3 style="margin-bottom:20px;">✏️ Update Production Entry</h3>
            <form method="post" action="">
                <input type="hidden" name="action"   value="update">
                <input type="hidden" name="entry_id" id="u_entry_id">
                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:16px;">
                    <div>
                        <label>New Stock Produced</label>
                        <input type="number" class="input" name="new_stock" id="u_new_stock" min="0" required>
                    </div>
                    <div>
                        <label>Aggregate Used</label>
                        <input type="number" class="input" name="Aggregate" id="u_aggregate" min="0" required>
                    </div>
                    <div>
                        <label>Sand Used</label>
                        <input type="number" class="input" name="Sand" id="u_sand" min="0" required>
                    </div>
                    <div>
                        <label>Cement Used</label>
                        <input type="number" class="input" name="Cement" id="u_cement" min="0" required>
                    </div>
                    <div style="grid-column:span 2;">
                        <label>Red Sand Used</label>
                        <input type="number" class="input" name="Red_sand" id="u_red_sand" min="0" required>
                    </div>
                </div>
                <div style="margin-top:24px; display:flex; gap:12px;">
                    <button type="submit" class="upload-btn">💾 Save Changes</button>
                    <button type="button" class="upload-btn" style="background:rgba(255,255,255,.1); box-shadow:none;" onclick="closeUpdateForm()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/script.js"></script>
    <script>
        function toggleProductionForm() {
            var f = document.getElementById('Form');
            if (f.style.display == 'none' || f.style.display == '') {
                f.style.display = 'block';
            } else {
                f.style.display = 'none';
            }
        }

        function openUpdateForm(entry) {
            document.getElementById('u_entry_id').value  = entry.id;
            document.getElementById('u_new_stock').value = entry.new_stock;
            document.getElementById('u_aggregate').value = entry.aggregate_used;
            document.getElementById('u_sand').value      = entry.sand_used;
            document.getElementById('u_cement').value    = entry.cement_used;
            document.getElementById('u_red_sand').value  = entry.red_sand_used;
            document.getElementById('updateModal').classList.add('active');
        }

        function closeUpdateForm() {
            document.getElementById('updateModal').classList.remove('active');
        }

        document.getElementById('updateModal').addEventListener('click', function(e) {
            if (e.target == this) {
                closeUpdateForm();
            }
        });
    </script>
</div>
</body>
</html>
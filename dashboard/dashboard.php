<?php
include '../config.php';
include "login_check.php";
include "access.php";
check_access("dashboard");

$query1 = mysqli_query($conn, "SELECT SUM(current_stock) FROM raw_material");
$row1 = mysqli_fetch_row($query1);

$raw_total = $row1[0];
if ($raw_total == null) {
    $raw_total = 0;
}

$query2 = mysqli_query($conn, "SELECT SUM(current_stock) FROM production_item");
$row2 = mysqli_fetch_row($query2);

$prod_total = $row2[0];
if ($prod_total == null) {
    $prod_total = 0;
}

$total_inventory = $raw_total + $prod_total;

$query3 = mysqli_query($conn, "SELECT COUNT(*) FROM production_item");
$row3 = mysqli_fetch_row($query3);


$product_types = $row3[0];

$query4 = mysqli_query($conn, "SELECT COUNT(*) FROM raw_material WHERE current_stock < min_stock");
$row4 = mysqli_fetch_row($query4);
$low_raw = $row4[0];

$query5 = mysqli_query($conn, "SELECT COUNT(*) FROM production_item WHERE current_stock < min_stock");
$row5 = mysqli_fetch_row($query5);
$low_prod = $row5[0];

$low_stock_count = $low_raw + $low_prod;

$query6 = mysqli_query($conn, "SELECT SUM(amount), SUM(qty) FROM sales WHERE MONTH(date) = MONTH(NOW()) AND YEAR(date) = YEAR(NOW())");
$row6 = mysqli_fetch_row($query6);
$month_revenue = $row6[0];
$month_units = $row6[1];
if ($month_revenue == null) {
    $month_revenue = 0;
}
if ($month_units == null) {
    $month_units = 0;
}

$query7 = mysqli_query($conn, "SELECT SUM(amount) FROM sales WHERE status = 'pending'");
$row7 = mysqli_fetch_row($query7);
$pending_total = $row7[0];
if ($pending_total == null) {
    $pending_total = 0;
}

$activity_q = mysqli_query($conn,
    "(SELECT 'Production' AS type, pi.name AS item, pe.new_stock AS qty, '+' AS direction, pe.entry_date AS date
      FROM production_entries pe JOIN production_item pi ON pe.item_id = pi.id ORDER BY pe.entry_date DESC LIMIT 5)
     UNION ALL
     (SELECT 'Sale' AS type, pi.name AS item, s.qty AS qty, '-' AS direction, s.date AS date
      FROM sales s JOIN production_item pi ON s.product_id = pi.id ORDER BY s.date DESC LIMIT 5)
     ORDER BY date DESC LIMIT 8");

$alerts = [];

$result = mysqli_query($conn, "SELECT material_name, current_stock, min_stock, unit FROM raw_material WHERE current_stock < min_stock AND current_stock > 0");
while ($r = mysqli_fetch_assoc($result)) {
    $alerts[] = ['icon' => '⚠️', 'msg' => ucfirst($r['material_name']) . ' stock below reorder level (' . $r['current_stock'] . ' ' . $r['unit'] . ' remaining)'];
}

$result = mysqli_query($conn, "SELECT material_name FROM raw_material WHERE current_stock <= 0");
while ($r = mysqli_fetch_assoc($result)) {
    $alerts[] = ['icon' => '❌', 'msg' => ucfirst($r['material_name']) . ' is out of stock'];
}

$result = mysqli_query($conn, "SELECT name, current_stock FROM production_item WHERE current_stock < min_stock AND current_stock > 0");
while ($r = mysqli_fetch_assoc($result)) {
    $alerts[] = ['icon' => '📉', 'msg' => $r['name'] . ' is running low (' . $r['current_stock'] . ' units)'];
}

$result = mysqli_query($conn, "SELECT name FROM production_item WHERE current_stock <= 0");
while ($r = mysqli_fetch_assoc($result)) {
    $alerts[] = ['icon' => '❌', 'msg' => $r['name'] . ' is out of stock'];
}

if ($pending_total > 0) {
    $alerts[] = ['icon' => '💰', 'msg' => '₹' . number_format($pending_total) . ' in pending payments outstanding'];
}

if (count($alerts) == 0) {
    $alerts[] = ['icon' => '✅', 'msg' => 'All stock levels are healthy'];
}

$snapshot_rm = mysqli_query($conn, "SELECT material_name AS item, 'Raw Material' AS category, current_stock AS available, unit, min_stock AS reorder FROM raw_material ORDER BY id");
$snapshot_fg = mysqli_query($conn, "SELECT name AS item, 'Finished Goods' AS category, current_stock AS available, 'units' AS unit, min_stock AS reorder FROM production_item ORDER BY id");

function convert_money($amount) {
    $amount = (int)$amount;
    if ($amount >= 10000000) {
        return '₹' . number_format($amount / 10000000, 2) . ' Cr';
    } else if ($amount >= 100000) {
        return '₹' . number_format($amount / 100000, 2) . ' L';
    } else if ($amount >= 1000) {
        return '₹' . number_format($amount / 1000, 2) . ' K';
    } else {
        return '₹' . number_format($amount);
    }
}

function time_ago($date) {
    $now = new DateTime();
    $past = new DateTime($date);
    $diff = $now->diff($past);

    if ($diff->days == 0) {
        return 'Today';
    } else if ($diff->days == 1) {
        return 'Yesterday';
    } else {
        return $diff->days . ' days ago';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rifat Enterprise | Inventory Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/dashboard/css/style.css">
    <style>
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-green  { background: rgba(16,185,129,.15); color: #34d399; }
        .badge-red    { background: rgba(239,68,68,.15);  color: #f87171; }
        .badge-yellow { background: rgba(251,191,36,.15); color: #fbbf24; }
        .badge-blue   { background: rgba(99,102,241,.15); color: #818cf8; }

        .activity-dir-plus  { color: #34d399; font-weight: 700; }
        .activity-dir-minus { color: #f87171; font-weight: 700; }

        .alert-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 10px;
            background: rgba(255,255,255,.04);
            margin-bottom: 8px;
            font-size: 13.5px;
            line-height: 1.4;
        }
        .alert-item:last-child { margin-bottom: 0; }

        .quick-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 28px;
        }
        .quick-link-card {
            flex: 1;
            min-width: 140px;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 12px;
            padding: 18px 16px;
            text-align: center;
            cursor: pointer;
            transition: background .2s;
            text-decoration: none;
            color: #e2e8f0;
        }
        .quick-link-card:hover { background: rgba(124,58,237,.15); border-color: rgba(124,58,237,.4); }
        .quick-link-card .ql-icon  { font-size: 26px; margin-bottom: 8px; }
        .quick-link-card .ql-label { font-size: 13px; font-weight: 600; }
    </style>
</head>

<body>
<div class="container">

    <?php include "sidebar.php"; ?>

    <main class="main">

        <div class="header">
            <h1>Inventory Dashboard</h1>
            <div class="profile" onclick="window.location.href='/dashboard/profile.php'" style="cursor:pointer;">
                <div class="avatar"></div>
                <span><?php echo $_SESSION["username"]; ?></span>
            </div>
        </div>

        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon used">📦</div>
                    <div>Total Inventory Items</div>
                </div>
                <div class="stat-number"><?php echo $total_inventory; ?></div>
                <div class="stat-label">Raw Materials + Finished goods</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon recent">🏭</div>
                    <div>Product Types</div>
                </div>
                <div class="stat-number"><?php echo $product_types; ?></div>
                <div class="stat-label">Active product lines</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon stats">⚠️</div>
                    <div>Low / Critical Stock</div>
                </div>
                <?php if ($low_stock_count > 0) { ?>
                    <div class="stat-number" style="color: #f87171;"><?php echo $low_stock_count; ?></div>
                    <div class="stat-label">Items need attention</div>
                <?php } else { ?>
                    <div class="stat-number" style="color: #34d399;"><?php echo $low_stock_count; ?></div>
                    <div class="stat-label">All levels healthy</div>
                <?php } ?>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon used">💰</div>
                    <div>Monthly Revenue</div>
                </div>
                <div class="stat-number"><?php echo convert_money($month_revenue); ?></div>
                <div class="stat-label"><?php echo $month_units; ?> units sold this month</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon stats">⏳</div>
                    <div>Pending Payments</div>
                </div>
                <?php if ($pending_total > 0) { ?>
                    <div class="stat-number" style="color: #fbbf24;"><?php echo convert_money($pending_total); ?></div>
                <?php } else { ?>
                    <div class="stat-number" style="color: #34d399;"><?php echo convert_money($pending_total); ?></div>
                <?php } ?>
                <div class="stat-label">Outstanding receivables</div>
            </div>

        </section>

        <section class="content-grid">

            <div class="recent-section">
                <div class="section-header">
                    <h3>Recent Inventory Activity</h3>
                    <a href="/dashboard/production_management.php" class="upload-btn" style="text-decoration:none;">➕ Add Stock</a>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_rows = mysqli_num_rows($activity_q);
                        if ($total_rows == 0) {
                            echo '<tr><td colspan="4" style="text-align:center; color:#94a3b8; padding:20px;">No activity yet.</td></tr>';
                        } else {
                            while ($a = mysqli_fetch_assoc($activity_q)) {
                                $badge_class = '';
                                if ($a['type'] == 'Production') {
                                    $badge_class = 'badge-blue';
                                } else {
                                    $badge_class = 'badge-green';
                                }

                                $dir_class = '';
                                if ($a['direction'] == '+') {
                                    $dir_class = 'activity-dir-plus';
                                } else {
                                    $dir_class = 'activity-dir-minus';
                                }
                        ?>
                        <tr>
                            <td><?php echo $a['item']; ?></td>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo $a['type']; ?></span></td>
                            <td class="<?php echo $dir_class; ?>"><?php echo $a['direction'] . $a['qty']; ?></td>
                            <td><?php echo time_ago($a['date']); ?></td>
                        </tr>
                        <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="design-card">
                <h3 style="margin-bottom:16px;">Inventory Alerts</h3>
                <?php
                for ($i = 0; $i < count($alerts); $i++) {
                    echo '<div class="alert-item">';
                    echo '<span>' . $alerts[$i]['icon'] . '</span>';
                    echo '<span>' . $alerts[$i]['msg'] . '</span>';
                    echo '</div>';
                }
                ?>
            </div>

        </section>

        <div class="quick-links">
            <a href="/dashboard/production_management.php"   class="quick-link-card"><div class="ql-icon">🧱</div><div class="ql-label">Production</div></a>
            <a href="/dashboard/raw_material_management.php" class="quick-link-card"><div class="ql-icon">📥</div><div class="ql-label">Raw Materials</div></a>
            <a href="/dashboard/sales_management.php"        class="quick-link-card"><div class="ql-icon">🛒</div><div class="ql-label">Sales</div></a>
            <a href="/dashboard/suppliers.php"               class="quick-link-card"><div class="ql-icon">🏭</div><div class="ql-label">Suppliers</div></a>
        </div>

        <section class="recent-section" style="margin-top:32px;">
            <div class="section-header">
                <h3>Inventory Snapshot</h3>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Available</th>
                        <th>Reorder Level</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $snapshots = [];
                    while ($r = mysqli_fetch_assoc($snapshot_rm)) {
                        $snapshots[] = $r;
                    }
                    while ($r = mysqli_fetch_assoc($snapshot_fg)) {
                        $snapshots[] = $r;
                    }

                    for ($i = 0; $i < count($snapshots); $i++) {
                        $row = $snapshots[$i];
                        $avail = $row['available'];
                        $reorder = $row['reorder'];

                        $badge = '';
                        $label = '';

                        if ($avail <= 0) {
                            $badge = 'badge-red';
                            $label = 'Out of Stock';
                        } else if ($avail < $reorder) {
                            $badge = 'badge-yellow';
                            $label = 'Low Stock';
                        } else {
                            $badge = 'badge-green';
                            $label = 'In Stock';
                        }
                    ?>
                    <tr>
                        <td><?php echo ucwords($row['item']); ?></td>
                        <td><span class="badge badge-blue"><?php echo $row['category']; ?></span></td>
                        <td><?php echo $avail . ' ' . $row['unit']; ?></td>
                        <td><?php echo $reorder . ' ' . $row['unit']; ?></td>
                        <td><span class="badge <?php echo $badge; ?>"><?php echo $label; ?></span></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </section>

    </main>

    <script src="js/script.js"></script>
</div>
</body>
</html>
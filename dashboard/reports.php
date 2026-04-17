<?php
include '../config.php';
include "login_check.php";
include "access.php";
check_access("reports");

$range = isset($_GET['range']) ? (int)$_GET['range'] : 30;

if (isset($_GET['date_from']) && $_GET['date_from'] != '') {
    $date_from = $_GET['date_from'];
} else {
    $date_from = date('Y-m-d', strtotime("-{$range} days"));
}

if (isset($_GET['date_to']) && $_GET['date_to'] != '') {
    $date_to = $_GET['date_to'];
} else {
    $date_to = date('Y-m-d');
}

$df = mysqli_real_escape_string($conn, $date_from);
$dt = mysqli_real_escape_string($conn, $date_to);

if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Customer', 'Product', 'Qty', 'Amount (INR)', 'Status']);
    $rows = mysqli_query($conn, "SELECT s.date, s.customer, pi.name, s.qty, s.amount, s.status FROM sales s JOIN production_item pi ON s.product_id = pi.id WHERE s.date BETWEEN '$df' AND '$dt' ORDER BY s.date DESC");
    while ($r = mysqli_fetch_row($rows)) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

$kpi_result = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS total_revenue, COALESCE(SUM(qty), 0) AS total_units, COALESCE(SUM(CASE WHEN status='pending' THEN amount ELSE 0 END),0) AS pending_rev, COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) AS paid_rev, COUNT(*) AS total_orders, COUNT(DISTINCT customer) AS unique_customers FROM sales WHERE date BETWEEN '$df' AND '$dt'");
$kpi = mysqli_fetch_assoc($kpi_result);

$daily_q = mysqli_query($conn, "SELECT date, SUM(amount) AS revenue, SUM(qty) AS units FROM sales WHERE date BETWEEN '$df' AND '$dt' GROUP BY date ORDER BY date ASC");
$daily_labels  = [];
$daily_revenue = [];
$daily_units   = [];
while ($r = mysqli_fetch_assoc($daily_q)) {
    $daily_labels[]  = date('d M', strtotime($r['date']));
    $daily_revenue[] = (int)$r['revenue'];
    $daily_units[]   = (int)$r['units'];
}

$prod_q = mysqli_query($conn, "SELECT pi.name, SUM(s.amount) AS revenue, SUM(s.qty) AS units, COUNT(*) AS orders FROM sales s JOIN production_item pi ON s.product_id = pi.id WHERE s.date BETWEEN '$df' AND '$dt' GROUP BY s.product_id ORDER BY revenue DESC");
$prod_names   = [];
$prod_revenue = [];
$prod_units   = [];
$prod_rows    = [];
while ($r = mysqli_fetch_assoc($prod_q)) {
    $prod_names[]   = $r['name'];
    $prod_revenue[] = (int)$r['revenue'];
    $prod_units[]   = (int)$r['units'];
    $prod_rows[]    = $r;
}

$pay_q = mysqli_query($conn, "SELECT status, SUM(amount) AS total, COUNT(*) AS cnt FROM sales WHERE date BETWEEN '$df' AND '$dt' GROUP BY status");
$pay_labels = [];
$pay_data   = [];
$pay_counts = [];
while ($r = mysqli_fetch_assoc($pay_q)) {
    $pay_labels[] = ucfirst($r['status']);
    $pay_data[]   = (int)$r['total'];
    $pay_counts[] = (int)$r['cnt'];
}

$trend_q = mysqli_query($conn, "SELECT DATE_FORMAT(date,'%b %Y') AS month, YEAR(date) AS yr, MONTH(date) AS mo, SUM(amount) AS revenue, SUM(qty) AS units FROM sales GROUP BY yr, mo ORDER BY yr DESC, mo DESC LIMIT 6");
$trend_labels  = [];
$trend_revenue = [];
$trend_units   = [];
while ($r = mysqli_fetch_assoc($trend_q)) {
    array_unshift($trend_labels,  $r['month']);
    array_unshift($trend_revenue, (int)$r['revenue']);
    array_unshift($trend_units,   (int)$r['units']);
}

$rm_q  = mysqli_query($conn, "SELECT COALESCE(SUM(aggregate_used),0) AS agg, COALESCE(SUM(sand_used),0) AS sand, COALESCE(SUM(cement_used),0) AS cement, COALESCE(SUM(red_sand_used),0) AS red_sand FROM production_entries WHERE entry_date BETWEEN '$df' AND '$dt'");
$rm    = mysqli_fetch_assoc($rm_q);
$rm_labels = ['Aggregate', 'Sand', 'Cement', 'Red Sand'];
$rm_data   = [(int)$rm['agg'], (int)$rm['sand'], (int)$rm['cement'], (int)$rm['red_sand']];

$stock_rm   = mysqli_fetch_all(mysqli_query($conn, "SELECT material_name, current_stock, min_stock, unit FROM raw_material ORDER BY id"), MYSQLI_ASSOC);
$stock_prod = mysqli_fetch_all(mysqli_query($conn, "SELECT name, current_stock, min_stock FROM production_item ORDER BY id"), MYSQLI_ASSOC);

$cust_q   = mysqli_query($conn, "SELECT customer, SUM(amount) AS revenue, SUM(qty) AS units, COUNT(*) AS orders FROM sales WHERE date BETWEEN '$df' AND '$dt' GROUP BY customer ORDER BY revenue DESC LIMIT 5");
$customers = mysqli_fetch_all($cust_q, MYSQLI_ASSOC);

$ps_q = mysqli_query($conn, "SELECT pi.name, COALESCE(SUM(pe.new_stock),0) AS produced FROM production_entries pe JOIN production_item pi ON pe.item_id = pi.id WHERE pe.entry_date BETWEEN '$df' AND '$dt' GROUP BY pe.item_id ORDER BY produced DESC");
$ps_names = [];
$ps_data  = [];
while ($r = mysqli_fetch_assoc($ps_q)) {
    $ps_names[] = $r['name'];
    $ps_data[]  = (int)$r['produced'];
}

$recent_q    = mysqli_query($conn, "SELECT s.date, s.customer, pi.name AS product, s.qty, s.amount, s.status FROM sales s JOIN production_item pi ON s.product_id = pi.id WHERE s.date BETWEEN '$df' AND '$dt' ORDER BY s.date DESC LIMIT 10");
$recent_sales = mysqli_fetch_all($recent_q, MYSQLI_ASSOC);

function money($n) {
    $n = (int)$n;
    if ($n >= 10000000) {
        return '₹' . number_format($n / 10000000, 2) . ' Cr';
    } else if ($n >= 100000) {
        return '₹' . number_format($n / 100000, 2) . ' L';
    } else if ($n >= 1000) {
        return '₹' . number_format($n / 1000, 1) . ' K';
    } else {
        return '₹' . number_format($n);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rifat Enterprise | Reports & Analytics</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,300&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/dashboard/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        .report-section {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 28px;
            box-shadow: var(--shadow-card);
            overflow: visible;
            height: auto;
        }

        .filter-row {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-card);
        }
        .filter-row .fi { display: flex; flex-direction: column; gap: 6px; }
        .filter-row .fi input,
        .filter-row .fi select {
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: var(--radius-sm);
            padding: 9px 14px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            outline: none;
            transition: border-color var(--transition-smooth);
            min-width: 140px;
        }
        .filter-row .fi input:focus,
        .filter-row .fi select:focus { border-color: var(--accent-1); }
        .filter-row label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-muted); margin-bottom: 0; }
        .filter-actions { display: flex; gap: 8px; align-self: flex-end; flex-wrap: wrap; }

        .range-pills { display: flex; gap: 6px; align-self: flex-end; flex-wrap: wrap; }
        .pill {
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid var(--border);
            color: var(--text-muted);
            text-decoration: none;
            cursor: pointer;
            transition: all var(--transition-smooth);
            background: var(--bg-overlay);
            white-space: nowrap;
        }
        .pill:hover  { background: var(--bg-hover); color: var(--text-primary); border-color: var(--border-strong); }
        .pill.active { background: rgba(108,99,255,.2); border-color: var(--accent-1); color: var(--accent-2); }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .kpi-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 22px 20px;
            box-shadow: var(--shadow-card);
            position: relative;
            overflow: hidden;
            transition: all var(--transition-smooth);
        }
        .kpi-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.1), transparent);
        }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-hover); border-color: var(--border-strong); }
        .kpi-icon  { font-size: 22px; margin-bottom: 12px; }
        .kpi-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-muted); margin-bottom: 6px; }
        .kpi-value { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 700; color: var(--text-primary); letter-spacing: -1px; line-height: 1; }
        .kpi-sub   { font-size: 12px; color: var(--text-muted); margin-top: 5px; }
        .kpi-accent-purple { border-top: 2px solid var(--accent-1); }
        .kpi-accent-green  { border-top: 2px solid var(--accent-green); }
        .kpi-accent-blue   { border-top: 2px solid var(--accent-blue); }
        .kpi-accent-pink   { border-top: 2px solid var(--accent-warm); }
        .kpi-accent-yellow { border-top: 2px solid #fbbf24; }
        .kpi-accent-teal   { border-top: 2px solid #2dd4bf; }

        .charts-row { display: grid; gap: 20px; margin-bottom: 24px; }
        .charts-row-2 { grid-template-columns: 1fr 1fr; }
        .charts-row-3 { grid-template-columns: 2fr 1fr 1fr; }

        .chart-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-card);
        }
        .chart-card h4 {
            font-family: 'Syne', sans-serif;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--accent-2);
            margin-bottom: 20px;
        }
        .chart-canvas-wrap { position: relative; }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 600; }
        .badge-green  { background: rgba(52,211,153,.12);  color: #34d399; }
        .badge-red    { background: rgba(248,113,113,.12); color: #f87171; }
        .badge-yellow { background: rgba(251,191,36,.12);  color: #fbbf24; }
        .badge-blue   { background: rgba(96,165,250,.12);  color: #60a5fa; }

        .stock-item { margin-bottom: 16px; }
        .stock-item:last-child { margin-bottom: 0; }
        .stock-row-label { display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: var(--text-secondary); margin-bottom: 6px; }
        .stock-row-label strong { color: var(--text-primary); font-size: 13px; }
        .stock-bg   { background: var(--bg-overlay); border-radius: 100px; height: 7px; }
        .stock-fill { height: 7px; border-radius: 100px; transition: width .6s cubic-bezier(.34,1.56,.64,1); }

        .r-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .r-table th { padding: 10px 12px; border-bottom: 1px solid var(--border); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-muted); }
        .r-table td { padding: 12px 12px; border-bottom: 1px solid rgba(255,255,255,.04); color: var(--text-secondary); }
        .r-table tr:hover td { color: var(--text-primary); background: var(--bg-hover); }
        .r-table tr:last-child td { border-bottom: none; }

        .no-data { text-align: center; color: var(--text-muted); padding: 32px; font-size: 13px; }

        @media (max-width: 1024px) {
            .charts-row-2, .charts-row-3 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">

    <?php include "sidebar.php"; ?>

    <main class="main">

        <div class="header">
            <h1>Reports & Analytics</h1>
            <div class="profile" onclick="window.location.href='/dashboard/profile.php'" style="cursor:pointer;">
                <div class="avatar"></div>
                <span><?php echo $_SESSION["username"]; ?></span>
            </div>
        </div>

        <form method="GET" action="reports.php">
            <div class="filter-row">

                <div class="fi">
                    <label>Quick Range</label>
                    <div class="range-pills">
                        <?php
                        $range_options = ['7' => '7 Days', '30' => '30 Days', '90' => '3 Months', '365' => '1 Year'];
                        foreach ($range_options as $days => $label) {
                            $is_active = ($range == $days && !isset($_GET['date_from'])) ? 'active' : '';
                            echo '<a class="pill ' . $is_active . '" href="reports.php?range=' . $days . '">' . $label . '</a>';
                        }
                        ?>
                    </div>
                </div>

                <div class="fi">
                    <label>From</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="fi">
                    <label>To</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="upload-btn">🔍 Apply</button>
                    <a href="reports.php" class="upload-btn" style="background: var(--bg-elevated); box-shadow: none; border: 1px solid var(--border); color: var(--text-secondary);">✖ Reset</a>
                    <a href="reports.php?export_csv=1&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="upload-btn" style="background: linear-gradient(135deg,#10b981,#059669);">📥 Export CSV</a>
                </div>

            </div>
        </form>

        <div class="kpi-grid">

            <div class="kpi-card kpi-accent-purple">
                <div class="kpi-icon">💰</div>
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value"><?php echo money($kpi['total_revenue']); ?></div>
                <div class="kpi-sub"><?php echo $date_from; ?> → <?php echo $date_to; ?></div>
            </div>

            <div class="kpi-card kpi-accent-green">
                <div class="kpi-icon">✅</div>
                <div class="kpi-label">Paid Revenue</div>
                <div class="kpi-value"><?php echo money($kpi['paid_rev']); ?></div>
                <div class="kpi-sub">Collected payments</div>
            </div>

            <div class="kpi-card kpi-accent-yellow">
                <div class="kpi-icon">⏳</div>
                <div class="kpi-label">Pending Revenue</div>
                <?php
                if ($kpi['pending_rev'] > 0) {
                    $pending_color = '#fbbf24';
                } else {
                    $pending_color = 'var(--text-primary)';
                }
                ?>
                <div class="kpi-value" style="color:<?php echo $pending_color; ?>"><?php echo money($kpi['pending_rev']); ?></div>
                <div class="kpi-sub">Outstanding</div>
            </div>

            <div class="kpi-card kpi-accent-blue">
                <div class="kpi-icon">📦</div>
                <div class="kpi-label">Units Sold</div>
                <div class="kpi-value"><?php echo number_format($kpi['total_units']); ?></div>
                <div class="kpi-sub">Bricks &amp; Blocks</div>
            </div>

            <div class="kpi-card kpi-accent-pink">
                <div class="kpi-icon">🛒</div>
                <div class="kpi-label">Total Orders</div>
                <div class="kpi-value"><?php echo number_format($kpi['total_orders']); ?></div>
                <div class="kpi-sub">Sales transactions</div>
            </div>

            <div class="kpi-card kpi-accent-teal">
                <div class="kpi-icon">👤</div>
                <div class="kpi-label">Unique Customers</div>
                <div class="kpi-value"><?php echo number_format($kpi['unique_customers']); ?></div>
                <div class="kpi-sub">In selected period</div>
            </div>

        </div>

        <div class="charts-row" style="grid-template-columns:1fr; margin-bottom:24px;">
            <div class="chart-card">
                <h4>📈 Daily Revenue & Units — <?php echo $date_from; ?> to <?php echo $date_to; ?></h4>
                <div class="chart-canvas-wrap">
                    <canvas id="dailyChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <div class="charts-row charts-row-2">

            <div class="chart-card">
                <h4>📊 Monthly Revenue Trend (Last 6 Months)</h4>
                <div class="chart-canvas-wrap">
                    <canvas id="trendChart" height="160"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h4>🍩 Payment Status Split</h4>
                <div class="chart-canvas-wrap" style="display:flex; justify-content:center;">
                    <canvas id="paymentChart" height="160" style="max-width:260px;"></canvas>
                </div>
                <?php if (count($pay_labels) > 0) { ?>
                <div style="display:flex; justify-content:center; gap:16px; margin-top:16px; flex-wrap:wrap;">
                    <?php
                    for ($i = 0; $i < count($pay_labels); $i++) {
                        $dot_color = ($i == 0) ? '#34d399' : '#f87171';
                        echo '<div style="display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-secondary);">';
                        echo '<span style="width:10px; height:10px; border-radius:50%; background:' . $dot_color . '; display:inline-block;"></span>';
                        echo $pay_labels[$i] . ': ' . money($pay_data[$i]);
                        echo '</div>';
                    }
                    ?>
                </div>
                <?php } ?>
            </div>

        </div>

        <div class="charts-row charts-row-2">

            <div class="chart-card">
                <h4>🧱 Revenue by Product</h4>
                <div class="chart-canvas-wrap">
                    <canvas id="productChart" height="180"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h4>⚗️ Raw Material Usage</h4>
                <div class="chart-canvas-wrap" style="display:flex; justify-content:center;">
                    <canvas id="rmChart" height="180" style="max-width:260px;"></canvas>
                </div>
                <?php
                $rm_total = 0;
                for ($i = 0; $i < count($rm_data); $i++) {
                    $rm_total += $rm_data[$i];
                }
                if ($rm_total > 0) {
                    $rm_colors = ['#60a5fa', '#fbbf24', '#34d399', '#f87171'];
                ?>
                <div style="display:flex; justify-content:center; gap:12px; margin-top:16px; flex-wrap:wrap;">
                    <?php
                    for ($i = 0; $i < count($rm_labels); $i++) {
                        echo '<div style="display:flex; align-items:center; gap:5px; font-size:12px; color:var(--text-secondary);">';
                        echo '<span style="width:10px; height:10px; border-radius:2px; background:' . $rm_colors[$i] . '; display:inline-block;"></span>';
                        echo $rm_labels[$i] . ': ' . number_format($rm_data[$i]);
                        echo '</div>';
                    }
                    ?>
                </div>
                <?php } ?>
            </div>

        </div>

        <div class="charts-row charts-row-2" style="margin-bottom:24px;">

            <div class="chart-card">
                <h4>📦 Current Stock Levels</h4>

                <div style="margin-bottom:20px;">
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; color:var(--accent-2); margin-bottom:14px;">Raw Materials</div>
                    <?php
                    for ($i = 0; $i < count($stock_rm); $i++) {
                        $s = $stock_rm[$i];

                        $pct = 0;
                        if ($s['min_stock'] > 0) {
                            $pct = round($s['current_stock'] / $s['min_stock'] * 100);
                            if ($pct > 100) $pct = 100;
                        } else {
                            $pct = 0;
                        }

                        if ($pct >= 100) {
                            $color = '#34d399';
                        } else if ($pct >= 50) {
                            $color = '#fbbf24';
                        } else {
                            $color = '#f87171';
                        }
                    ?>
                    <div class="stock-item">
                        <div class="stock-row-label">
                            <span><?php echo ucwords($s['material_name']); ?> <span style="color:var(--text-muted); font-size:11px;">(<?php echo $s['unit']; ?>)</span></span>
                            <strong style="color:<?php echo $color; ?>"><?php echo number_format($s['current_stock']); ?> / <?php echo number_format($s['min_stock']); ?></strong>
                        </div>
                        <div class="stock-bg">
                            <div class="stock-fill" style="width:<?php echo $pct; ?>%; background:<?php echo $color; ?>;"></div>
                        </div>
                    </div>
                    <?php } ?>
                </div>

                <div>
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; color:var(--accent-2); margin-bottom:14px;">Finished Goods</div>
                    <?php
                    for ($i = 0; $i < count($stock_prod); $i++) {
                        $s = $stock_prod[$i];

                        $pct = 0;
                        if ($s['min_stock'] > 0) {
                            $pct = round($s['current_stock'] / $s['min_stock'] * 100);
                            if ($pct > 100) $pct = 100;
                        } else {
                            $pct = 100;
                        }

                        if ($pct >= 100) {
                            $color = '#34d399';
                        } else if ($pct >= 50) {
                            $color = '#fbbf24';
                        } else {
                            $color = '#f87171';
                        }
                    ?>
                    <div class="stock-item">
                        <div class="stock-row-label">
                            <span><?php echo $s['name']; ?></span>
                            <strong style="color:<?php echo $color; ?>"><?php echo number_format($s['current_stock']); ?> / <?php echo number_format($s['min_stock']); ?></strong>
                        </div>
                        <div class="stock-bg">
                            <div class="stock-fill" style="width:<?php echo $pct; ?>%; background:<?php echo $color; ?>;"></div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <div class="chart-card">
                <h4>🏆 Top Customers</h4>
                <?php if (count($customers) == 0) { ?>
                    <div class="no-data">No sales in this period.</div>
                <?php } else { ?>
                <table class="r-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Orders</th>
                            <th>Units</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        for ($i = 0; $i < count($customers); $i++) {
                            $c = $customers[$i];
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);"><?php echo $i + 1; ?></td>
                            <td style="font-weight:600; color:var(--text-primary);"><?php echo $c['customer']; ?></td>
                            <td><?php echo $c['orders']; ?></td>
                            <td><?php echo number_format($c['units']); ?></td>
                            <td style="color:var(--accent-green); font-weight:600;"><?php echo money($c['revenue']); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>
            </div>

        </div>

        <div class="charts-row charts-row-2" style="margin-bottom:24px;">

            <div class="chart-card">
                <h4>📋 Product Sales Breakdown</h4>
                <?php if (count($prod_rows) == 0) { ?>
                    <div class="no-data">No sales in this period.</div>
                <?php } else { ?>
                <table class="r-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Orders</th>
                            <th>Units</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        for ($i = 0; $i < count($prod_rows); $i++) {
                            $p = $prod_rows[$i];
                        ?>
                        <tr>
                            <td style="font-weight:600; color:var(--text-primary);"><?php echo $p['name']; ?></td>
                            <td><?php echo $p['orders']; ?></td>
                            <td><?php echo number_format($p['units']); ?></td>
                            <td style="color:var(--accent-green); font-weight:600;"><?php echo money($p['revenue']); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>
            </div>

            <div class="chart-card">
                <h4>🕒 Recent Sales (Last 10)</h4>
                <?php if (count($recent_sales) == 0) { ?>
                    <div class="no-data">No sales in this period.</div>
                <?php } else { ?>
                <table class="r-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        for ($i = 0; $i < count($recent_sales); $i++) {
                            $s = $recent_sales[$i];
                            if ($s['status'] == 'paid') {
                                $badge_class = 'badge-green';
                            } else {
                                $badge_class = 'badge-yellow';
                            }
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);"><?php echo $s['date']; ?></td>
                            <td><?php echo $s['customer']; ?></td>
                            <td><?php echo $s['product']; ?></td>
                            <td style="color:var(--accent-green); font-weight:600;"><?php echo money($s['amount']); ?></td>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($s['status']); ?></span></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>
            </div>

        </div>

    </main>
</div>

<script>
    Chart.defaults.color                       = 'rgba(240,240,248,0.45)';
    Chart.defaults.borderColor                 = 'rgba(255,255,255,0.07)';
    Chart.defaults.font.family                 = "'DM Sans', sans-serif";
    Chart.defaults.font.size                   = 12;
    Chart.defaults.plugins.legend.display      = false;

    var gridLine = { color: 'rgba(255,255,255,0.05)', drawBorder: false };
    var tooltipDefaults = {
        backgroundColor: '#141720',
        borderColor:     'rgba(108,99,255,0.4)',
        borderWidth:     1,
        titleColor:      '#f0f0f8',
        bodyColor:       'rgba(240,240,248,0.65)',
        padding:         12,
        cornerRadius:    10,
        displayColors:   true,
        boxPadding:      4,
    };

    var dailyLabels  = <?php echo json_encode($daily_labels); ?>;
    var dailyRevenue = <?php echo json_encode($daily_revenue); ?>;
    var dailyUnits   = <?php echo json_encode($daily_units); ?>;

    var trendLabels  = <?php echo json_encode($trend_labels); ?>;
    var trendRevenue = <?php echo json_encode($trend_revenue); ?>;
    var trendUnits   = <?php echo json_encode($trend_units); ?>;

    var prodNames   = <?php echo json_encode($prod_names); ?>;
    var prodRevenue = <?php echo json_encode($prod_revenue); ?>;
    var prodUnits   = <?php echo json_encode($prod_units); ?>;

    var payLabels = <?php echo json_encode($pay_labels); ?>;
    var payData   = <?php echo json_encode($pay_data); ?>;

    var rmLabels = <?php echo json_encode($rm_labels); ?>;
    var rmData   = <?php echo json_encode($rm_data); ?>;

    var dailyCtx = document.getElementById('dailyChart');
    if (dailyCtx && dailyLabels.length == 0) {
        dailyCtx.parentElement.innerHTML = '<div style="text-align:center;color:rgba(240,240,248,.3);padding:40px;font-size:13px;">No data in selected range.</div>';
    } else if (dailyCtx) {
        var gradient1 = dailyCtx.getContext('2d').createLinearGradient(0, 0, 0, 280);
        gradient1.addColorStop(0, 'rgba(108,99,255,0.28)');
        gradient1.addColorStop(1, 'rgba(108,99,255,0.00)');
        var gradient2 = dailyCtx.getContext('2d').createLinearGradient(0, 0, 0, 280);
        gradient2.addColorStop(0, 'rgba(52,211,153,0.18)');
        gradient2.addColorStop(1, 'rgba(52,211,153,0.00)');

        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [
                    {
                        label: 'Revenue (₹)',
                        data: dailyRevenue,
                        borderColor: '#6c63ff',
                        backgroundColor: gradient1,
                        borderWidth: 2.5,
                        pointRadius: dailyLabels.length > 20 ? 0 : 4,
                        pointBackgroundColor: '#6c63ff',
                        pointBorderColor: '#0e1118',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6,
                        tension: 0.42,
                        fill: true,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Units Sold',
                        data: dailyUnits,
                        borderColor: '#34d399',
                        backgroundColor: gradient2,
                        borderWidth: 2,
                        pointRadius: dailyLabels.length > 20 ? 0 : 3,
                        pointBackgroundColor: '#34d399',
                        pointBorderColor: '#0e1118',
                        pointBorderWidth: 2,
                        pointHoverRadius: 5,
                        tension: 0.42,
                        fill: true,
                        yAxisID: 'y1',
                        borderDash: [5, 3],
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, labels: { color: 'rgba(240,240,248,0.55)', usePointStyle: true, pointStyleWidth: 10, boxHeight: 6 } },
                    tooltip: tooltipDefaults,
                },
                scales: {
                    x:  { grid: gridLine },
                    y:  { grid: gridLine, ticks: { callback: function(v) { return '₹' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v); } } },
                    y1: { grid: { display: false }, position: 'right', ticks: { color: 'rgba(52,211,153,0.6)' } },
                }
            }
        });
    }

    var trendCtx = document.getElementById('trendChart');
    if (trendCtx && trendLabels.length == 0) {
        trendCtx.parentElement.innerHTML = '<div style="text-align:center;color:rgba(240,240,248,.3);padding:40px;font-size:13px;">No data.</div>';
    } else if (trendCtx) {
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Revenue (₹)',
                        data: trendRevenue,
                        backgroundColor: 'rgba(108,99,255,0.7)',
                        borderColor: '#6c63ff',
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                    },
                    {
                        label: 'Units',
                        data: trendUnits,
                        backgroundColor: 'rgba(52,211,153,0.55)',
                        borderColor: '#34d399',
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, labels: { color: 'rgba(240,240,248,0.55)', usePointStyle: true, pointStyleWidth: 10, boxHeight: 6 } },
                    tooltip: tooltipDefaults,
                },
                scales: {
                    x:  { grid: { display: false } },
                    y:  { grid: gridLine, ticks: { callback: function(v) { return '₹' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v); } } },
                    y1: { grid: { display: false }, position: 'right', ticks: { color: 'rgba(52,211,153,0.6)' } },
                }
            }
        });
    }

    var payCtx = document.getElementById('paymentChart');
    if (payCtx && payLabels.length == 0) {
        payCtx.parentElement.innerHTML = '<div style="text-align:center;color:rgba(240,240,248,.3);padding:40px;font-size:13px;">No data.</div>';
    } else if (payCtx) {
        new Chart(payCtx, {
            type: 'doughnut',
            data: {
                labels: payLabels,
                datasets: [{
                    data: payData,
                    backgroundColor: ['rgba(52,211,153,0.8)', 'rgba(251,191,36,0.8)'],
                    borderColor: ['#34d399', '#fbbf24'],
                    borderWidth: 2,
                    hoverOffset: 8,
                }]
            },
            options: {
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign({}, tooltipDefaults, { callbacks: { label: function(ctx) { return ' ' + ctx.label + ': ₹' + ctx.parsed.toLocaleString(); } } })
                }
            }
        });
    }

    var prodCtx = document.getElementById('productChart');
    if (prodCtx && prodNames.length == 0) {
        prodCtx.parentElement.innerHTML = '<div style="text-align:center;color:rgba(240,240,248,.3);padding:40px;font-size:13px;">No data.</div>';
    } else if (prodCtx) {
        new Chart(prodCtx, {
            type: 'bar',
            data: {
                labels: prodNames,
                datasets: [
                    {
                        label: 'Revenue (₹)',
                        data: prodRevenue,
                        backgroundColor: ['rgba(108,99,255,0.75)', 'rgba(244,114,182,0.75)', 'rgba(52,211,153,0.75)', 'rgba(96,165,250,0.75)'],
                        borderColor: ['#6c63ff', '#f472b6', '#34d399', '#60a5fa'],
                        borderWidth: 1,
                        borderRadius: 8,
                        borderSkipped: false,
                    },
                    {
                        label: 'Units',
                        data: prodUnits,
                        backgroundColor: 'rgba(96,165,250,0.4)',
                        borderColor: '#60a5fa',
                        borderWidth: 1,
                        borderRadius: 8,
                        borderSkipped: false,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                indexAxis: prodNames.length > 3 ? 'y' : 'x',
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, labels: { color: 'rgba(240,240,248,0.55)', usePointStyle: true, pointStyleWidth: 10, boxHeight: 6 } },
                    tooltip: tooltipDefaults,
                },
                scales: {
                    x: { grid: gridLine, ticks: { callback: function(v) { if (typeof v == 'number') { return '₹' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v); } return v; } } },
                    y: { grid: { display: false } },
                    y1: { display: false },
                }
            }
        });
    }

    var rmCtx  = document.getElementById('rmChart');
    var rmTotal = 0;
    for (var i = 0; i < rmData.length; i++) {
        rmTotal += rmData[i];
    }
    if (rmCtx && rmTotal == 0) {
        rmCtx.parentElement.innerHTML = '<div style="text-align:center;color:rgba(240,240,248,.3);padding:40px;font-size:13px;">No production entries in this period.</div>';
    } else if (rmCtx) {
        new Chart(rmCtx, {
            type: 'doughnut',
            data: {
                labels: rmLabels,
                datasets: [{
                    data: rmData,
                    backgroundColor: ['rgba(96,165,250,0.8)', 'rgba(251,191,36,0.8)', 'rgba(52,211,153,0.8)', 'rgba(248,113,113,0.8)'],
                    borderColor: ['#60a5fa', '#fbbf24', '#34d399', '#f87171'],
                    borderWidth: 2,
                    hoverOffset: 8,
                }]
            },
            options: {
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign({}, tooltipDefaults, { callbacks: { label: function(ctx) { return ' ' + ctx.label + ': ' + ctx.parsed.toLocaleString() + ' units'; } } })
                }
            }
        });
    }
</script>

</body>
</html>
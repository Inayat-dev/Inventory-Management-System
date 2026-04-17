<?php
include '../config.php';
include "login_check.php";
include "access.php";
check_access("sales_management");

function convert_money($amount) {
    if ($amount === null || $amount == 0) return "0";
    $amount = (int)$amount;
    if ($amount >= 10000000) {
        return number_format($amount / 10000000, 2) . ' Crore';
    } else if ($amount >= 100000) {
        return number_format($amount / 100000, 2) . ' Lakh';
    } else if ($amount >= 1000) {
        return number_format($amount / 1000, 2) . ' Thousand';
    }
    return (string)$amount;
}

function pending_amount($conn) {
    $result = mysqli_query($conn, "SELECT amount, customer FROM sales WHERE status = 'pending' ORDER BY amount DESC LIMIT 1");
    $data = mysqli_fetch_assoc($result);
    if ($data) {
        return "⚠️ ₹" . $data['amount'] . " Pending payment from " . htmlspecialchars($data['customer']);
    }
    return "✅ No pending payments";
}

function demand($conn) {
    $result = mysqli_query($conn, "SELECT product_id, COUNT(*) AS total_count FROM sales WHERE product_id IN (1, 2) GROUP BY product_id ORDER BY product_id");
    $data = mysqli_fetch_all($result);
    if (count($data) < 2) {
        return "💰 Not enough data for demand analysis";
    }
    if ($data[0][1] > $data[1][1]) {
        return "💰 High demand for Burnt Clay Bricks";
    } else {
        return "💰 High demand for Cement Blocks";
    }
}

function stock($conn) {
    $result = mysqli_query($conn, "SELECT SUM(CASE WHEN product_id = 1 THEN qty ELSE 0 END) AS total_bricks, SUM(CASE WHEN product_id = 2 THEN qty ELSE 0 END) AS total_cement FROM sales");
    $data = mysqli_fetch_assoc($result);
    if ($data['total_bricks'] > $data['total_cement']) {
        return "📉 Burnt Clay Bricks stock reducing fast";
    }
    return "📉 Cement Blocks stock reducing fast";
}

function get_stock_levels($conn) {
    $result = mysqli_query($conn, "SELECT id, name, current_stock FROM production_item ORDER BY id");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function stock_impact($a, $b) {
    if ($a > $b) {
        return "High";
    } else if ($a == $b) {
        return "Medium";
    } else {
        return "Low";
    }
}

function paginate_url($page) {
    $params = $_GET;
    $params['page'] = $page;
    unset($params['toggle_status'], $params['delete_sale'], $params['invoice']);
    return 'sales_management.php?' . http_build_query($params);
}

/* ═══════════════════════════════════════════════
   PROFESSIONAL INVOICE
═══════════════════════════════════════════════ */
if (isset($_GET['invoice'])) {
    $sale_id = (int)$_GET['invoice'];
    $inv = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT pe.*, pi.name AS product_name, pi.price_per_unit
         FROM sales pe
         JOIN production_item pi ON pe.product_id = pi.id
         WHERE pe.id = $sale_id"
    ));

    if ($inv) {
        $unit_price  = ($inv['qty'] > 0) ? round($inv['amount'] / $inv['qty'], 2) : 0;
        $subtotal    = $inv['amount'];
        $tax_rate    = 0;   // set to 18 for 18% GST if needed
        $tax_amount  = round($subtotal * $tax_rate / 100, 2);
        $grand_total = $subtotal + $tax_amount;
        $inv_number  = 'INV-' . date('Y') . '-' . str_pad($inv['id'], 5, '0', STR_PAD_LEFT);
        $due_date    = date('Y-m-d', strtotime($inv['date'] . ' +30 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo $inv_number; ?> — Rifat Enterprise</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        :root {
            --ink:      #0f172a;
            --ink-mid:  #334155;
            --ink-soft: #64748b;
            --ink-faint:#94a3b8;
            --accent:   #1e3a5f;
            --accent2:  #2e5090;
            --line:     #e2e8f0;
            --page:     #f1f5f9;
            --green:    #065f46;
            --green-bg: #d1fae5;
            --red:      #991b1b;
            --red-bg:   #fee2e2;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--page);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 36px 20px 60px;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            gap: 12px;
            margin-bottom: 28px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .tb-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: opacity .15s, transform .1s;
        }
        .tb-btn:hover { opacity:.86; transform:translateY(-1px); }
        .tb-btn.primary { background: var(--accent); color: #fff; box-shadow: 0 4px 14px rgba(30,58,95,.35); }
        .tb-btn.ghost   { background: #fff; color: var(--ink-mid); border: 1.5px solid var(--line); }

        /* Paper */
        .paper {
            background: #ffffff;
            width: 100%;
            max-width: 800px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,.06), 0 16px 48px rgba(0,0,0,.12);
            overflow: hidden;
        }
        .paper-inner { position: relative; overflow: hidden; }

        /* Watermark */
        .watermark {
            position: absolute;
            top: 46%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-28deg);
            font-family: 'Playfair Display', serif;
            font-size: 120px;
            font-weight: 700;
            color: rgba(6,95,70,.06);
            pointer-events: none;
            user-select: none;
            white-space: nowrap;
            letter-spacing: 14px;
            z-index: 0;
        }

        /* ── Header band ── */
        .header-band {
            background: var(--accent);
            padding: 38px 52px 34px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            position: relative;
            z-index: 1;
        }
        .header-band::after {
            content: '';
            position: absolute;
            bottom: 0; right: 0;
            width: 220px; height: 100%;
            background: rgba(255,255,255,.03);
            clip-path: polygon(40% 0, 100% 0, 100% 100%, 0% 100%);
        }
        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -.3px;
            line-height: 1.1;
        }
        .brand-tagline {
            font-size: 11.5px;
            color: rgba(255,255,255,.55);
            margin-top: 4px;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        .brand-contact {
            margin-top: 16px;
            font-size: 12.5px;
            color: rgba(255,255,255,.7);
            line-height: 1.8;
        }
        .inv-title-block { text-align: right; }
        .inv-word {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            font-weight: 700;
            color: rgba(255,255,255,.13);
            letter-spacing: 6px;
            text-transform: uppercase;
            line-height: 1;
        }
        .inv-number {
            font-size: 14px;
            font-weight: 600;
            color: rgba(255,255,255,.88);
            margin-top: 8px;
            letter-spacing: .05em;
        }
        .status-chip {
            display: inline-block;
            padding: 5px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-top: 10px;
        }
        .chip-paid    { background: var(--green-bg); color: var(--green); }
        .chip-pending { background: var(--red-bg);   color: var(--red);   }

        /* ── Meta strip ── */
        .meta-strip {
            background: #f8fafc;
            border-bottom: 1px solid var(--line);
            padding: 18px 52px;
            display: flex;
            gap: 0;
            position: relative;
            z-index: 1;
        }
        .meta-cell { flex: 1; }
        .meta-cell:not(:last-child) {
            border-right: 1px solid var(--line);
            padding-right: 24px;
            margin-right: 24px;
        }
        .meta-label {
            font-size: 10px;
            font-weight: 600;
            color: var(--ink-faint);
            text-transform: uppercase;
            letter-spacing: .1em;
            margin-bottom: 4px;
        }
        .meta-value {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--ink);
        }

        /* ── Body ── */
        .body-pad {
            padding: 40px 52px 32px;
            position: relative;
            z-index: 1;
        }

        /* Bill section */
        .bill-section {
            display: flex;
            justify-content: space-between;
            gap: 32px;
            margin-bottom: 36px;
            padding-bottom: 28px;
            border-bottom: 1px solid var(--line);
        }
        .bill-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--ink-faint);
            text-transform: uppercase;
            letter-spacing: .1em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .bill-label::after {
            content: '';
            display: block;
            width: 32px;
            height: 2px;
            background: var(--accent);
            border-radius: 2px;
        }
        .bill-name {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 600;
            color: var(--ink);
            line-height: 1.2;
        }
        .bill-sub {
            font-size: 13px;
            color: var(--ink-soft);
            margin-top: 3px;
        }

        /* Items table */
        .items-wrap {
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table thead tr { background: var(--accent); }
        .items-table thead th {
            padding: 13px 18px;
            font-size: 10.5px;
            font-weight: 600;
            color: rgba(255,255,255,.8);
            text-transform: uppercase;
            letter-spacing: .09em;
            text-align: left;
        }
        .items-table thead th:nth-child(2),
        .items-table thead th:nth-child(3) { text-align: center; }
        .items-table thead th:last-child    { text-align: right; }

        .items-table tbody tr { border-bottom: 1px solid var(--line); }
        .items-table tbody tr:last-child { border-bottom: none; }
        .items-table tbody tr:nth-child(even) { background: #fafbfd; }
        .items-table tbody td {
            padding: 16px 18px;
            font-size: 14px;
            color: var(--ink-mid);
            vertical-align: middle;
        }
        .items-table tbody td:nth-child(2),
        .items-table tbody td:nth-child(3) { text-align: center; }
        .items-table tbody td:last-child    { text-align: right; font-weight: 600; color: var(--ink); }
        .product-name { font-weight: 600; color: var(--ink); font-size: 14.5px; }
        .product-desc { font-size: 12px; color: var(--ink-faint); margin-top: 3px; }

        /* Totals */
        .totals-outer {
            display: flex;
            justify-content: flex-end;
            border-top: 1px solid var(--line);
            padding-top: 24px;
            margin-top: 24px;
        }
        .totals-inner { width: 290px; }
        .t-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 13.5px;
            color: var(--ink-mid);
            border-bottom: 1px dashed var(--line);
        }
        .t-row:last-child { border-bottom: none; }
        .t-row.grand {
            border-top: 2px solid var(--accent);
            border-bottom: none;
            margin-top: 10px;
            padding-top: 14px;
            font-size: 16px;
            font-weight: 700;
            color: var(--ink);
        }
        .t-row.grand .t-val { color: var(--accent); font-size: 19px; }

        /* Bottom strip */
        .bottom-strip {
            border-top: 1px solid var(--line);
            padding: 28px 52px;
            display: flex;
            gap: 36px;
            background: #f8fafc;
            position: relative;
            z-index: 1;
        }
        .note-block { flex: 1; }
        .note-head {
            font-size: 10px;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: .1em;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1.5px solid var(--accent);
            display: inline-block;
        }
        .note-text {
            font-size: 12.5px;
            color: var(--ink-soft);
            line-height: 1.75;
            margin-top: 6px;
        }
        .sig-block {
            flex: 0 0 160px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: flex-end;
        }
        .sig-line {
            width: 150px;
            border-top: 1.5px solid var(--ink-mid);
            padding-top: 8px;
            text-align: center;
        }
        .sig-line .s-title { font-size: 10.5px; color: var(--ink-soft); text-transform: uppercase; letter-spacing: .07em; }
        .sig-line .s-name  { font-size: 13px; font-weight: 600; color: var(--ink); margin-top: 2px; }

        /* Footer band */
        .footer-band {
            background: var(--accent);
            padding: 14px 52px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        .footer-band span { font-size: 11.5px; color: rgba(255,255,255,.6); }
        .footer-band .thank {
            font-family: 'Playfair Display', serif;
            font-size: 13.5px;
            font-style: italic;
            color: rgba(255,255,255,.85);
        }

        /* Print */
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .paper { box-shadow: none; max-width: 100%; border-radius: 0; }
            .header-band, .items-table thead tr, .footer-band {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar">
    <button class="tb-btn primary" onclick="window.print()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="6 9 6 2 18 2 18 9"/>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
            <rect x="6" y="14" width="12" height="8"/>
        </svg>
        Print / Save PDF
    </button>
    <button class="tb-btn ghost" onclick="window.history.back()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Back to Sales
    </button>
</div>

<div class="paper">
  <div class="paper-inner">

    <?php if ($inv['status'] === 'paid'): ?>
    <div class="watermark">PAID</div>
    <?php endif; ?>

    <!-- Header Band -->
    <div class="header-band">
        <div>
            <div class="brand-name">Rifat Enterprise</div>
            <div class="brand-tagline">Building Materials Supplier</div>
            <div class="brand-contact">
                Khodvadri, Gariyadhar, Bhavnagar, Gujarat, India<br>
                altafnaya55@@gmail.com<br>
                
            </div>
        </div>
        <div class="inv-title-block">
            <div class="inv-word">Invoice</div>
            <div class="inv-number"><?php echo $inv_number; ?></div>
            <div>
                <span class="status-chip <?php echo $inv['status'] === 'paid' ? 'chip-paid' : 'chip-pending'; ?>">
                    <?php echo $inv['status'] === 'paid' ? '✓ Paid' : '⏳ Payment Pending'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Meta Strip -->
    <div class="meta-strip">
        <div class="meta-cell">
            <div class="meta-label">Invoice Date</div>
            <div class="meta-value"><?php echo date('d M Y', strtotime($inv['date'])); ?></div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Due Date</div>
            <div class="meta-value"><?php echo date('d M Y', strtotime($due_date)); ?></div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Reference No.</div>
            <div class="meta-value"><?php echo $inv_number; ?></div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Sale ID</div>
            <div class="meta-value">#<?php echo str_pad($inv['id'], 5, '0', STR_PAD_LEFT); ?></div>
        </div>
    </div>

    <div class="body-pad">

        <div class="bill-section">
            <div style="flex:1;">
                <div class="bill-label">Bill To</div>
                <div class="bill-name"><?php echo htmlspecialchars($inv['customer']); ?></div>
                <div class="bill-sub">Customer</div>
            </div>
            <div style="flex:1; text-align:right;">
                <div class="bill-label" style="justify-content:flex-end; flex-direction:row-reverse;">From</div>
                <div class="bill-name">Rifat Enterprise</div>
                <div class="bill-sub">Rajkot, Gujarat, India</div>
            </div>
        </div>

        <div class="items-wrap">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:42%;">Description</th>
                        <th style="width:14%;">Qty</th>
                        <th style="width:22%;">Unit Price</th>
                        <th style="width:22%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="product-name"><?php echo htmlspecialchars($inv['product_name']); ?></div>
                            <div class="product-desc">Building material — Rifat Enterprise</div>
                        </td>
                        <td><?php echo number_format($inv['qty']); ?> units</td>
                        <td>₹<?php echo number_format($unit_price, 2); ?></td>
                        <td>₹<?php echo number_format($subtotal, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="totals-outer">
            <div class="totals-inner">
                <div class="t-row">
                    <span>Subtotal</span>
                    <span class="t-val">₹<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <?php if ($tax_rate > 0): ?>
                <div class="t-row">
                    <span>GST (<?php echo $tax_rate; ?>%)</span>
                    <span class="t-val">₹<?php echo number_format($tax_amount, 2); ?></span>
                </div>
                <?php else: ?>
                <div class="t-row">
                    <span style="color:var(--ink-faint);">Tax / GST</span>
                    <span style="color:var(--ink-faint);">—</span>
                </div>
                <?php endif; ?>
                <div class="t-row grand">
                    <span>Total Due</span>
                    <span class="t-val">₹<?php echo number_format($grand_total, 2); ?></span>
                </div>
            </div>
        </div>

    </div><!-- /body-pad -->

    <!-- Notes, Terms, Signature -->
    <div class="bottom-strip">
        
        <div class="sig-block">
            <div class="sig-line">
                <div class="s-title">Authorised Signatory</div>
                <div class="s-name">Rifat Enterprise</div>
            </div>
        </div>
    </div>

    <!-- Footer Band -->
    <div class="footer-band">
        <span>Generated: <?php echo date('d M Y, H:i'); ?></span>
        <span class="thank">Thank you for your business!</span>
        <span><?php echo $inv_number; ?></span>
    </div>

  </div><!-- /paper-inner -->
</div><!-- /paper -->

</body>
</html>
<?php
        exit();
    }
}
/* ═══════════════════════════════════════════════
   END INVOICE
═══════════════════════════════════════════════ */


if (isset($_POST['add_sale'])) {
    $customer   = mysqli_real_escape_string($conn, trim($_POST['customer']));
    $product_id = (int)$_POST['product_id'];
    $qty        = (int)$_POST['qty'];
    $amount     = (float)$_POST['amount'];
    $date       = date("Y-m-d");

    if (in_array($_POST['status'], ['paid', 'pending'])) {
        $status = $_POST['status'];
    } else {
        $status = 'pending';
    }

    $check = mysqli_query($conn, "SELECT current_stock FROM production_item WHERE id = $product_id");
    $stock_data = mysqli_fetch_assoc($check);

    if (!$stock_data) {
        echo "<script>alert('Invalid product selected!');</script>";
    } else if ($qty > $stock_data['current_stock']) {
        echo "<script>alert('Not enough stock! Available: " . $stock_data['current_stock'] . "');</script>";
    } else {
        mysqli_query($conn, "INSERT INTO sales (customer, product_id, qty, amount, status, date) VALUES ('$customer', '$product_id', '$qty', '$amount', '$status', '$date')");
        $new_stock = $stock_data['current_stock'] - $qty;
        mysqli_query($conn, "UPDATE production_item SET current_stock = $new_stock WHERE id = $product_id");
        echo "<script>alert('Sale Added Successfully'); window.location.href='sales_management.php';</script>";
    }
}

if (isset($_POST['edit_sale'])) {
    $sale_id    = (int)$_POST['sale_id'];
    $customer   = mysqli_real_escape_string($conn, trim($_POST['customer']));
    $product_id = (int)$_POST['product_id'];
    $qty        = (int)$_POST['qty'];
    $amount     = (float)$_POST['amount'];

    if (in_array($_POST['status'], ['paid', 'pending'])) {
        $status = $_POST['status'];
    } else {
        $status = 'pending';
    }

    $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM sales WHERE id = $sale_id"));

    if ($old) {
        mysqli_query($conn, "UPDATE production_item SET current_stock = current_stock + {$old['qty']} WHERE id = {$old['product_id']}");

        $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT current_stock FROM production_item WHERE id = $product_id"));

        if ($qty > $check['current_stock']) {
            mysqli_query($conn, "UPDATE production_item SET current_stock = current_stock - {$old['qty']} WHERE id = {$old['product_id']}");
            echo "<script>alert('Not enough stock! Available: " . $check['current_stock'] . "');</script>";
        } else {
            mysqli_query($conn, "UPDATE sales SET customer='$customer', product_id=$product_id, qty=$qty, amount=$amount, status='$status' WHERE id=$sale_id");
            mysqli_query($conn, "UPDATE production_item SET current_stock = current_stock - $qty WHERE id = $product_id");
            echo "<script>alert('Sale Updated Successfully'); window.location.href='sales_management.php';</script>";
        }
    }
}

if (isset($_GET['delete_sale'])) {
    $sale_id = (int)$_GET['delete_sale'];
    $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM sales WHERE id = $sale_id"));
    if ($old) {
        mysqli_query($conn, "UPDATE production_item SET current_stock = current_stock + {$old['qty']} WHERE id = {$old['product_id']}");
        mysqli_query($conn, "DELETE FROM sales WHERE id = $sale_id");
    }
    header("Location: sales_management.php");
    exit();
}

if (isset($_GET['toggle_status'])) {
    $sale_id = (int)$_GET['toggle_status'];
    $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM sales WHERE id = $sale_id"));
    if ($check) {
        if ($check['status'] == 'pending') {
            $new_status = 'paid';
        } else {
            $new_status = 'pending';
        }
        mysqli_query($conn, "UPDATE sales SET status = '$new_status' WHERE id = $sale_id");
    }
    header("Location: sales_management.php");
    exit();
}

if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_export_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Customer', 'Product', 'Quantity', 'Amount (INR)', 'Status', 'Date']);
    $rows = mysqli_query($conn, "SELECT pe.*, pi.name FROM sales pe JOIN production_item pi ON pe.product_id = pi.id ORDER BY pe.date DESC");
    while ($row = mysqli_fetch_assoc($rows)) {
        fputcsv($out, [$row['id'], $row['customer'], $row['name'], $row['qty'], $row['amount'], $row['status'], $row['date']]);
    }
    fclose($out);
    exit();
}

$stats_result = mysqli_query($conn, "SELECT SUM(amount) AS total_amount, SUM(qty) AS total_qty FROM sales WHERE MONTH(date) = MONTH(NOW()) AND YEAR(date) = YEAR(NOW())");
$stats        = mysqli_fetch_assoc($stats_result);
$total_amount = isset($stats['total_amount']) ? $stats['total_amount'] : 0;
$sold         = isset($stats['total_qty'])    ? $stats['total_qty']    : 0;

$pending_result = mysqli_query($conn, "SELECT SUM(amount) AS pending FROM sales WHERE status = 'pending'");
$pending_data   = mysqli_fetch_assoc($pending_result);
$pending_amount = isset($pending_data['pending']) ? $pending_data['pending'] : 0;

$search_customer = isset($_GET['s_customer']) ? mysqli_real_escape_string($conn, trim($_GET['s_customer'])) : '';
$search_product  = isset($_GET['s_product'])  ? (int)$_GET['s_product']  : 0;
$search_status   = isset($_GET['s_status'])   ? $_GET['s_status']        : '';
$search_from     = isset($_GET['s_from'])     ? $_GET['s_from']          : '';
$search_to       = isset($_GET['s_to'])       ? $_GET['s_to']            : '';

$where = "WHERE 1=1";
if ($search_customer) $where .= " AND pe.customer LIKE '%$search_customer%'";
if ($search_product)  $where .= " AND pe.product_id = $search_product";
if ($search_status && in_array($search_status, ['paid', 'pending'])) $where .= " AND pe.status = '$search_status'";
if ($search_from) $where .= " AND pe.date >= '" . mysqli_real_escape_string($conn, $search_from) . "'";
if ($search_to)   $where .= " AND pe.date <= '" . mysqli_real_escape_string($conn, $search_to) . "'";

$per_page    = 15;
$page        = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset      = ($page - 1) * $per_page;

$count_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM sales pe $where");
$count_data   = mysqli_fetch_assoc($count_result);
$total_rows   = $count_data['total'];
$total_pages  = ceil($total_rows / $per_page);

$sales_result = mysqli_query($conn, "SELECT pe.*, pi.name FROM sales pe JOIN production_item pi ON pe.product_id = pi.id $where ORDER BY pe.date DESC LIMIT $per_page OFFSET $offset");
$data         = mysqli_fetch_all($sales_result, MYSQLI_ASSOC);

$summary_result = mysqli_query($conn, "SELECT SUM(CASE WHEN product_id = 1 THEN qty ELSE 0 END) AS qty_bricks, SUM(CASE WHEN product_id = 2 THEN qty ELSE 0 END) AS qty_cement, SUM(CASE WHEN product_id = 1 THEN amount ELSE 0 END) AS rev_bricks, SUM(CASE WHEN product_id = 2 THEN amount ELSE 0 END) AS rev_cement FROM sales");
$summary        = mysqli_fetch_assoc($summary_result);

$products_list = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM production_item"), MYSQLI_ASSOC);
$stock_levels  = get_stock_levels($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rifat Enterprise | Sales Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/dashboard/css/style.css">
    <style>
        .filter-bar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:18px; align-items:flex-end; }
        .filter-bar .fi { display:flex; flex-direction:column; gap:4px; }
        .filter-bar label { font-size:11px; color:var(--text-muted, #94a3b8); text-transform:uppercase; letter-spacing:.05em; }
        .filter-bar input,
        .filter-bar select { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); color:#e2e8f0; border-radius:8px; padding:7px 12px; font-size:13px; min-width:130px; }
        .filter-bar input:focus,
        .filter-bar select:focus { outline:none; border-color:#7c3aed; }
        .filter-btn { padding:8px 18px; border-radius:8px; border:none; cursor:pointer; font-weight:600; font-size:13px; align-self:flex-end; }
        .btn-filter { background:linear-gradient(135deg,#7c3aed,#4f46e5); color:#fff; }
        .btn-reset  { background:rgba(255,255,255,0.08); color:#e2e8f0; }

        .pagination { display:flex; gap:6px; justify-content:center; margin-top:20px; flex-wrap:wrap; }
        .pagination a, .pagination span { padding:6px 14px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; border:1px solid rgba(255,255,255,0.1); color:#e2e8f0; }
        .pagination a:hover      { background:rgba(124,58,237,0.2); }
        .pagination span.current { background:linear-gradient(135deg,#7c3aed,#4f46e5); border-color:transparent; }

        .action-group { display:flex; gap:6px; flex-wrap:wrap; }
        .btn-sm { padding:5px 11px; border-radius:7px; font-size:11px; font-weight:600; border:none; cursor:pointer; text-decoration:none; display:inline-block; white-space:nowrap; }
        .btn-delete  { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; }
        .btn-edit    { background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; }
        .btn-invoice { background:linear-gradient(135deg,#06b6d4,#0891b2); color:#fff; }

        .stock-bar-wrap { margin-top:4px; }
        .stock-bar-bg   { background:rgba(255,255,255,0.08); border-radius:4px; height:6px; margin-top:4px; }
        .stock-bar-fill { height:6px; border-radius:4px; }

        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:1000; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:#1a1a2e; border:1px solid rgba(255,255,255,.1); border-radius:16px; padding:32px; width:90%; max-width:560px; }
        .modal-box h3 { margin-bottom:20px; font-size:18px; }
        .modal-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .modal-grid label { font-size:13px; color:#94a3b8; display:block; margin-bottom:4px; }
        .modal-grid input,
        .modal-grid select { width:100%; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); color:#e2e8f0; border-radius:8px; padding:8px 12px; font-size:14px; }
        .modal-actions { display:flex; gap:10px; margin-top:20px; }
    </style>
</head>

<body>
<div class="container">

    <?php include "sidebar.php"; ?>

    <main class="main">

        <div class="header">
            <h1>Sales Management</h1>
            <div class="profile" onclick="window.location.href='/dashboard/profile.php'" style="cursor:pointer;">
                <div class="avatar"></div>
                <span><?php echo $_SESSION["username"]; ?></span>
            </div>
        </div>

        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon used">💰</div>
                    <div>Total Sales (Month)</div>
                </div>
                <div class="stat-number">₹<?php echo convert_money($total_amount); ?></div>
                <div class="stat-label">Current month</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon recent">📦</div>
                    <div>Units Sold</div>
                </div>
                <div class="stat-number"><?php echo $sold; ?></div>
                <div class="stat-label">Bricks / Blocks / Tiles</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon stats">📉</div>
                    <div>Pending Payments</div>
                </div>
                <div class="stat-number">₹<?php echo convert_money($pending_amount); ?></div>
                <div class="stat-label">Outstanding</div>
            </div>

        </section>

        <section class="content-grid">

            <div class="recent-section">
                <div class="section-header">
                    <h3>Sales Records <span style="font-size:13px; color:#94a3b8; font-weight:400; margin-left:8px;">(<?php echo $total_rows; ?> records)</span></h3>
                    <div style="display:flex; gap:10px;">
                        <a href="sales_management.php?export_csv=1&<?php echo http_build_query(array_filter(['s_customer' => $search_customer, 's_product' => $search_product, 's_status' => $search_status, 's_from' => $search_from, 's_to' => $search_to])); ?>"
                           class="upload-btn" style="background:linear-gradient(135deg,#10b981,#059669); text-decoration:none;">
                            📥 Export CSV
                        </a>
                        <button class="upload-btn" onclick="toggleProductionForm()">➕ New Sale</button>
                    </div>
                </div>

                <div id="Form" style="display:none; margin-bottom:20px;">
                    <div class="design-card">
                        <form method="POST">
                            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
                                <div>
                                    <label>Customer Name</label>
                                    <input type="text" name="customer"   oninput="this.value=this.value.replace(/[^A-Za-z ]/g,'')"  class="input" placeholder="Enter customer name" required>
                                </div>
                                <div>
                                    <label>Product</label>
                                    <select name="product_id" class="input" required>
                                        <?php
                                        for ($i = 0; $i < count($products_list); $i++) {
                                            $row = $products_list[$i];
                                            echo '<option value="' . (int)$row['id'] . '">' . $row['name'] . ' (Stock: ' . (int)$row['current_stock'] . ')</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div>
                                    <label>Quantity</label>
                                    <input type="number" name="qty" class="input" placeholder="0" min="1" required>
                                </div>
                                <div>
                                    <label>Total Amount (₹)</label>
                                    <input type="number" name="amount" class="input" placeholder="0.00" min="0" step="0.01" required>
                                </div>
                                <div>
                                    <label>Payment Status</label>
                                    <select name="status" class="input">
                                        <option value="paid">Paid</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                </div>
                            </div>
                            <div style="margin-top:20px; display:flex; gap:10px;">
                                <button type="submit" name="add_sale" class="upload-btn">💾 Save Sale</button>
                                <button type="button" onclick="toggleProductionForm()" class="upload-btn" style="background:rgba(255,255,255,0.1); box-shadow:none;">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <form method="GET" action="sales_management.php">
                    <div class="filter-bar">
                        <div class="fi">
                            <label>Customer</label>
                            <input type="text" name="s_customer" placeholder="Search name…" value="<?php echo $search_customer; ?>">
                        </div>
                        <div class="fi">
                            <label>Product</label>
                            <select name="s_product">
                                <option value="">All Products</option>
                                <?php
                                for ($i = 0; $i < count($products_list); $i++) {
                                    $row      = $products_list[$i];
                                    $selected = ($search_product == $row['id']) ? 'selected' : '';
                                    echo '<option value="' . (int)$row['id'] . '" ' . $selected . '>' . $row['name'] . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="fi">
                            <label>Status</label>
                            <select name="s_status">
                                <option value="">All</option>
                                <option value="paid"    <?php echo ($search_status == 'paid')    ? 'selected' : ''; ?>>Paid</option>
                                <option value="pending" <?php echo ($search_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        <div class="fi">
                            <label>From Date</label>
                            <input type="date" name="s_from" value="<?php echo $search_from; ?>">
                        </div>
                        <div class="fi">
                            <label>To Date</label>
                            <input type="date" name="s_to" value="<?php echo $search_to; ?>">
                        </div>
                        <button type="submit" class="filter-btn btn-filter">🔍 Filter</button>
                        <a href="sales_management.php" class="filter-btn btn-reset" style="text-decoration:none; text-align:center;">✖ Reset</a>
                    </div>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($data) == 0) {
                            echo '<tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:30px;">No sales records found.</td></tr>';
                        } else {
                            for ($i = 0; $i < count($data); $i++) {
                                $record = $data[$i];
                                $is_pending = ($record['status'] == 'pending');
                                if ($is_pending) {
                                    $btn_text     = '⏳ Mark Paid';
                                    $btn_style    = 'background:linear-gradient(135deg,#ef4444,#dc2626);';
                                    $status_color = '#f87171';
                                } else {
                                    $btn_text     = '✅ Mark Pending';
                                    $btn_style    = 'background:linear-gradient(135deg,#10b981,#059669);';
                                    $status_color = '#34d399';
                                }
                        ?>
                        <tr>
                            <td><?php echo (int)$record['id']; ?></td>
                            <td><?php echo htmlspecialchars($record['customer']); ?></td>
                            <td><?php echo htmlspecialchars($record['name']); ?></td>
                            <td><?php echo number_format((int)$record['qty']); ?></td>
                            <td>₹<?php echo number_format((float)$record['amount'], 2); ?></td>
                            <td style="color:<?php echo $status_color; ?>; font-weight:600;"><?php echo ucfirst($record['status']); ?></td>
                            <td><?php echo $record['date']; ?></td>
                            <td>
                                <div class="action-group">
                                    <a href="?toggle_status=<?php echo (int)$record['id']; ?>" class="btn-sm" style="<?php echo $btn_style; ?> color:#fff;" onclick="return confirm('Change payment status?')"><?php echo $btn_text; ?></a>
                                    <button class="btn-sm btn-edit" onclick="openEdit(<?php echo json_encode($record); ?>)">✏️ Edit</button>
                                    <a href="?invoice=<?php echo (int)$record['id']; ?>" class="btn-sm btn-invoice" target="_blank">🧾 Invoice</a>
                                    <a href="?delete_sale=<?php echo (int)$record['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this sale? Stock will be restored.')">🗑️ Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1) { ?>
                <div class="pagination">
                    <?php if ($page > 1) { ?>
                        <a href="<?php echo paginate_url(1); ?>">«</a>
                        <a href="<?php echo paginate_url($page - 1); ?>">‹</a>
                    <?php } ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($total_pages, $page + 2);
                    for ($p = $start; $p <= $end; $p++) {
                        if ($p == $page) {
                            echo '<span class="current">' . $p . '</span>';
                        } else {
                            echo '<a href="' . paginate_url($p) . '">' . $p . '</a>';
                        }
                    }
                    ?>
                    <?php if ($page < $total_pages) { ?>
                        <a href="<?php echo paginate_url($page + 1); ?>">›</a>
                        <a href="<?php echo paginate_url($total_pages); ?>">»</a>
                    <?php } ?>
                </div>
                <?php } ?>

            </div>

            <div class="design-card">
                <h3 style="margin-bottom:16px; font-family:'Syne',sans-serif;">Sales Alerts</h3>
                <div class="design-item"><?php echo pending_amount($conn); ?></div>
                <div class="design-item"><?php echo stock($conn); ?></div>
                <div class="design-item"><?php echo demand($conn); ?></div>

                <h3 style="margin:20px 0 12px; font-family:'Syne',sans-serif;">📦 Stock Levels</h3>
                <?php
                $max_stock = 1;
                for ($i = 0; $i < count($stock_levels); $i++) {
                    if ($stock_levels[$i]['current_stock'] > $max_stock) {
                        $max_stock = $stock_levels[$i]['current_stock'];
                    }
                }

                for ($i = 0; $i < count($stock_levels); $i++) {
                    $sl  = $stock_levels[$i];
                    $pct = min(100, round(($sl['current_stock'] / $max_stock) * 100));

                    if ($pct > 50) {
                        $bar_color   = '#10b981';
                        $stock_label = '✅';
                    } else if ($pct > 20) {
                        $bar_color   = '#f59e0b';
                        $stock_label = '⚠️';
                    } else {
                        $bar_color   = '#ef4444';
                        $stock_label = '🔴';
                    }
                ?>
                <div class="design-item stock-bar-wrap">
                    <div style="display:flex; justify-content:space-between; font-size:13px;">
                        <span><?php echo $stock_label . ' ' . $sl['name']; ?></span>
                        <strong style="color:<?php echo $bar_color; ?>"><?php echo number_format($sl['current_stock']); ?> units</strong>
                    </div>
                    <div class="stock-bar-bg">
                        <div class="stock-bar-fill" style="width:<?php echo $pct; ?>%; background:<?php echo $bar_color; ?>;"></div>
                    </div>
                </div>
                <?php } ?>
            </div>

        </section>

        <section class="recent-section" style="margin-top:32px; height:auto;">
            <div class="section-header">
                <h3>Sales Summary</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Total Sold (Units)</th>
                        <th>Revenue</th>
                        <th>Stock Impact</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>🧱 Burnt Clay Bricks</td>
                        <td><?php echo number_format((int)$summary['qty_bricks']); ?></td>
                        <td>₹<?php echo convert_money($summary['rev_bricks']); ?></td>
                        <td>
                            <?php
                            $impact = stock_impact($summary['rev_bricks'], $summary['rev_cement']);
                            $color  = $impact == 'High' ? '#f87171' : ($impact == 'Medium' ? '#fbbf24' : '#34d399');
                            echo "<span style='color:$color; font-weight:600;'>$impact</span>";
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>🏗️ Cement Blocks</td>
                        <td><?php echo number_format((int)$summary['qty_cement']); ?></td>
                        <td>₹<?php echo convert_money($summary['rev_cement']); ?></td>
                        <td>
                            <?php
                            $impact = stock_impact($summary['rev_cement'], $summary['rev_bricks']);
                            $color  = $impact == 'High' ? '#f87171' : ($impact == 'Medium' ? '#fbbf24' : '#34d399');
                            echo "<span style='color:$color; font-weight:600;'>$impact</span>";
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

    </main>

</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3>✏️ Edit Sale</h3>
        <form method="POST">
            <input type="hidden" name="sale_id" id="edit_sale_id">
            <div class="modal-grid">
                <div>
                    <label>Customer Name</label>
                    <input type="text" name="customer" id="edit_customer" required>
                </div>
                <div>
                    <label>Product</label>
                    <select name="product_id" id="edit_product_id" required>
                        <?php
                        for ($i = 0; $i < count($products_list); $i++) {
                            $row = $products_list[$i];
                            echo '<option value="' . (int)$row['id'] . '">' . $row['name'] . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label>Quantity</label>
                    <input type="number" name="qty" id="edit_qty" min="1" required>
                </div>
                <div>
                    <label>Amount (₹)</label>
                    <input type="number" name="amount" id="edit_amount" min="0" step="0.01" required>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="submit" name="edit_sale" class="upload-btn">💾 Update Sale</button>
                <button type="button" onclick="closeEdit()" class="upload-btn" style="background:rgba(255,255,255,0.1); box-shadow:none;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="js/script.js"></script>
<script>
    function toggleProductionForm() {
        var form = document.getElementById('Form');
        if (form.style.display == 'none' || form.style.display == '') {
            form.style.display = 'block';
            form.style.animation = 'form_ani 0.3s ease forwards';
        } else {
            form.style.display = 'none';
        }
    }

    function openEdit(record) {
        document.getElementById('edit_sale_id').value    = record.id;
        document.getElementById('edit_customer').value   = record.customer;
        document.getElementById('edit_product_id').value = record.product_id;
        document.getElementById('edit_qty').value        = record.qty;
        document.getElementById('edit_amount').value     = record.amount;
        document.getElementById('edit_status').value     = record.status;
        document.getElementById('editModal').classList.add('open');
    }

    function closeEdit() {
        document.getElementById('editModal').classList.remove('open');
    }

    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target == this) closeEdit();
    });
</script>

</body>
</html>
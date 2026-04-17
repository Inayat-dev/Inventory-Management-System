<?php
/**
 * update_entry.php
 * Handles updating an existing production entry.
 * Called via POST from the Update modal in production_management.php
 */

include '../config.php';
include "login_check.php";
$page = 'products';
include "access.php";
check_access($page);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: production_management.php");
    exit();
}

// ── 1. Collect & sanitize inputs ─────────────────────────────────────────────
$entry_id  = (int) $_POST['entry_id'];
$item_id   = (int) $_POST['item_id'];
$new_stock = (int) $_POST['new_stock'];
$aggregate = (int) $_POST['aggregate'];
$sand      = (int) $_POST['sand'];
$cement    = (int) $_POST['cement'];
$red_sand  = (int) $_POST['red_sand'];

if ($entry_id <= 0 || $item_id <= 0) {
    header("Location: production_management.php?error=invalid_entry");
    exit();
}

// ── 2. Fetch the OLD entry so we know what was previously consumed ────────────
$stmt = $conn->prepare("SELECT new_stock, aggregate_used, sand_used, cement_used, red_sand_used FROM production_entries WHERE id = ?");
$stmt->bind_param("i", $entry_id);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$old) {
    header("Location: production_management.php?error=entry_not_found");
    exit();
}

$old_new_stock = (int) $old['new_stock'];
$old_aggregate = (int) $old['aggregate_used'];
$old_sand      = (int) $old['sand_used'];
$old_cement    = (int) $old['cement_used'];
$old_red_sand  = (int) $old['red_sand_used'];

// ── 3. Fetch current raw material stocks ─────────────────────────────────────
$query   = "SELECT * FROM raw_material";
$result  = mysqli_query($conn, $query);
$raw_all = mysqli_fetch_all($result);

// Map by name for clarity (adjust indices if your table differs)
// Assumed order: [0]=Cement, [1]=Sand, [2]=Aggregate, [3]=Red Sand
$current_cement    = (int) $raw_all[0][3];
$current_sand      = (int) $raw_all[1][3];
$current_aggregate = (int) $raw_all[2][3];
$current_red_sand  = (int) $raw_all[3][3];

// Effective available = current stock + what the OLD entry consumed
// (because we will "return" the old usage and apply the new usage)
$effective_aggregate = $current_aggregate + $old_aggregate;
$effective_sand      = $current_sand      + $old_sand;
$effective_cement    = $current_cement    + $old_cement;
$effective_red_sand  = $current_red_sand  + $old_red_sand;

// ── 4. Validate new raw material amounts against effective stock ───────────────
if (
    $aggregate > $effective_aggregate ||
    $sand      > $effective_sand      ||
    $cement    > $effective_cement    ||
    $red_sand  > $effective_red_sand
) {
    header("Location: production_management.php?error=insufficient_material");
    exit();
}

// ── 5. Recalculate production_item current_stock ─────────────────────────────
$stmt = $conn->prepare("SELECT current_stock, min_stock FROM production_item WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    header("Location: production_management.php?error=item_not_found");
    exit();
}

// Remove old entry's contribution, add new
$updated_stock = ((int) $item['current_stock']) - $old_new_stock + $new_stock;
$status        = ($updated_stock < (int) $item['min_stock']) ? "Low Stock" : "Available stock";

// ── 6. Update production_entries row ─────────────────────────────────────────
$stmt = $conn->prepare("UPDATE production_entries 
    SET new_stock = ?, Available = ?, aggregate_used = ?, sand_used = ?, cement_used = ?, red_sand_used = ?, status = ?
    WHERE id = ?");
$stmt->bind_param("iiiiiisi", $new_stock, $updated_stock, $aggregate, $sand, $cement, $red_sand, $status, $entry_id);
$stmt->execute();
$stmt->close();

// ── 7. Update production_item current_stock ───────────────────────────────────
$stmt = $conn->prepare("UPDATE production_item SET current_stock = ? WHERE id = ?");
$stmt->bind_param("ii", $updated_stock, $item_id);
$stmt->execute();
$stmt->close();

// ── 8. Update raw_material stocks (return old, deduct new) ───────────────────
$materials = [
    ['Cement',    $cement,    $old_cement],
    ['Sand',      $sand,      $old_sand],
    ['Aggregate', $aggregate, $old_aggregate],
    ['Red Sand',  $red_sand,  $old_red_sand],
];

foreach ($materials as [$name, $new_used, $old_used]) {
    // Fetch current value fresh to avoid stale reads inside loop
    $stmt = $conn->prepare("SELECT current_stock FROM raw_material WHERE material_name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $corrected = ((int) $row['current_stock']) + $old_used - $new_used;

    $stmt = $conn->prepare("UPDATE raw_material SET current_stock = ? WHERE material_name = ?");
    $stmt->bind_param("is", $corrected, $name);
    $stmt->execute();
    $stmt->close();
}

// ── 9. Redirect back with success message ────────────────────────────────────
header("Location: production_management.php?success=1");
exit();

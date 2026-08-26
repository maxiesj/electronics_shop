<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__FILE__) . '/../session_auth.php';

if(session_status()===PHP_SESSION_NONE){session_start();}
include_once file_exists(__DIR__.'/../db.php')?__DIR__.'/../db.php':'../db.php';

// FIXED SECURITY CHECK: Allows both Super Admin and regular Admins automatically while dodging header warnings
if (!verifyWorkspaceClearance('add_product.php')) {
    echo "<script>window.location.href = '../login.php?msg=err_unauthorized_access';</script>";
    exit();
}

$error = ""; $success_msg = "";

// 1. EXTRACT TARGET ROW IDENTIFIER SECURELY AHEAD OF MUTATION BLOCKS
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);

// --- SECURE ROUTING SAFETY GUARD ---
// If the page is requested without a product ID, show a guide instead of a system error banner
if ($id <= 0 && ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['process_edit_product']))) {
    echo '
    <div style="background:#f8fafc; border:1px dashed #cbd5e1; color:#64748b; padding:30px; border-radius:8px; text-align:center; font-family:sans-serif;">
        <span style="font-size:24px; display:block; margin-bottom:8px;">📦 Catalog Modification Center</span>
        Please browse the <a href="#" class="ajax-link" data-target="warehouse.php" style="color:#6366f1; font-weight:bold; text-decoration:none;">Active Warehouse Registry</a> and click the edit icon on any item entry to modify its properties.
    </div>';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_edit_product'])) {
    $pid = intval($_POST['product_id']);
    $title = trim($_POST['product_title']);
    $sku = trim($_POST['sku_code']);
    $cat = intval($_POST['category_id']);
    $brand = intval($_POST['brand_id']);
    $price = floatval($_POST['price']);
    $cost = floatval($_POST['cost_price']);
    $stock = intval($_POST['stock']);
    
    if ($pid > 0 && $cost >= 0.01 && $cost <= 99999999.99) {
        $stmt = $conn->prepare("UPDATE products SET product_name=?, sku=?, category_id=?, brand_id=?, price=?, cost_price=?, stock_quantity=? WHERE id=?");
        $stmt->bind_param("ssiiddii", $title, $sku, $cat, $brand, $price, $cost, $stock, $pid);
        
        if ($stmt->execute()) {
            
            // Log the inventory edit transaction event details cleanly into the staff logs audit trails
            $log_details = "Product #{$pid} updated. SKU {$sku}; selling price KES " . number_format($price, 2, '.', '') . "; buying cost KES " . number_format($cost, 2, '.', '') . "; stock {$stock}.";
            $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Inventory Update', ?)");
            if ($log_stmt) {
                $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
                $log_stmt->execute();
                $log_stmt->close();
            }

            $success_msg = "Product modifications successfully written into the active catalog database registry.";
            $id = $pid;
        } else {
            $error = "Database execution fault: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Enter a valid product and a unit buying cost of at least KES 0.01.";
    }
}

// 2. COMPILE ACTIVE DATA PROFILE DIRECTLY FROM DISK PER RENDER CYCLE
$item = null;
if ($id > 0) {
    // FIXED STRUCTURAL ADJUSTMENT: Sanitizes variables to block raw URL parameter SQL Injection holes
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) { $item = $res->fetch_assoc(); }
    $stmt->close();
}
?>

<!-- Component Notifications Area -->
<?php if(!empty($error)): ?>
    <div style="background:#fee2e2; color:#ef4444; padding:12px; border-radius:6px; margin-bottom:15px; font-family:sans-serif;">⚠️ <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if(!empty($success_msg)): ?>
    <div style="background:#e6fcf5; color:#0ca678; padding:12px; border-radius:6px; margin-bottom:15px; font-family:sans-serif; font-weight:bold;">✓ <?php echo htmlspecialchars($success_msg); ?></div>
<?php endif; ?>

<?php if($item): ?>
<div class="card" style="background:white; padding:25px; border-radius:8px; border:1px solid #e2e8f0; width:100%; box-sizing:border-box; font-family:sans-serif;">
    <h3 style="margin-top:0; color:#0f172a;">📝 Edit Product: <?php echo htmlspecialchars($item['product_name']); ?> (ID: #<?php echo $item['id']; ?>)</h3>
    
    <form id="ajax-edit-product-form" method="post" action="edit_product.php?id=<?php echo $item['id']; ?>" style="display:flex; flex-direction:column; gap:12px;">
        <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
        <input type="hidden" name="process_edit_product" value="1">
        
        <div style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-weight:bold; font-size:13px; color:#475569;">Product Name *</label>
            <input type="text" name="product_title" value="<?php echo htmlspecialchars($item['product_name']); ?>" style="padding:10px; border-radius:6px; border:1px solid #cbd5e1;" required>
        </div>

        <div style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-weight:bold; font-size:13px; color:#475569;">SKU (Stock Keeping Unit) *</label>
            <input type="text" name="sku_code" value="<?php echo htmlspecialchars($item['sku']); ?>" style="padding:10px; border-radius:6px; border:1px solid #cbd5e1;" required>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-weight:bold; font-size:13px; color:#475569;">Category *</label>
                <select name="category_id" style="padding:10px; border-radius:6px; border:1px solid #cbd5e1;">
                    <?php 
                    $cats = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name ASC");
                    while($c = $cats->fetch_assoc()): 
                        $sel = ($c['id'] == $item['category_id']) ? "selected" : "";
                    ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $sel; ?>><?php echo strtoupper($c['category_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-weight:bold; font-size:13px; color:#475569;">Brand *</label>
                <select name="brand_id" style="padding:10px; border-radius:6px; border:1px solid #cbd5e1;">
                    <?php 
                    $brands = $conn->query("SELECT id, brand_name FROM brands ORDER BY brand_name ASC");
                    while($b = $brands->fetch_assoc()): 
                        $sel = ($b['id'] == $item['brand_id']) ? "selected" : "";
                    ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo $sel; ?>><?php echo strtoupper($b['brand_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>


        <div style="display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px;">
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-weight:bold; font-size:13px; color:#475569;">Price (KSH) *</label>
                <input type="number" name="price" step="0.01" min="0" value="<?php echo floatval($item['price']); ?>" style="padding:10px; border-radius:6px; border:1px solid #cbd5e1;" required>
            </div>
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-weight:bold; font-size:13px; color:#475569;">Buying Cost (KES) *</label>
                <input type="number" name="cost_price" step="0.01" min="0.01" max="99999999.99" value="<?php echo htmlspecialchars($item['cost_price'] ?? ''); ?>" style="padding:10px; border-radius:6px; border:1px solid #cbd5e1;" required>
            </div>
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-weight:bold; font-size:13px; color:#475569;">Stock Quantity *</label>
                <input type="number" name="stock" min="0" value="<?php echo intval($item['stock_quantity']); ?>" style="padding:10px; border-radius:6px; border:1px solid #cbd5e1;" required>
            </div>
        </div>

        <div style="display:flex; gap:8px; margin-top:8px;">
            <button type="submit" style="background:#6366f1; color:#fff; border:none; padding:12px; font-weight:bold; border-radius:6px; cursor:pointer; flex:1;">Update Catalog Entry</button>
            <a href="#" class="ajax-link" data-target="warehouse.php" style="background:#e2e8f0; color:#334155; padding:12px; border-radius:6px; text-decoration:none; font-size:13px; text-align:center; font-weight:bold; width:100px; display:flex; align-items:center; justify-content:center;">Cancel</a>
        </div>
    </form>
</div>
<?php else: ?>
    <div style="background:#fee2e2; color:#ef4444; padding:20px; border-radius:8px; text-align:center; font-family:sans-serif;">⚠️ Error: Target item not found in catalog logs.</div>
<?php endif; ?>

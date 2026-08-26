<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__FILE__) . '/../session_auth.php';

// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_path = dirname(__FILE__) . '/../db.php';
include_once file_exists($db_path) ? $db_path : '../db.php';

// UPDATED SECURITY GATE: Allows both Super Admin and regular Admins automatically 
if (!verifyWorkspaceClearance('add_product.php')) {
    header("Location: ../login.php?msg=err_unauthorized_access"); 
    exit();
}

$error = ""; 
$success_msg = "";
if (empty($_SESSION['add_product_csrf'])) { $_SESSION['add_product_csrf'] = bin2hex(random_bytes(32)); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_new_product'])) {
    if (!hash_equals($_SESSION['add_product_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'This form expired. Refresh the page and try again.';
    }
    $p_title  = trim($_POST['product_title']);
    $p_sku    = strtoupper(trim($_POST['sku_code']));
    $p_cat    = intval($_POST['category_id']);
    $p_brand  = intval($_POST['brand_id']);
    $p_email  = trim($_POST['supplier_email']);
    $p_desc   = trim($_POST['description']);
    $p_price  = floatval($_POST['price']);
    $p_cost   = floatval($_POST['cost_price']);
    $p_stock  = intval($_POST['stock']);
    $img_name = null;

    if ($error === "" && $p_title !== "" && strlen($p_title) <= 255 && preg_match('/^[A-Z0-9][A-Z0-9._-]{1,99}$/', $p_sku) && filter_var($p_email, FILTER_VALIDATE_EMAIL) && $p_price >= 0 && $p_price <= 99999999.99 && $p_cost >= 0.01 && $p_cost <= 99999999.99 && $p_stock >= 0 && $p_stock <= 1000000) {
        // 1. Check for Duplicate SKU
        $check_sku = $conn->prepare("SELECT id FROM products WHERE UPPER(TRIM(sku)) = ? LIMIT 1");
        $check_sku->bind_param("s", $p_sku);
        $check_sku->execute();
        $sku_result = $check_sku->get_result();
        
        if ($sku_result && $sku_result->num_rows > 0) {
            $error = "E-Commerce Conflict Alert: SKU [ " . $p_sku . " ] already exists in your warehouse table database registry.";
        } else {
            // 2. Process Native Image Upload
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp  = $_FILES['product_image']['tmp_name'];
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file_tmp);
                $allowed_images = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if ($_FILES['product_image']['size'] > 5 * 1024 * 1024 || !isset($allowed_images[$mime]) || @getimagesize($file_tmp) === false) {
                    $error = 'Choose a valid JPG, PNG or WebP image no larger than 5 MB.';
                }
                $file_name = 'product_' . bin2hex(random_bytes(12)) . '.' . ($allowed_images[$mime] ?? 'invalid');
                $target_dir = "../uploads/";
                
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                
                if ($error === "" && move_uploaded_file($file_tmp, $target_dir . $file_name)) {
                    $img_name = $file_name;
                }
            }

            // Store only after all upload and form validation has passed.
            $stmt = $conn->prepare("INSERT INTO products (category_id, brand_id, supplier_email, product_name, sku, price, cost_price, stock_quantity, image, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssddiss", $p_cat, $p_brand, $p_email, $p_title, $p_sku, $p_price, $p_cost, $p_stock, $img_name, $p_desc);
            
            if ($error === "" && $stmt->execute()) {
                $product_id = $stmt->insert_id;
                $user_id = (int)($_SESSION['user_id'] ?? 0);
                $staff_name = (string)($_SESSION['fullname'] ?? 'System operator');
                $details = "Product #{$product_id} created. SKU {$p_sku}; selling price KES " . number_format($p_price, 2, '.', '') . "; buying cost KES " . number_format($p_cost, 2, '.', '') . "; opening stock {$p_stock}.";
                $log = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Inventory Create', ?)");
                if ($log) { $log->bind_param("iss", $user_id, $staff_name, $details); $log->execute(); $log->close(); }
                $_SESSION['add_product_csrf'] = bin2hex(random_bytes(32));
                $success_msg = "Product added successfully to the catalog.";
            } else {
                if ($img_name && is_file(__DIR__ . '/../uploads/' . $img_name)) { unlink(__DIR__ . '/../uploads/' . $img_name); }
                if ($error === '') {
                    error_log('Add product failed: ' . $stmt->error);
                    $error = 'The product could not be saved. Please try again.';
                }
            }
            $stmt->close();
        }
        $check_sku->close();
    } else {
        if ($error === "") { $error = "Please complete every required field and check the highlighted values."; }
    }
}
?>

<!-- Add Product workspace -->
<style>
.product-entry{max-width:980px;margin:0 auto;color:#172033;font-family:inherit}.product-entry *{box-sizing:border-box}
.product-entry__nav{display:flex;align-items:center;margin-bottom:14px}.product-entry__back{display:inline-flex;align-items:center;gap:8px;padding:9px 13px;border:1px solid #cbd7e6;border-radius:9px;background:#fff;color:#34445d;text-decoration:none;font-weight:700}
.product-entry__card{border:1px solid #dbe5ef;border-radius:16px;background:#fff;box-shadow:0 12px 32px rgba(31,55,90,.07);overflow:hidden}
.product-entry__header{display:flex;gap:14px;align-items:center;padding:23px 25px;border-bottom:1px solid #e7edf4;background:linear-gradient(135deg,#fff,#f4f8ff)}
.product-entry__icon{width:46px;height:46px;display:grid;place-items:center;border-radius:13px;background:#e9f1ff;color:#2f6fed;font-size:22px}
.product-entry__header h2{margin:0 0 5px;font-size:24px}.product-entry__header p{margin:0;color:#6c7e98;font-size:14px}
.product-entry__body{padding:24px}.product-section{padding:0 0 23px;margin-bottom:23px;border-bottom:1px solid #e7edf4}.product-section:last-of-type{margin-bottom:0;border:0}
.product-section__title{display:flex;align-items:center;gap:9px;margin:0 0 16px;font-size:15px}.product-section__number{width:25px;height:25px;display:grid;place-items:center;border-radius:50%;background:#edf3ff;color:#2f6fed;font-size:12px}
.product-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.product-field{display:flex;flex-direction:column;gap:7px}.product-field.full{grid-column:1/-1}
.product-field label{font-size:12px;font-weight:800;letter-spacing:.03em;text-transform:uppercase;color:#52627a}.product-field input,.product-field select,.product-field textarea{width:100%;padding:12px 13px;border:1px solid #cbd7e6;border-radius:9px;background:#fff;color:#172033;font:inherit;outline:none;transition:.2s}
.product-field input:focus,.product-field select:focus,.product-field textarea:focus{border-color:#2f6fed;box-shadow:0 0 0 3px rgba(47,111,237,.12)}.product-field__help{font-size:12px;color:#8190a6}
.product-upload{display:grid;grid-template-columns:105px 1fr;gap:16px;align-items:center;padding:14px;border:1px dashed #b9c7d9;border-radius:11px;background:#f8fafc}
.product-upload__preview{width:105px;height:84px;display:grid;place-items:center;overflow:hidden;border-radius:9px;background:#e8eef6;color:#74839a;font-size:12px;text-align:center}.product-upload__preview img{width:100%;height:100%;object-fit:cover}
.product-upload__button{display:inline-flex;padding:10px 13px;border:1px solid #bdcbe0;border-radius:8px;background:#fff;color:#33445d;font-weight:750;cursor:pointer}.product-upload input{position:absolute;width:1px;height:1px;opacity:0}
.product-upload__name{display:block;margin-top:8px;color:#7a899f;font-size:12px}
.product-entry__actions{position:sticky;bottom:10px;display:flex;justify-content:flex-end;padding:16px 24px;border-top:1px solid #e7edf4;background:rgba(255,255,255,.96);backdrop-filter:blur(8px)}
.product-entry__save{min-width:210px;padding:13px 20px;border:0;border-radius:9px;background:#10a66a;color:#fff;font-weight:800;cursor:pointer;box-shadow:0 7px 18px rgba(16,166,106,.22)}.product-entry__save:disabled{opacity:.7;cursor:wait}
.product-entry__spinner{display:none;width:16px;height:16px;margin-right:8px;border:2px solid rgba(255,255,255,.45);border-top-color:#fff;border-radius:50%;vertical-align:-3px;animation:entrySpin .7s linear infinite}.is-saving .product-entry__spinner{display:inline-block}
.product-entry__notice{max-width:980px;margin:0 auto 14px;padding:13px 15px;border-radius:10px;font-weight:650}.product-entry__notice.error{color:#b42318;background:#fff1f0;border:1px solid #fecaca}.product-entry__notice.success{color:#087a43;background:#ecfdf3;border:1px solid #bbf7d0}
.product-field .field-invalid{border-color:#dc3545!important;background:#fffafa;box-shadow:0 0 0 3px rgba(220,53,69,.1)!important}.product-field__error{display:block;margin-top:1px;color:#c92a2a;font-size:12px;font-weight:700}@keyframes entrySpin{to{transform:rotate(360deg)}}@media(max-width:700px){.product-entry__header,.product-entry__body{padding:18px}.product-grid{grid-template-columns:1fr}.product-field.full{grid-column:auto}.product-upload{grid-template-columns:1fr}.product-entry__actions{padding:14px 18px}.product-entry__save{width:100%}}
</style>
<?php if(!empty($error)): ?><div class="product-entry__notice error" role="alert">⚠ <?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if(!empty($success_msg)): ?><div class="product-entry__notice success" role="status">✓ <?=htmlspecialchars($success_msg)?></div><?php endif; ?>
<section class="product-entry">
  <nav class="product-entry__nav"><a href="warehouse.php" class="product-entry__back ajax-link" data-target="warehouse.php">← Back to inventory</a></nav>
  <div class="product-entry__card">
    <header class="product-entry__header"><div class="product-entry__icon" aria-hidden="true">▦</div><div><h2>Add Product</h2><p>Add a new item to the store catalog and opening inventory.</p></div></header>
    <form id="ajax-restock-form" method="post" action="add_product.php" enctype="multipart/form-data">
      <input type="hidden" name="submit_new_product" value="1"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['add_product_csrf'])?>">
      <div class="product-entry__body">
        <section class="product-section"><h3 class="product-section__title"><span class="product-section__number">1</span>Product details</h3><div class="product-grid">
          <div class="product-field"><label for="product-title">Product name *</label><input id="product-title" name="product_title" maxlength="255" value="<?=htmlspecialchars($_POST['product_title']??'')?>" placeholder="Samsung Galaxy S24 Ultra" required></div>
          <div class="product-field"><label for="sku-code">SKU *</label><input id="sku-code" name="sku_code" maxlength="100" value="<?=htmlspecialchars($_POST['sku_code']??'')?>" placeholder="SAM-S24-UL" autocomplete="off" required><span class="product-field__help">A unique stock code using letters, numbers, dashes or underscores.</span></div>
          <div class="product-field"><label for="category-id">Category *</label><select id="category-id" name="category_id" required><option value="">Select category</option><?php $cat_query=$conn->query("SELECT id,category_name FROM categories ORDER BY category_name");if($cat_query):while($c=$cat_query->fetch_assoc()):?><option value="<?=(int)$c['id']?>" <?=((string)$c['id']===($_POST['category_id']??''))?'selected':''?>><?=htmlspecialchars($c['category_name'])?></option><?php endwhile;endif;?></select></div>
          <div class="product-field"><label for="brand-id">Brand *</label><select id="brand-id" name="brand_id" required><option value="">Select brand</option><?php $brand_query=$conn->query("SELECT id,brand_name FROM brands ORDER BY brand_name");if($brand_query):while($b=$brand_query->fetch_assoc()):?><option value="<?=(int)$b['id']?>" <?=((string)$b['id']===($_POST['brand_id']??''))?'selected':''?>><?=htmlspecialchars($b['brand_name'])?></option><?php endwhile;endif;?></select></div>
          <div class="product-field full"><label for="supplier-email">Supplier email *</label><input id="supplier-email" type="email" name="supplier_email" maxlength="100" value="<?=htmlspecialchars($_POST['supplier_email']??'')?>" placeholder="supplier@example.com" required><span class="product-field__help">Used for stock-reorder requests and supplier communication.</span></div>
        </div></section>
        <section class="product-section"><h3 class="product-section__title"><span class="product-section__number">2</span>Pricing and stock</h3><div class="product-grid">
          <div class="product-field"><label for="unit-price">Unit price (KES) *</label><input id="unit-price" type="number" name="price" min="0" max="99999999.99" step=".01" value="<?=htmlspecialchars($_POST['price']??'')?>" placeholder="0.00" required><span class="product-field__help">Customer selling price per unit.</span></div>
          <div class="product-field"><label for="cost-price">Unit buying cost (KES) *</label><input id="cost-price" type="number" name="cost_price" min="0.01" max="99999999.99" step=".01" value="<?=htmlspecialchars($_POST['cost_price']??'')?>" placeholder="0.00" required><span class="product-field__help">Cost recognized by the shop per unit. Profit uses net sales after VAT less this cost.</span></div>
          <div class="product-field"><label for="opening-stock">Opening stock *</label><input id="opening-stock" type="number" name="stock" min="0" max="1000000" step="1" value="<?=htmlspecialchars($_POST['stock']??'')?>" placeholder="0" required><span class="product-field__help">Quantity currently ready for sale.</span></div>
        </div></section>
        <section class="product-section"><h3 class="product-section__title"><span class="product-section__number">3</span>Media and description</h3><div class="product-grid">
          <div class="product-field full"><label>Product image</label><div class="product-upload"><div class="product-upload__preview" id="product-image-preview">Image preview</div><div><label class="product-upload__button" for="product-image">Choose image</label><input id="product-image" type="file" name="product_image" accept="image/jpeg,image/png,image/webp"><span class="product-upload__name" id="product-image-name">JPG, PNG or WebP · maximum 5 MB</span></div></div></div>
          <div class="product-field full"><label for="description">Description</label><textarea id="description" name="description" rows="5" maxlength="10000" placeholder="Key specifications, warranty and other useful product details"><?=htmlspecialchars($_POST['description']??'')?></textarea></div>
        </div></section>
      </div>
      <footer class="product-entry__actions"><button class="product-entry__save" id="save-product" type="submit"><span class="product-entry__spinner"></span><span class="product-entry__save-label">Save product</span></button></footer>
    </form>
  </div>
</section>
<script>
(function(){
  const form=document.getElementById('ajax-restock-form');
  const file=document.getElementById('product-image');
  const preview=document.getElementById('product-image-preview');
  const fileName=document.getElementById('product-image-name');
  const save=document.getElementById('save-product');
  const messages={
    'product-title':'Enter the product name.',
    'sku-code':'Enter a unique SKU using letters, numbers, dashes or underscores.',
    'category-id':'Select a product category.',
    'brand-id':'Select a product brand.',
    'supplier-email':'Enter a valid supplier email address.',
    'unit-price':'Enter a valid unit price of KES 0 or more.',
    'cost-price':'Enter the unit buying cost (at least KES 0.01).',
    'opening-stock':'Enter the available stock quantity.'
  };
  function clearError(field){
    field.classList.remove('field-invalid');
    field.removeAttribute('aria-invalid');
    const current=field.parentElement.querySelector('.product-field__error');
    if(current)current.remove();
  }
  function showError(field){
    clearError(field);
    field.classList.add('field-invalid');
    field.setAttribute('aria-invalid','true');
    const error=document.createElement('span');
    error.className='product-field__error';
    error.textContent=messages[field.id]||'Check this field and try again.';
    field.insertAdjacentElement('afterend',error);
  }
  if(form){
    form.setAttribute('novalidate','novalidate');
    form.querySelectorAll('input,select,textarea').forEach(field=>{
      field.addEventListener('input',()=>{if(field.checkValidity())clearError(field);});
      field.addEventListener('change',()=>{if(field.checkValidity())clearError(field);});
    });
    form.addEventListener('submit',function(event){
      let firstInvalid=null;
      form.querySelectorAll('input,select,textarea').forEach(field=>{
        if(!field.checkValidity()){event.preventDefault();showError(field);if(!firstInvalid)firstInvalid=field;}else clearError(field);
      });
      if(firstInvalid){firstInvalid.focus();firstInvalid.scrollIntoView({behavior:'smooth',block:'center'});return;}
      save.disabled=true;save.classList.add('is-saving');
      save.querySelector('.product-entry__save-label').textContent='Saving product...';
    });
  }
  if(file)file.addEventListener('change',function(){
    const selected=this.files&&this.files[0];
    preview.textContent='Image preview';fileName.textContent='JPG, PNG or WebP - maximum 5 MB';
    if(!selected)return;
    fileName.textContent=selected.name;
    const image=document.createElement('img');image.alt='Selected product image';image.src=URL.createObjectURL(selected);
    image.onload=()=>URL.revokeObjectURL(image.src);preview.replaceChildren(image);
  });
})();
</script>
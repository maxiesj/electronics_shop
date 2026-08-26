<?php
// admin_create_user.php
session_start();

// SECURE: Kick out anyone who is not explicitly an admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'db.php';

if(isset($_POST['submit'])){
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    
    // SECURE: Grab the role safely from the backend post arrays
    $role = trim($_POST['role']); 

    // Validate role is strictly one of the allowed options
    $allowed_roles = ['admin', 'staff', 'customer'];
    if (!in_array($role, $allowed_roles)) {
        echo "Invalid role selected.";
        exit;
    }

    if(empty($fullname) || empty($email) || empty($phone) || empty($password) || empty($role)){
        echo "All fields are required.";
        exit;
    }

    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        echo "Invalid email format.";
        exit;
    }

    if(strlen($password) < 8){
        echo "Password must be at least 8 characters long.";
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $check = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($check);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        echo "Email already exists";
    }else{
        $sql = "INSERT INTO users(fullname, email, phone, password, role) VALUES(?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $fullname, $email, $phone, $hashed_password, $role);

        if($stmt->execute()){
            echo "Account successfully created by Admin.";
        }else{
            echo "Account creation failed.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<style>
    /* ==========================================================================
       1. FORM COMPONENT CONTAINER STYLES (DEFAULT DESKTOP VIEW)
       ========================================================================== */
    .myform {
        background-color: #e6f2ff;
        box-shadow: 0 4px 15px rgba(0, 0, 255, 0.15); /* Softened harsh solid blue shadow */
        padding: 30px 24px;
        width: 30%; /* Balanced width to prevent input squishing on small desktop displays */
        max-width: 450px;
        min-width: 320px;
        margin: 90px auto;
        border-radius: 8px;
        box-sizing: border-box; /* Prevents padding from breaking layout width bounds */
        font-family: 'Segoe UI', system-ui, sans-serif;
    }
    
    /* 2. Interactive Form Inputs & Selections Base Layout */
    input, select {
        width: 100%; /* Spreads across content card width with uniform outer container bounds */
        margin: 12px 0;
        display: block;
        padding: 10px 14px; /* Enhanced internal text breathing padding room */
        border-radius: 6px; /* Reduced from 12px to give a modern dashboard aesthetics layout */
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
        font-size: 14px;
        color: #1e293b;
        height: 40px; /* Explicit height baseline for cross-browser balance symmetry */
        box-sizing: border-box;
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    input:focus, select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }
    
    /* 3. Primary Execution Form Submission Trigger */
    button {
        width: 100%; /* Expanded from 40% on desktop to match input layout parameters cleanly */
        background-color: #2563eb; /* Updated from flat deep blue to a modern workspace cobalt tint */
        padding: 10px 0;
        color: white;
        margin: 16px 0 0 0;
        border-radius: 6px; /* Balanced border tracking metrics ratio tags */
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        height: 42px;
        box-sizing: border-box;
        transition: background-color 0.2s ease, transform 0.1s ease;
    }
    button:hover {
        background-color: #1d4ed8;
    }
    button:active {
        transform: scale(0.98);
    }

    /* ==========================================================================
       4. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 📱 PORTRAIT TABLETS & LARGE MOBILE SCREEN VIEWPORTS (Max 768px Width) */
    @media screen and (max-width: 768px) {
        .myform {
            width: 50%;
            margin: 60px auto;
            padding: 24px;
        }
    }

    /* 📱 SMARTPHONES & ULTRA COMPACT VIEWS (Max 480px Width) */
    @media screen and (max-width: 480px) {
        .myform {
            width: calc(100% - 32px); /* Scales cleanly across fluid margins without clipping edges */
            margin: 32px 16px;
            padding: 20px 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); /* Lightweight container visibility flat shadow tracking */
        }
        
        /* Enlarge touch input heights to avoid double-tap zoom triggers on phone options */
        input, select {
            height: 46px;
            font-size: 15px;
            padding: 12px;
        }
        
        button {
            height: 48px;
            font-size: 15px;
            margin-top: 20px;
        }
    }
</style>

</head>
<body>
<form method="POST" class="myform">
    <h2>Admin Panel: Add Team Member</h2><hr>
    <input type="text" name="fullname" placeholder="Full Name" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="text" name="phone" placeholder="Phone" required>
    <input type="password" name="password" placeholder="Temporary Password" required>
    
    <!-- It is safe to use the dropdown here because only an admin can see this form -->
    <select name="role" required>
        <option value="">Select Role</option>
        <option value="admin">Admin</option>
        <option value="staff">Staff</option>
        <option value="customer">Customer</option>
    </select>
    <br>
    <button type="submit" name="submit">Create Account</button>
    <a href="admin_dashboard.php">Back to Dashboard</a>
</form>
</body>
</html>

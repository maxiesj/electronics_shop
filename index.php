<?php
include 'db.php';
?>

<!DOCTYPE html>
<html>
<head>

    <title>Electronics Shop</title>
<style>
    /* ==========================================================================
       1. GLOBAL RESET & BASELINE LAYOUT RULES (DEFAULT DESKTOP VIEW)
       ========================================================================== */
    body {
        background-color: #f3f4f6; /* Added modern workspace slate layout tint */
        font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; /* Modern web-fallback tokens */
        margin: 0;
        padding: 40px 24px; /* Structured canvas cushion spacing */
        color: #1f2937;
    }

    /* 2. Core Catalog Grid System (Default Desktop View) */
    .products {
        display: grid; /* Swapped from flex to grid for strict, pixel-perfect baseline structure */
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); /* Dynamically auto-fills screen columns */
        gap: 24px; /* Enhanced internal spacing grids padding room */
        max-width: 80rem;
        margin: 0 auto;
        width: 100%;
        box-sizing: border-box;
    }

    /* 3. Product Showcase Display Cards Components */
    .card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        padding: 20px;
        border-radius: 12px; /* Smoothed from 10px to mirror your transaction dashboards theme */
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.02);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 12px; /* Adjusted gap to balance out the new rating layout rows */
        box-sizing: border-box;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
    }

    /* Fixed Thumbnail Image Frame Showcase */
    .card img {
        width: 100%;
        height: 200px;
        object-fit: contain; /* Changed from cover to contain to prevent hardware/appliance image cropping issues */
        background-color: #f9fafb;
        border-radius: 8px;
        padding: 8px;
        box-sizing: border-box;
    }

    /* 🌟 COMPLIANT STAR RATING INTEGRATION STYLES */
    .rating-row {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 4px;
        box-sizing: border-box;
    }
    .stars {
        color: #d97706; /* High-contrast golden amber star tint token */
        font-size: 0.875rem;
        letter-spacing: 1px;
        white-space: nowrap;
        display: inline-flex;
    }
    .rating-text {
        font-size: 0.75rem;
        font-weight: 700;
        color: #6b7280; /* Neutral grey secondary textual allocation */
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    /* Typography & Currency Layout Attributes */
    .price {
        color: #059669; /* Updated to a clean, professional financial emerald color token */
        font-size: 1.25rem; /* Converted from 20px to standard fluid rem scaling */
        font-weight: 800;
        margin: 6px 0 0;
        white-space: nowrap;
    }

    /* ==========================================================================
       4. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 📱 PORTRAIT TABLETS & MEDIUM VIEWPORTS (Max 768px Width Screens) */
    @media screen and (max-width: 768px) {
        body {
            padding: 24px 16px;
        }
        .products {
            grid-template-columns: repeat(2, minmax(0, 1fr)); /* Rigidly locks down into safe dual-columns lists */
            gap: 16px;
        }
        .card {
            padding: 16px;
            border-radius: 8px;
            gap: 10px;
        }
        .card img {
            height: 160px; /* Contracts image display heights slightly for shorter tablet columns */
            padding: 4px;
        }
        .rating-row {
            gap: 4px;
        }
        .stars {
            font-size: 0.8rem;
        }
        .rating-text {
            font-size: 0.7rem;
        }
        .price {
            font-size: 1.1rem;
            margin-top: 4px;
        }
    }

    /* 📱 SMARTPHONES & ULTRA COMPACT TOUCH VIEWS (Max 480px Width Screens) */
    @media screen and (max-width: 480px) {
        .products {
            grid-template-columns: 1fr; /* Flattens matrix layers into clean, full-width vertical lines */
            gap: 14px;
        }
        .card img {
            height: 220px; /* Scales image back up on smartphone screens to utilize fingertip tap focus targets */
        }
        .rating-row {
            margin-top: 6px;
            justify-content: flex-start;
        }
    }
</style>


</head>
<body>

<h1>Electronics Shop</h1>

<div class="products">

<?php

$sql = "SELECT
            products.*,
            categories.category_name
        FROM products

        LEFT JOIN categories
        ON products.category_id = categories.id

        ORDER BY products.id DESC";

$result = $conn->query($sql);

if($result->num_rows > 0){

    while($row = $result->fetch_assoc()){

?>

        <div class="card">

            <!-- Product Image -->
            <img src="uploads/<?php echo $row['image']; ?>">

            <!-- Product Name -->
            <h3>
                <?php echo $row['product_name']; ?>
            </h3>

            <!-- Category -->
            <p>
                Category:
                <?php echo $row['category_name']; ?>
            </p>

            <!-- Price -->
            <p class="price">
                KES <?php echo $row['price']; ?>
            </p>

            <!-- Stock -->
            <p>
                Stock:
                <?php echo $row['stock_quantity']; ?>
            </p>

            <!-- Description -->
            <p>
                <?php echo $row['description']; ?>
            </p>

            <!-- View Button -->
            <a href="product_details.php?id=<?php echo $row['id']; ?>">

                View Product

            </a>

        </div>

<?php

    }

}else{

    echo "No products available";
}

?>

</div>

</body>
</html>
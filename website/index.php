<?php
// Start session and verify the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';

// --- CALCULATE DASHBOARD STATS ---

// 1. Get the total number of parts
$total_parts = 0;
$query_parts = "SELECT COUNT(*) AS total FROM Part";
$parts_result = $conn->query($query_parts);
if ($parts_result) {
    $parts_row = $parts_result->fetch_assoc();
    $total_parts = $parts_row['total'];
}

// 2. Get the total number of categories
$total_categories = 0;
$query_cats = "SELECT COUNT(*) AS total FROM Category";
$cats_result = $conn->query($query_cats);
if ($cats_result) {
    $cats_row = $cats_result->fetch_assoc();
    $total_categories = $cats_row['total'];
}

// 3. Get the total number of active users
$total_users = 0;
$query_users = "SELECT COUNT(*) AS total FROM Users WHERE IsActive = 1";
$users_result = $conn->query($query_users);
if ($users_result) {
    $users_row = $users_result->fetch_assoc();
    $total_users = $users_row['total'];
}

// 4. Calculate total money sitting in inventory (Quantity * Price)
$total_inventory_value = 0;
$query_value = "SELECT SUM(Inventory.QuantityOnHand * Part.Price) AS total_value FROM Inventory JOIN Part ON Inventory.PartID = Part.PartID";
$value_result = $conn->query($query_value);
if ($value_result) {
    $value_row = $value_result->fetch_assoc();
    if ($value_row['total_value'] != null) {
        $total_inventory_value = $value_row['total_value'];
    }
}

// 5. Get the 5 most recent transactions to show in the feed
$query_recent_transactions = "
    SELECT StockTransaction.TransactionType, StockTransaction.Quantity, StockTransaction.TransactionDate, Part.PartName 
    FROM StockTransaction 
    JOIN Part ON StockTransaction.PartID = Part.PartID 
    ORDER BY StockTransaction.TransactionDate DESC 
    LIMIT 5
";
$recent_transactions_result = $conn->query($query_recent_transactions);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - IM System</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
    <div class="sidebar">
        <h2>IM System.</h2>
        <a href="index.php" class="active">Dashboard</a>
        <a href="category.php">Category</a>
        <a href="manufacturer.php">Manufacturer</a>
        <a href="users.php">Users</a>
        <a href="supplier.php">Supplier</a>
        <a href="part.php">Part</a>
        <a href="inventory.php">Inventory</a>
        <a href="stocktransaction.php">Stock Transactions</a>
        
        <a href="logout.php" class="logout">Logout</a>
    </div>
    <div class="main-content">
        <div class="header">
            <div>
                <h1>Dashboard</h1>
                <p>Welcome back, here is your system overview.</p>
            </div>
            <div class="header-user-info">
                <p class="header-user-label">Logged in as</p>
                <p class="header-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
            </div>
        </div>
        
        <div class="metrics-grid">
            <div class="metric-card">
                <span>Total Parts</span>
                <h2><?= number_format($total_parts) ?></h2>
            </div>
            <div class="metric-card">
                <span>Total Categories</span>
                <h2><?= number_format($total_categories) ?></h2>
            </div>
            <div class="metric-card">
                <span>Total Value</span>
                <h2>$<?= number_format((float)$total_inventory_value, 2) ?></h2>
            </div>
            <div class="metric-card">
                <span>Active Users</span>
                <h2><?= number_format($total_users) ?></h2>
            </div>
        </div>

        <div class="card">
            <h3>Recent Transactions</h3>
            <ul class="recent-list">
                <?php 
                if ($recent_transactions_result) {
                    if ($recent_transactions_result->num_rows > 0) {
                        while ($transaction_row = $recent_transactions_result->fetch_assoc()) {
                            $type_css_class = '';
                            if ($transaction_row['TransactionType'] == 'Receipt') {
                                $type_css_class = 'tx-receipt';
                            } else if ($transaction_row['TransactionType'] == 'Sale') {
                                $type_css_class = 'tx-sale';
                            } else {
                                $type_css_class = 'tx-adjustment';
                            }
                            
                            $math_sign = '';
                            if ($transaction_row['TransactionType'] == 'Sale') {
                                $math_sign = '-';
                            } else {
                                $math_sign = '+';
                            }
                            ?>
                            <li>
                                <div>
                                    <p class="feed-item-title"><?= htmlspecialchars($transaction_row['PartName']) ?></p>
                                    <p class="feed-item-date"><?= date('M j, Y g:i A', strtotime($transaction_row['TransactionDate'])) ?></p>
                                </div>
                                <div class="feed-item-right">
                                    <span class="feed-qty"><?= $math_sign ?><?= htmlspecialchars($transaction_row['Quantity']) ?></span>
                                    <span class="tx-type <?= $type_css_class ?>"><?= htmlspecialchars($transaction_row['TransactionType']) ?></span>
                                </div>
                            </li>
                            <?php 
                        }
                    } else {
                        echo '<li class="feed-empty">No recent transactions found.</li>';
                    }
                }
                ?>
            </ul>
        </div>
    </div>
</body>
</html>

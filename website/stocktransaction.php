<?php

// Start session to verify the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// Connect to database
include 'db.php';

$error_message = ""; // Variable to hold validation errors

// Fetch parts for the dropdown menu
$parts = array();
$part_sql = 'SELECT PartID, PartName FROM Part';
$part_result = $conn->query($part_sql);
if ($part_result) {
    while ($row = $part_result->fetch_assoc()) {
        $parts[] = $row;
    }
}

// Fetch suppliers for the dropdown menu
$suppliers = array();
$supplier_sql = 'SELECT SupplierID, SupplierName FROM Supplier';
$supplier_result = $conn->query($supplier_sql);
if ($supplier_result) {
    while ($row = $supplier_result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}


// --- CHECK IF EDITING ---
// If 'edit' is in the URL, fetch the record so we can fill the form
$edit_row = null;
if (isset($_GET['edit'])) {
    $id_to_edit = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM StockTransaction WHERE TransactionID = $id_to_edit";
    $edit_result = $conn->query($edit_query);
    if ($edit_result) {
        $edit_row = $edit_result->fetch_assoc();
    }
}

// --- CHECK IF DELETING ---
// If 'delete' is in the URL, delete the record
if (isset($_GET['delete'])) {
    $id_to_delete = (int)$_GET['delete'];
    
    $can_delete = true;
    
    // Validate that deleting won't drop inventory below 0
    $get_old_transaction_sql = "SELECT * FROM StockTransaction WHERE TransactionID = $id_to_delete";
    $transaction_result = $conn->query($get_old_transaction_sql);
    if ($transaction_result && $transaction_result->num_rows > 0) {
        $old_transaction = $transaction_result->fetch_assoc();
        $transaction_type = $old_transaction['TransactionType'];
        $transaction_quantity = (int)$old_transaction['Quantity'];
        $part_id = (int)$old_transaction['PartID'];
        
        // Deleting a receipt or positive adjustment subtracts from inventory. Check if it drops below 0.
        if ($transaction_type === 'Receipt' || $transaction_type === 'Adjustment') {
            $inv_res = $conn->query("SELECT QuantityOnHand FROM Inventory WHERE PartID = $part_id");
            if ($inv_res && $inv_res->num_rows > 0) {
                $curr_qty = (int)$inv_res->fetch_assoc()['QuantityOnHand'];
                if (($curr_qty - $transaction_quantity) < 0) {
                    $can_delete = false;
                    $error_message = "Cannot delete record: Reversing this transaction would drop inventory below 0.";
                }
            }
        }
        
        if ($can_delete) {
            // Reverse inventory math if we are deleting a stock transaction
            if ($transaction_type === 'Sale') {
                $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand + $transaction_quantity WHERE PartID = $part_id";
                $conn->query($update_inventory_sql);
            } else {
                if ($transaction_type === 'Receipt' || $transaction_type === 'Adjustment') {
                    $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand - $transaction_quantity WHERE PartID = $part_id";
                    $conn->query($update_inventory_sql);
                }
            }
            
            // Perform the actual deletion
            $delete_query = "DELETE FROM StockTransaction WHERE TransactionID = $id_to_delete";
            $conn->query($delete_query);
            
            // Reload the page
            header("Location: stocktransaction.php"); 
            exit;
        }
    }
}

// --- CHECK IF SAVING/UPDATING FORM ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $transaction_type_val = $_POST['TransactionType'];
    $quantity_val = (int)$_POST['Quantity'];
    $new_part_id = (int)$_POST['PartID'];
    
    $is_valid = true;

    // 1. Prevent negative values on anything but Adjustment
    if ($transaction_type_val !== 'Adjustment' && $quantity_val < 0) {
        $error_message = "Only Adjustments can have a negative quantity.";
        $is_valid = false;
    }

    // 2. Validate that transactions won't drop inventory below 0
    if ($is_valid) {
        $id_to_update = (isset($_POST['update_id']) && $_POST['update_id'] != '') ? (int)$_POST['update_id'] : 0;
        $new_net_change = ($transaction_type_val === 'Sale') ? -$quantity_val : $quantity_val;

        if ($id_to_update > 0) {
            // Validating an UPDATE
            $get_old_sql = "SELECT TransactionType, Quantity, PartID FROM StockTransaction WHERE TransactionID = $id_to_update";
            $old_res = $conn->query($get_old_sql);
            if ($old_res && $old_res->num_rows > 0) {
                $old_tx = $old_res->fetch_assoc();
                $old_part_id = (int)$old_tx['PartID'];
                $old_qty = (int)$old_tx['Quantity'];
                $old_type = $old_tx['TransactionType'];
                $old_net_change = ($old_type === 'Sale') ? -$old_qty : $old_qty;

                $old_curr_qty = 0;
                $res1 = $conn->query("SELECT QuantityOnHand FROM Inventory WHERE PartID = $old_part_id");
                if ($res1 && $res1->num_rows > 0) { $old_curr_qty = (int)$res1->fetch_assoc()['QuantityOnHand']; }

                $new_curr_qty = 0;
                $res2 = $conn->query("SELECT QuantityOnHand FROM Inventory WHERE PartID = $new_part_id");
                if ($res2 && $res2->num_rows > 0) { $new_curr_qty = (int)$res2->fetch_assoc()['QuantityOnHand']; }

                if ($old_part_id === $new_part_id) {
                    $combined = $old_curr_qty - $old_net_change + $new_net_change;
                    if ($combined < 0) {
                        $error_message = "Cannot save: Transaction would drop inventory below 0 (Projected: $combined).";
                        $is_valid = false;
                    }
                } else {
                    if ($old_curr_qty - $old_net_change < 0) {
                        $error_message = "Updating this drops the original part's inventory below 0.";
                        $is_valid = false;
                    } elseif ($new_curr_qty + $new_net_change < 0) {
                        $error_message = "Cannot save: Transaction drops the new part's inventory below 0.";
                        $is_valid = false;
                    }
                }
            }
        } else {
            // Validating an INSERT
            $curr_qty = 0;
            $inv_res = $conn->query("SELECT QuantityOnHand FROM Inventory WHERE PartID = $new_part_id");
            if ($inv_res && $inv_res->num_rows > 0) {
                $curr_qty = (int)$inv_res->fetch_assoc()['QuantityOnHand'];
            }
            if ($curr_qty + $new_net_change < 0) {
                $error_message = "Cannot save: Transaction would drop inventory below 0.";
                $is_valid = false;
            }
        }
    }

    if ($is_valid) {
        // Grab form inputs securely
        $safe_value = $conn->real_escape_string($_POST['TransactionType']);
        $TransactionType = "'" . $safe_value . "'";
        $safe_value = $conn->real_escape_string($_POST['Quantity']);
        $Quantity = "'" . $safe_value . "'";
        $TransactionDate = "'" . date('Y-m-d H:i:s') . "'";
        $safe_value = $conn->real_escape_string($_POST['Notes']);
        $Notes = "'" . $safe_value . "'";
        $safe_value = $conn->real_escape_string($_POST['PartID']);
        $PartID = "'" . $safe_value . "'";
        
        if (empty($_POST['SupplierID'])) {
            $SupplierID = 'NULL';
        } else {
            $safe_value = $conn->real_escape_string($_POST['SupplierID']);
            $SupplierID = "'" . $safe_value . "'";
        }
        $UserID = $_SESSION['user_id'];
        $safe_value = $conn->real_escape_string($_POST['ReferenceNumber']);
        $ReferenceNumber = "'" . $safe_value . "'";

        // If 'update_id' exists, we are UPDATING an old record
        if (isset($_POST['update_id']) && $_POST['update_id'] != '') {
            $id_to_update = (int)$_POST['update_id'];
            
            // Reversing old math from inventory
            $get_old_transaction_sql = "SELECT * FROM StockTransaction WHERE TransactionID = $id_to_update";
            $transaction_result = $conn->query($get_old_transaction_sql);
            if ($transaction_result && $transaction_result->num_rows > 0) {
                $old_transaction = $transaction_result->fetch_assoc();
                $old_transaction_type = $old_transaction['TransactionType'];
                $old_transaction_quantity = (int)$old_transaction['Quantity'];
                $part_id = (int)$old_transaction['PartID'];
                
                if ($old_transaction_type === 'Sale') {
                    $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand + $old_transaction_quantity WHERE PartID = $part_id";
                    $conn->query($update_inventory_sql);
                } else {
                    $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand - $old_transaction_quantity WHERE PartID = $part_id";
                    $conn->query($update_inventory_sql);
                }
            }
            
            // Update the record
            $update_record_sql = "UPDATE StockTransaction SET TransactionType = $TransactionType, Quantity = $Quantity, TransactionDate = $TransactionDate, Notes = $Notes, PartID = $PartID, SupplierID = $SupplierID, UserID = $UserID, ReferenceNumber = $ReferenceNumber WHERE TransactionID = $id_to_update";
            $conn->query($update_record_sql);
            
            // Apply NEW inventory math
            $transaction_type = $_POST['TransactionType'];
            $transaction_quantity = (int)$_POST['Quantity'];
            $part_id = (int)$_POST['PartID'];
            
            if ($transaction_type === 'Sale') {
                $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand - $transaction_quantity WHERE PartID = $part_id";
                $conn->query($update_inventory_sql);
            } else {
                $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand + $transaction_quantity WHERE PartID = $part_id";
                $conn->query($update_inventory_sql);
            }
            
        } else {
            // INSERTING a new record
            $insert_record_sql = "INSERT INTO StockTransaction (TransactionType, Quantity, TransactionDate, Notes, PartID, SupplierID, UserID, ReferenceNumber) VALUES ($TransactionType, $Quantity, $TransactionDate, $Notes, $PartID, $SupplierID, $UserID, $ReferenceNumber)";
            $conn->query($insert_record_sql);
            
            // Apply inventory math
            $transaction_type = $_POST['TransactionType'];
            $transaction_quantity = (int)$_POST['Quantity'];
            $part_id = (int)$_POST['PartID'];
            
            $check_inventory_sql = "SELECT * FROM Inventory WHERE PartID = $part_id";
            $inventory_check_result = $conn->query($check_inventory_sql);
            
            if ($inventory_check_result) {
                if ($inventory_check_result->num_rows > 0) {
                    if ($transaction_type === 'Sale') {
                        $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand - $transaction_quantity WHERE PartID = $part_id";
                        $conn->query($update_inventory_sql);
                    } else {
                        $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand + $transaction_quantity WHERE PartID = $part_id";
                        $conn->query($update_inventory_sql);
                    }
                } else {
                    $initial_quantity = ($transaction_type === 'Sale') ? -$transaction_quantity : $transaction_quantity;
                    $insert_inventory_sql = "INSERT INTO Inventory (PartID, QuantityOnHand, ReservedQuantity) VALUES ($part_id, $initial_quantity, 0)";
                    $conn->query($insert_inventory_sql);
                }
            }
        }
        
        // Reload the page on success
        header("Location: stocktransaction.php");
        exit;
    }
}

// --- HANDLE SEARCH BAR ---
$search_query = '';
if (isset($_GET['search'])) {
    $search_query = $_GET['search'];
}

$search_keyword = $conn->real_escape_string($search_query);

$where_sql = "";
if ($search_keyword != '') {
    $where_sql = " WHERE (" . "StockTransaction.TransactionID LIKE '%$search_keyword%'" . " OR " . "StockTransaction.TransactionType LIKE '%$search_keyword%'" . " OR " . "Part.PartName LIKE '%$search_keyword%'" . " OR " . "Supplier.SupplierName LIKE '%$search_keyword%'" . " OR " . "Users.FullName LIKE '%$search_keyword%'" . " OR " . "StockTransaction.ReferenceNumber LIKE '%$search_keyword%'" . " OR " . "StockTransaction.Notes LIKE '%$search_keyword%'" . ")";
}

$final_query = "SELECT StockTransaction.*, Part.PartName, Supplier.SupplierName, Users.FullName FROM StockTransaction LEFT JOIN Part ON StockTransaction.PartID = Part.PartID LEFT JOIN Supplier ON StockTransaction.SupplierID = Supplier.SupplierID LEFT JOIN Users ON StockTransaction.UserID = Users.UserID" . $where_sql . " ORDER BY TransactionDate DESC";
$result = $conn->query($final_query);

?>
<!DOCTYPE html>
<html>
<head>
    <title>StockTransaction - KompyuTek</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
    <div class="sidebar">
        <h2>KompyuTek</h2>
        <a href="index.php">Dashboard</a>
        <?php
        $category_nav_class = ""; if ("StockTransaction" == "Category") $category_nav_class = "active";
        $manufacturer_nav_class = ""; if ("StockTransaction" == "Manufacturer") $manufacturer_nav_class = "active";
        $users_nav_class = ""; if ("StockTransaction" == "Users") $users_nav_class = "active";
        $supplier_nav_class = ""; if ("StockTransaction" == "Supplier") $supplier_nav_class = "active";
        $part_nav_class = ""; if ("StockTransaction" == "Part") $part_nav_class = "active";
        $inventory_nav_class = ""; if ("StockTransaction" == "Inventory") $inventory_nav_class = "active";
        $stock_nav_class = ""; if ("StockTransaction" == "StockTransaction") $stock_nav_class = "active";
        ?>
        <a href="category.php" class="<?= $category_nav_class ?>">Category</a>
        <a href="manufacturer.php" class="<?= $manufacturer_nav_class ?>">Manufacturer</a>
        <a href="users.php" class="<?= $users_nav_class ?>">Users</a>
        <a href="supplier.php" class="<?= $supplier_nav_class ?>">Supplier</a>
        <a href="part.php" class="<?= $part_nav_class ?>">Part</a>
        <a href="inventory.php" class="<?= $inventory_nav_class ?>">Inventory</a>
        <a href="stocktransaction.php" class="<?= $stock_nav_class ?>">Stock Transactions</a>
        
        <a href="logout.php" class="logout">Logout</a>
    </div>
    <div class="main-content">
        <div class="header">
            <div>
                <h1>StockTransaction</h1>
                <p>Manage and track your stocktransaction records here.</p>
            </div>
            <div class="header-user-info">
                <p class="header-user-label">Logged in as</p>
                <p class="header-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
            </div>
        </div>
        
        <div class="card">
            <h3>
            <?php
            if (isset($edit_row)) {
                echo 'Edit Record';
            } else {
                echo 'Add New Record';
            }
            ?>
            </h3>

            <?php if (!empty($error_message)): ?>
                <div style="background-color: #f8d7da; color: #721c24; padding: 12px; margin-bottom: 20px; border-radius: 6px; border: 1px solid #f5c6cb;">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php
                $hidden_id = "";
                if (isset($edit_row)) {
                    $hidden_id = $edit_row['TransactionID'];
                }
                ?>
                <input type="hidden" name="update_id" value="<?= $hidden_id ?>">
                
                <div class="form-group">
                    <label>Transaction Type</label>
                    <select name="TransactionType" required>
                        <?php
                        $receipt_selected = "";
                        $sale_selected = "";
                        $adjustment_selected = "";
                        if (isset($edit_row)) {
                            if ($edit_row['TransactionType'] == 'Receipt') { $receipt_selected = "selected"; }
                            if ($edit_row['TransactionType'] == 'Sale') { $sale_selected = "selected"; }
                            if ($edit_row['TransactionType'] == 'Adjustment') { $adjustment_selected = "selected"; }
                        }
                        ?>
                        <option value="Receipt" <?= $receipt_selected ?>>Receipt (+)</option>
                        <option value="Sale" <?= $sale_selected ?>>Sale (-)</option>
                        <option value="Adjustment" <?= $adjustment_selected ?>>Adjustment</option>
                    </select>
                </div>
            
                <div class="form-group">
                    <label>Quantity</label>
                    <?php
                    $input_value = "";
                    if (isset($_POST['Quantity'])) {
                        // Persist value if there's an error
                        $input_value = htmlspecialchars($_POST['Quantity']);
                    } elseif (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['Quantity']);
                    }
                    ?>
                    <input type="number" step="1" name="Quantity" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>Notes</label>
                    <?php
                    $input_value = "";
                    if (isset($_POST['Notes'])) {
                        $input_value = htmlspecialchars($_POST['Notes']);
                    } elseif (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['Notes']);
                    }
                    ?>
                    <input type="text" name="Notes" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>Part</label>
                    <select name="PartID" required>
                        <option value="">-- Select Part --</option>
                        <?php foreach($parts as $item): ?>
                            <?php
                            $selected_attribute = "";
                            if (isset($_POST['PartID']) && $_POST['PartID'] == $item['PartID']) {
                                $selected_attribute = "selected";
                            } elseif (isset($edit_row) && $edit_row['PartID'] == $item['PartID']) {
                                $selected_attribute = "selected";
                            }
                            ?>
                            <option value="<?= $item['PartID'] ?>" <?= $selected_attribute ?>><?= htmlspecialchars($item['PartName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            
                <div class="form-group">
                    <label>Supplier</label>
                    <select name="SupplierID">
                        <option value="">-- None (Walk-in/General) --</option>
                        <?php foreach($suppliers as $item): ?>
                            <?php
                            $selected_attribute = "";
                            if (isset($_POST['SupplierID']) && $_POST['SupplierID'] == $item['SupplierID']) {
                                $selected_attribute = "selected";
                            } elseif (isset($edit_row) && $edit_row['SupplierID'] == $item['SupplierID']) {
                                $selected_attribute = "selected";
                            }
                            ?>
                            <option value="<?= $item['SupplierID'] ?>" <?= $selected_attribute ?>><?= htmlspecialchars($item['SupplierName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            
                <div class="form-group">
                    <label>ReferenceNumber</label>
                    <?php
                    $input_value = "";
                    if (isset($_POST['ReferenceNumber'])) {
                        $input_value = htmlspecialchars($_POST['ReferenceNumber']);
                    } elseif (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['ReferenceNumber']);
                    }
                    ?>
                    <input type="text" name="ReferenceNumber" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group form-actions">
                    <?php if(isset($edit_row)): ?>
                    <a href="stocktransaction.php" class="btn-cancel">Cancel</a>
                    <?php endif; ?>
                    <button type="submit">
                        <?php
                        if (isset($edit_row)) {
                            echo 'Update Details';
                        } else {
                            echo 'Save Record';
                        }
                        ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="card toolbar-card">
            <h3 class="toolbar-title">Records List</h3>
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search_query) ?>" class="search-input">
                <button type="submit" class="btn-search">Search</button>
                <?php if($search_query != ''): ?>
                <a href="stocktransaction.php"><button type="button" class="btn-outline btn-clear">Clear</button></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card table-card">
            <table>
                <thead>
                    <tr>
                        <th>TransactionID</th><th>TransactionType</th><th>Quantity</th><th>TransactionDate</th><th>Notes</th><th>PartName</th><th>SupplierName</th><th>FullName</th><th>ReferenceNumber</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result) {
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                ?>
                                <tr>
                                    <td><?= isset($row['TransactionID']) ? htmlspecialchars($row['TransactionID']) : '' ?></td>
                                    <td><?= isset($row['TransactionType']) ? htmlspecialchars($row['TransactionType']) : '' ?></td>
                                    <td><?= isset($row['Quantity']) ? htmlspecialchars($row['Quantity']) : '' ?></td>
                                    <td><?= isset($row['TransactionDate']) ? htmlspecialchars($row['TransactionDate']) : '' ?></td>
                                    <td><?= isset($row['Notes']) ? htmlspecialchars($row['Notes']) : '' ?></td>
                                    <td><?= isset($row['PartName']) ? htmlspecialchars($row['PartName']) : '' ?></td>
                                    <td><?= isset($row['SupplierName']) ? htmlspecialchars($row['SupplierName']) : '' ?></td>
                                    <td><?= isset($row['FullName']) ? htmlspecialchars($row['FullName']) : '' ?></td>
                                    <td><?= isset($row['ReferenceNumber']) ? htmlspecialchars($row['ReferenceNumber']) : '' ?></td>
                                    <td class="action-cell">
                                        <a href="?edit=<?= $row['TransactionID'] ?>"><button class="btn-outline btn-sm mr-sm">Edit</button></a>
                                        <a href="?delete=<?= $row['TransactionID'] ?>" onclick="return confirm('Are you sure you want to delete this record?')"><button class="btn-danger btn-sm">Delete</button></a>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="100" class="empty-state">No records found matching your search.</td></tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
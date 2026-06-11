<?php

// Start session to verify the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// Connect to database
include 'db.php';


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
    
    // Reverse inventory math if we are deleting a stock transaction
    $get_old_transaction_sql = "SELECT * FROM StockTransaction WHERE TransactionID = $id_to_delete";
    $transaction_result = $conn->query($get_old_transaction_sql);
    if ($transaction_result) {
        $old_transaction = $transaction_result->fetch_assoc();
        if ($old_transaction) {
            $transaction_type = $old_transaction['TransactionType'];
            $transaction_quantity = (int)$old_transaction['Quantity'];
            $part_id = (int)$old_transaction['PartID'];
            
            // --- NEW VALIDATION: Prevent delete if it drops inventory below 0 ---
            $inv_check = $conn->query("SELECT QuantityOnHand FROM Inventory WHERE PartID = $part_id");
            if ($inv_check && $row = $inv_check->fetch_assoc()) {
                if ($row['QuantityOnHand'] - $transaction_quantity < 0) {
                    echo "<script>alert('Cannot delete: Reversing this transaction drops inventory below 0.'); window.location.href='stocktransaction.php';</script>";
                    exit;
                }
            }
            // --------------------------------------------------------------------
            
            // Uniformly reverse the math
            $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand - $transaction_quantity WHERE PartID = $part_id";
            $conn->query($update_inventory_sql);
        }
    }
    
    // Perform the actual deletion
    $delete_query = "DELETE FROM StockTransaction WHERE TransactionID = $id_to_delete";
    try {
        $conn->query($delete_query);
        header("Location: stocktransaction.php"); 
        exit;
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1451) {
            $error_message = "Error: Cannot delete this record because it is currently in use by other items (Foreign Key Constraint).";
        } else {
            $error_message = "Error deleting record: " . $e->getMessage();
        }
    }
}

// --- CHECK IF SAVING/UPDATING FORM ---
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $ref_num_check = $conn->real_escape_string($_POST['ReferenceNumber']);
    $update_id_check = 0;
    if (isset($_POST['update_id']) && $_POST['update_id'] != '') {
        $update_id_check = (int)$_POST['update_id'];
    }
    $ref_check_sql = "SELECT TransactionID FROM StockTransaction WHERE ReferenceNumber = '$ref_num_check' AND TransactionID != $update_id_check";
    $ref_check_res = $conn->query($ref_check_sql);
    
    if (empty($ref_num_check)) {
        $error_message = 'Error: Reference Number cannot be empty.';
    } elseif ($ref_check_res && $ref_check_res->num_rows > 0) {
        $error_message = 'Error: Reference Number already exists. Please use a unique one.';
    }

    if (empty($error_message)) {
        $transaction_type_check = $_POST['TransactionType'];
        $quantity_check = (int)$_POST['Quantity'];
        $part_id_check = (int)$_POST['PartID'];

        if ($transaction_type_check === 'Sale' && $quantity_check > 0) {
            $quantity_check = -$quantity_check;
            $_POST['Quantity'] = $quantity_check; 
        } elseif ($transaction_type_check === 'Receipt' && $quantity_check < 0) {
            $error_message = 'Error: Receipts cannot be negative.';
        }

        if (empty($error_message)) {
            $net_change = $quantity_check;

            if (isset($_POST['update_id']) && $_POST['update_id'] != '') {
                $id_to_update = (int)$_POST['update_id'];
                $old_tx_res = $conn->query("SELECT * FROM StockTransaction WHERE TransactionID = $id_to_update");
                if ($old_tx_res && $old_tx = $old_tx_res->fetch_assoc()) {
                    $old_part = (int)$old_tx['PartID'];
                    $old_qty = (int)$old_tx['Quantity'];
                    $old_net = $old_qty;

                    if ($old_part == $part_id_check) {
                        $inv_res = $conn->query("SELECT QuantityOnHand FROM Inventory WHERE PartID = $old_part");
                        $curr_qty = 0;
                        if ($inv_res) {
                            $row = $inv_res->fetch_assoc();
                            if ($row) {
                                $curr_qty = (int)$row['QuantityOnHand'];
                            }
                        }
                        if ($curr_qty - $old_net + $net_change < 0) {
                            $error_message = 'Cannot save: Update drops inventory below 0.';
                        }
                    } else {
                        $inv_old = $conn->query("SELECT QuantityOnHand FROM Inventory WHERE PartID = $old_part");
                        $curr_old_qty = 0;
                        if ($inv_old) {
                            $row = $inv_old->fetch_assoc();
                            if ($row) {
                                $curr_old_qty = (int)$row['QuantityOnHand'];
                            }
                        }
                        if ($curr_old_qty - $old_net < 0) {
                            $error_message = 'Cannot save: Update drops original part inventory below 0.';
                        }

                        if (empty($error_message)) {
                            $inv_new = $conn->query("SELECT QuantityOnHand FROM Inventory WHERE PartID = $part_id_check");
                            $curr_new_qty = 0;
                            if ($inv_new) {
                                $row = $inv_new->fetch_assoc();
                                if ($row) {
                                    $curr_new_qty = (int)$row['QuantityOnHand'];
                                }
                            }
                            if ($curr_new_qty + $net_change < 0) {
                                $error_message = 'Cannot save: Update drops new part inventory below 0.';
                            }
                        }
                    }
                }
            } else {
                $inv_res = $conn->query("SELECT QuantityOnHand FROM Inventory WHERE PartID = $part_id_check");
                $curr_qty = 0;
                        if ($inv_res) {
                            $row = $inv_res->fetch_assoc();
                            if ($row) {
                                $curr_qty = (int)$row['QuantityOnHand'];
                            }
                        }
                if ($curr_qty + $net_change < 0) {
                    $error_message = 'Cannot save: Transaction drops inventory below 0.';
                }
            }
        }
        
        if (empty($error_message)) {
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

            if (isset($_POST['update_id']) && $_POST['update_id'] != '') {
                $id_to_update = (int)$_POST['update_id'];
                
                $get_old_transaction_sql = "SELECT * FROM StockTransaction WHERE TransactionID = $id_to_update";
                $transaction_result = $conn->query($get_old_transaction_sql);
                if ($transaction_result) {
                    $old_transaction = $transaction_result->fetch_assoc();
                    if ($old_transaction) {
                        $old_transaction_quantity = (int)$old_transaction['Quantity'];
                        $part_id = (int)$old_transaction['PartID'];
                        
                        $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand - $old_transaction_quantity WHERE PartID = $part_id";
                        $conn->query($update_inventory_sql);
                    }
                }
                
                $update_record_sql = "UPDATE StockTransaction SET TransactionType = $TransactionType, Quantity = $Quantity, TransactionDate = $TransactionDate, Notes = $Notes, PartID = $PartID, SupplierID = $SupplierID, UserID = $UserID, ReferenceNumber = $ReferenceNumber WHERE TransactionID = $id_to_update";
                $conn->query($update_record_sql);
                
                $transaction_quantity = (int)$_POST['Quantity'];
                $part_id = (int)$_POST['PartID'];
                
                $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand + $transaction_quantity WHERE PartID = $part_id";
                $conn->query($update_inventory_sql);
                
            } else {
                $insert_record_sql = "INSERT INTO StockTransaction (TransactionType, Quantity, TransactionDate, Notes, PartID, SupplierID, UserID, ReferenceNumber) VALUES ($TransactionType, $Quantity, $TransactionDate, $Notes, $PartID, $SupplierID, $UserID, $ReferenceNumber)";
                $conn->query($insert_record_sql);
                
                $transaction_quantity = (int)$_POST['Quantity'];
                $part_id = (int)$_POST['PartID'];
                
                $check_inventory_sql = "SELECT * FROM Inventory WHERE PartID = $part_id";
                $inventory_check_result = $conn->query($check_inventory_sql);
                
                if ($inventory_check_result) {
                    if ($inventory_check_result->num_rows > 0) {
                        $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand + $transaction_quantity WHERE PartID = $part_id";
                        $conn->query($update_inventory_sql);
                    } else {
                        $initial_quantity = $transaction_quantity;
                        $insert_inventory_sql = "INSERT INTO Inventory (PartID, QuantityOnHand, ReservedQuantity) VALUES ($part_id, $initial_quantity, 0)";
                        $conn->query($insert_inventory_sql);
                    }
                }
            }
            
            header("Location: stocktransaction.php");
            exit;
        }
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
    // Search across multiple columns using the LIKE operator
    $where_sql = " WHERE (" . "StockTransaction.TransactionID LIKE '%$search_keyword%'" . " OR " . "StockTransaction.TransactionType LIKE '%$search_keyword%'" . " OR " . "Part.PartName LIKE '%$search_keyword%'" . " OR " . "Supplier.SupplierName LIKE '%$search_keyword%'" . " OR " . "Users.FullName LIKE '%$search_keyword%'" . " OR " . "StockTransaction.ReferenceNumber LIKE '%$search_keyword%'" . " OR " . "StockTransaction.Notes LIKE '%$search_keyword%'" . ")";
}

// Combine query parts and fetch the final results for the table
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
            // Change form title depending on whether we are editing or creating
            if (isset($edit_row)) {
                echo 'Edit Record';
            } else {
                echo 'Add New Record';
            }
            ?>
            </h3>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-msg" style="color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 12px; border-radius: 5px; margin-bottom: 20px; font-weight: bold;">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php
                // Hidden input to pass the ID if we are editing
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
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['Quantity']);
                    }
                    ?>
                    <input type="text" name="Quantity" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>Notes</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
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
                            if (isset($edit_row)) {
                                if ($edit_row['PartID'] == $item['PartID']) {
                                    $selected_attribute = "selected";
                            }
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
                            if (isset($edit_row)) {
                                if ($edit_row['SupplierID'] == $item['SupplierID']) {
                                    $selected_attribute = "selected";
                            }
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
                    if (isset($edit_row)) {
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
                        <th>TransactionID</th><th>TransactionType</th><th>Quantity</th><th>TransactionDate</th><th>Notes</th><th>PartName</th><th>SupplierName</th><th>Handled By</th><th>ReferenceNumber</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Display the database records inside the HTML table
                    if ($result) {
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                ?>
                                <tr>
                                    
                            <?php
                            $table_cell_value = "";
                            if (isset($row['TransactionID'])) {
                                $table_cell_value = htmlspecialchars($row['TransactionID']);
                        }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['TransactionType'])) {
                                $table_cell_value = htmlspecialchars($row['TransactionType']);
                        }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['Quantity'])) {
                                $table_cell_value = htmlspecialchars($row['Quantity']);
                        }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['TransactionDate'])) {
                                $table_cell_value = htmlspecialchars($row['TransactionDate']);
                        }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['Notes'])) {
                                $table_cell_value = htmlspecialchars($row['Notes']);
                        }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['PartName'])) {
                                $table_cell_value = htmlspecialchars($row['PartName']);
                        }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['SupplierName'])) {
                                $table_cell_value = htmlspecialchars($row['SupplierName']);
                        }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['FullName'])) {
                                $table_cell_value = htmlspecialchars($row['FullName']);
                        }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['ReferenceNumber'])) {
                                $table_cell_value = htmlspecialchars($row['ReferenceNumber']);
                        }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
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
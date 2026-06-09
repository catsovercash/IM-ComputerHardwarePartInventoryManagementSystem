<?php

// Start session to verify the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// Connect to database
include 'db.php';



// --- CHECK IF EDITING ---
// If 'edit' is in the URL, fetch the record so we can fill the form
$edit_row = null;
if (isset($_GET['edit'])) {
    $id_to_edit = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM Supplier WHERE SupplierID = $id_to_edit";
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
    if ("Supplier" === "StockTransaction") {
        $get_old_transaction_sql = "SELECT * FROM StockTransaction WHERE SupplierID = $id_to_delete";
        $transaction_result = $conn->query($get_old_transaction_sql);
        if ($transaction_result) {
            $old_transaction = $transaction_result->fetch_assoc();
            if ($old_transaction) {
                $transaction_type = $old_transaction['TransactionType'];
                $transaction_quantity = (int)$old_transaction['Quantity'];
                $part_id = (int)$old_transaction['PartID'];
                
                // If we delete a Sale, we get the parts back (add them)
                if ($transaction_type === 'Sale') {
                    $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand + $transaction_quantity WHERE PartID = $part_id";
                    $conn->query($update_inventory_sql);
                } else {
                    // If we delete a Receipt, we lose the parts (subtract them)
                    if ($transaction_type === 'Receipt' || $transaction_type === 'Adjustment') {
                        $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand - $transaction_quantity WHERE PartID = $part_id";
                        $conn->query($update_inventory_sql);
                    }
                }
            }
        }
    }
    
    // Perform the actual deletion
    $delete_query = "DELETE FROM Supplier WHERE SupplierID = $id_to_delete";
    $conn->query($delete_query);
    
    // Reload the page
    header("Location: supplier.php"); 
    exit;
}

// --- CHECK IF SAVING/UPDATING FORM ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Grab form inputs securely
    $safe_value = $conn->real_escape_string($_POST['SupplierName']);
    $SupplierName = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['PhoneNumber']);
    $PhoneNumber = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['SupplierEmail']);
    $SupplierEmail = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['SupplierAddress']);
    $SupplierAddress = "'" . $safe_value . "'";
    $UserID = $_SESSION['user_id']; // Automatically assign the logged-in user


    // If 'update_id' exists, we are UPDATING an old record
    if (isset($_POST['update_id']) && $_POST['update_id'] != '') {
        $id_to_update = (int)$_POST['update_id'];
        
        // Before updating a stock transaction, reverse its old math from the inventory
        if ("Supplier" === "StockTransaction") {
            $get_old_transaction_sql = "SELECT * FROM StockTransaction WHERE SupplierID = $id_to_update";
            $transaction_result = $conn->query($get_old_transaction_sql);
            if ($transaction_result) {
                $old_transaction = $transaction_result->fetch_assoc();
                if ($old_transaction) {
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
            }
        }
        
        // Update the actual record
        $update_record_sql = "UPDATE Supplier SET SupplierName = $SupplierName, PhoneNumber = $PhoneNumber, SupplierEmail = $SupplierEmail, SupplierAddress = $SupplierAddress, UserID = $UserID WHERE SupplierID = $id_to_update";
        $conn->query($update_record_sql);
        
        // Apply the NEW inventory math for the updated transaction
        if ("Supplier" === "StockTransaction") {
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
        }
        
    // If 'update_id' is empty, we are INSERTING a new record
    } else {
        // Create the record
        $insert_record_sql = "INSERT INTO Supplier (SupplierName, PhoneNumber, SupplierEmail, SupplierAddress, UserID) VALUES ($SupplierName, $PhoneNumber, $SupplierEmail, $SupplierAddress, $UserID)";
        $conn->query($insert_record_sql);
        
        // Apply inventory math for the brand new transaction
        if ("Supplier" === "StockTransaction") {
            $transaction_type = $_POST['TransactionType'];
            $transaction_quantity = (int)$_POST['Quantity'];
            $part_id = (int)$_POST['PartID'];
            
            $check_inventory_sql = "SELECT * FROM Inventory WHERE PartID = $part_id";
            $inventory_check_result = $conn->query($check_inventory_sql);
            
            if ($inventory_check_result) {
                // If the part is already in the inventory table, update its quantity
                if ($inventory_check_result->num_rows > 0) {
                    if ($transaction_type === 'Sale') {
                        $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand - $transaction_quantity WHERE PartID = $part_id";
                        $conn->query($update_inventory_sql);
                    } else {
                        $update_inventory_sql = "UPDATE Inventory SET QuantityOnHand = QuantityOnHand + $transaction_quantity WHERE PartID = $part_id";
                        $conn->query($update_inventory_sql);
                    }
                } else {
                    // If the part is NOT in the inventory yet, create a new row for it
                    $initial_quantity = 0;
                    if ($transaction_type === 'Sale') {
                        $initial_quantity = -$transaction_quantity;
                    } else {
                        $initial_quantity = $transaction_quantity;
                    }
                    $insert_inventory_sql = "INSERT INTO Inventory (PartID, QuantityOnHand, ReservedQuantity) VALUES ($part_id, $initial_quantity, 0)";
                    $conn->query($insert_inventory_sql);
                }
            }
        }
    }
    
    // Reload the page
    header("Location: supplier.php");
    exit;
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
    $where_sql = " WHERE (" . "SupplierID LIKE '%$search_keyword%'" . " OR " . "SupplierName LIKE '%$search_keyword%'" . " OR " . "PhoneNumber LIKE '%$search_keyword%'" . " OR " . "SupplierEmail LIKE '%$search_keyword%'" . " OR " . "SupplierAddress LIKE '%$search_keyword%'" . " OR " . "UserID LIKE '%$search_keyword%'" . ")";
}

// Combine query parts and fetch the final results for the table
$final_query = "SELECT * FROM Supplier" . $where_sql . "";
$result = $conn->query($final_query);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Supplier - KompyuTek</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
    <div class="sidebar">
        <h2>KompyuTek</h2>
        <a href="index.php">Dashboard</a>
        <?php
        $category_nav_class = ""; if ("Supplier" == "Category") $category_nav_class = "active";
        $manufacturer_nav_class = ""; if ("Supplier" == "Manufacturer") $manufacturer_nav_class = "active";
        $users_nav_class = ""; if ("Supplier" == "Users") $users_nav_class = "active";
        $supplier_nav_class = ""; if ("Supplier" == "Supplier") $supplier_nav_class = "active";
        $part_nav_class = ""; if ("Supplier" == "Part") $part_nav_class = "active";
        $inventory_nav_class = ""; if ("Supplier" == "Inventory") $inventory_nav_class = "active";
        $stock_nav_class = ""; if ("Supplier" == "StockTransaction") $stock_nav_class = "active";
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
                <h1>Supplier</h1>
                <p>Manage and track your supplier records here.</p>
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
            <form method="POST">
                <?php
                // Hidden input to pass the ID if we are editing
                $hidden_id = "";
                if (isset($edit_row)) {
                    $hidden_id = $edit_row['SupplierID'];
                }
                ?>
                <input type="hidden" name="update_id" value="<?= $hidden_id ?>">
                
                
                <div class="form-group">
                    <label>SupplierName</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['SupplierName']);
                    }
                    ?>
                    <input type="text" name="SupplierName" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>PhoneNumber</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['PhoneNumber']);
                    }
                    ?>
                    <input type="text" name="PhoneNumber" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>SupplierEmail</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['SupplierEmail']);
                    }
                    ?>
                    <input type="text" name="SupplierEmail" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>SupplierAddress</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['SupplierAddress']);
                    }
                    ?>
                    <input type="text" name="SupplierAddress" value="<?= $input_value ?>" required>
                </div>
            
                
                <div class="form-group form-actions">
                    <?php if(isset($edit_row)): ?>
                    <a href="supplier.php" class="btn-cancel">Cancel</a>
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
                <a href="supplier.php"><button type="button" class="btn-outline btn-clear">Clear</button></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card table-card">
            <table>
                <thead>
                    <tr>
                        <th>SupplierID</th><th>SupplierName</th><th>PhoneNumber</th><th>SupplierEmail</th><th>SupplierAddress</th><th>UserID</th>
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
                            if (isset($row['SupplierID'])) {
                                $table_cell_value = htmlspecialchars($row['SupplierID']);
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
                            if (isset($row['PhoneNumber'])) {
                                $table_cell_value = htmlspecialchars($row['PhoneNumber']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['SupplierEmail'])) {
                                $table_cell_value = htmlspecialchars($row['SupplierEmail']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['SupplierAddress'])) {
                                $table_cell_value = htmlspecialchars($row['SupplierAddress']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['UserID'])) {
                                $table_cell_value = htmlspecialchars($row['UserID']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                                    <td class="action-cell">
                                        <a href="?edit=<?= $row['SupplierID'] ?>"><button class="btn-outline btn-sm mr-sm">Edit</button></a>
                                        <a href="?delete=<?= $row['SupplierID'] ?>" onclick="return confirm('Are you sure you want to delete this record?')"><button class="btn-danger btn-sm">Delete</button></a>
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
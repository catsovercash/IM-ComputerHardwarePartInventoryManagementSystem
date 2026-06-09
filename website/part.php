<?php

// Start session to verify the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// Connect to database
include 'db.php';


// Fetch categories for the dropdown menu
$categories = array();
$category_sql = 'SELECT CategoryID, CategoryName FROM Category';
$category_result = $conn->query($category_sql);
if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch manufacturers for the dropdown menu
$manufacturers = array();
$manufacturer_sql = 'SELECT ManufacturerID, ManufacturerName FROM Manufacturer';
$manufacturer_result = $conn->query($manufacturer_sql);
if ($manufacturer_result) {
    while ($row = $manufacturer_result->fetch_assoc()) {
        $manufacturers[] = $row;
    }
}


// --- CHECK IF EDITING ---
// If 'edit' is in the URL, fetch the record so we can fill the form
$edit_row = null;
if (isset($_GET['edit'])) {
    $id_to_edit = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM Part WHERE PartID = $id_to_edit";
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
    if ("Part" === "StockTransaction") {
        $get_old_transaction_sql = "SELECT * FROM StockTransaction WHERE PartID = $id_to_delete";
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
    $delete_query = "DELETE FROM Part WHERE PartID = $id_to_delete";
    $conn->query($delete_query);
    
    // Reload the page
    header("Location: part.php"); 
    exit;
}

// --- CHECK IF SAVING/UPDATING FORM ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Grab form inputs securely
    $safe_value = $conn->real_escape_string($_POST['SKU']);
    $SKU = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['PartName']);
    $PartName = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['ModelNumber']);
    $ModelNumber = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['CategoryID']);
    $CategoryID = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['ManufacturerID']);
    $ManufacturerID = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['Description']);
    $Description = "'" . $safe_value . "'";
    // Handle Price safely
    if (empty($_POST['Price'])) {
        $Price = 'NULL';
    } else {
        $safe_value = $conn->real_escape_string($_POST['Price']);
        $Price = "'" . $safe_value . "'";
    }


    // If 'update_id' exists, we are UPDATING an old record
    if (isset($_POST['update_id']) && $_POST['update_id'] != '') {
        $id_to_update = (int)$_POST['update_id'];
        
        // Before updating a stock transaction, reverse its old math from the inventory
        if ("Part" === "StockTransaction") {
            $get_old_transaction_sql = "SELECT * FROM StockTransaction WHERE PartID = $id_to_update";
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
        $update_record_sql = "UPDATE Part SET SKU = $SKU, PartName = $PartName, ModelNumber = $ModelNumber, CategoryID = $CategoryID, ManufacturerID = $ManufacturerID, Description = $Description, Price = $Price WHERE PartID = $id_to_update";
        $conn->query($update_record_sql);
        
        // Apply the NEW inventory math for the updated transaction
        if ("Part" === "StockTransaction") {
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
        $insert_record_sql = "INSERT INTO Part (SKU, PartName, ModelNumber, CategoryID, ManufacturerID, Description, Price) VALUES ($SKU, $PartName, $ModelNumber, $CategoryID, $ManufacturerID, $Description, $Price)";
        $conn->query($insert_record_sql);
        
        // Apply inventory math for the brand new transaction
        if ("Part" === "StockTransaction") {
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
    header("Location: part.php");
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
    $where_sql = " WHERE (" . "Part.PartName LIKE '%$search_keyword%'" . " OR " . "Part.SKU LIKE '%$search_keyword%'" . " OR " . "Part.ModelNumber LIKE '%$search_keyword%'" . " OR " . "Category.CategoryName LIKE '%$search_keyword%'" . " OR " . "Manufacturer.ManufacturerName LIKE '%$search_keyword%'" . " OR " . "Part.Description LIKE '%$search_keyword%'" . ")";
}

// Combine query parts and fetch the final results for the table
$final_query = "SELECT Part.*, Category.CategoryName, Manufacturer.ManufacturerName FROM Part LEFT JOIN Category ON Part.CategoryID = Category.CategoryID LEFT JOIN Manufacturer ON Part.ManufacturerID = Manufacturer.ManufacturerID" . $where_sql . "";
$result = $conn->query($final_query);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Part - KompyuTek</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
    <div class="sidebar">
        <h2>KompyuTek</h2>
        <a href="index.php">Dashboard</a>
        <?php
        $category_nav_class = ""; if ("Part" == "Category") $category_nav_class = "active";
        $manufacturer_nav_class = ""; if ("Part" == "Manufacturer") $manufacturer_nav_class = "active";
        $users_nav_class = ""; if ("Part" == "Users") $users_nav_class = "active";
        $supplier_nav_class = ""; if ("Part" == "Supplier") $supplier_nav_class = "active";
        $part_nav_class = ""; if ("Part" == "Part") $part_nav_class = "active";
        $inventory_nav_class = ""; if ("Part" == "Inventory") $inventory_nav_class = "active";
        $stock_nav_class = ""; if ("Part" == "StockTransaction") $stock_nav_class = "active";
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
                <h1>Part</h1>
                <p>Manage and track your part records here.</p>
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
                    $hidden_id = $edit_row['PartID'];
                }
                ?>
                <input type="hidden" name="update_id" value="<?= $hidden_id ?>">
                
                
                <div class="form-group">
                    <label>SKU</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['SKU']);
                    }
                    ?>
                    <input type="text" name="SKU" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>PartName</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['PartName']);
                    }
                    ?>
                    <input type="text" name="PartName" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>ModelNumber</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['ModelNumber']);
                    }
                    ?>
                    <input type="text" name="ModelNumber" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>Category</label>
                    <select name="CategoryID" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach($categories as $item): ?>
                            <?php
                            $selected_attribute = "";
                            if (isset($edit_row)) {
                                if ($edit_row['CategoryID'] == $item['CategoryID']) {
                                    $selected_attribute = "selected";
                                }
                            }
                            ?>
                            <option value="<?= $item['CategoryID'] ?>" <?= $selected_attribute ?>><?= htmlspecialchars($item['CategoryName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            
                <div class="form-group">
                    <label>Manufacturer</label>
                    <select name="ManufacturerID" required>
                        <option value="">-- Select Manufacturer --</option>
                        <?php foreach($manufacturers as $item): ?>
                            <?php
                            $selected_attribute = "";
                            if (isset($edit_row)) {
                                if ($edit_row['ManufacturerID'] == $item['ManufacturerID']) {
                                    $selected_attribute = "selected";
                                }
                            }
                            ?>
                            <option value="<?= $item['ManufacturerID'] ?>" <?= $selected_attribute ?>><?= htmlspecialchars($item['ManufacturerName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            
                <div class="form-group">
                    <label>Description</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['Description']);
                    }
                    ?>
                    <input type="text" name="Description" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>Price</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['Price']);
                    }
                    ?>
                    <input type="text" name="Price" value="<?= $input_value ?>" required>
                </div>
            
                
                <div class="form-group form-actions">
                    <?php if(isset($edit_row)): ?>
                    <a href="part.php" class="btn-cancel">Cancel</a>
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
                <a href="part.php"><button type="button" class="btn-outline btn-clear">Clear</button></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card table-card">
            <table>
                <thead>
                    <tr>
                        <th>PartID</th><th>SKU</th><th>PartName</th><th>ModelNumber</th><th>CategoryName</th><th>ManufacturerName</th><th>Description</th><th>Price</th>
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
                            if (isset($row['PartID'])) {
                                $table_cell_value = htmlspecialchars($row['PartID']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['SKU'])) {
                                $table_cell_value = htmlspecialchars($row['SKU']);
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
                            if (isset($row['ModelNumber'])) {
                                $table_cell_value = htmlspecialchars($row['ModelNumber']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['CategoryName'])) {
                                $table_cell_value = htmlspecialchars($row['CategoryName']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['ManufacturerName'])) {
                                $table_cell_value = htmlspecialchars($row['ManufacturerName']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['Description'])) {
                                $table_cell_value = htmlspecialchars($row['Description']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['Price'])) {
                                $table_cell_value = htmlspecialchars($row['Price']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                                    <td class="action-cell">
                                        <a href="?edit=<?= $row['PartID'] ?>"><button class="btn-outline btn-sm mr-sm">Edit</button></a>
                                        <a href="?delete=<?= $row['PartID'] ?>" onclick="return confirm('Are you sure you want to delete this record?')"><button class="btn-danger btn-sm">Delete</button></a>
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
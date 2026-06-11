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
    $edit_query = "SELECT * FROM Inventory WHERE InventoryID = $id_to_edit";
    $edit_result = $conn->query($edit_query);
    if ($edit_result) {
        $edit_row = $edit_result->fetch_assoc();
    }
}

// --- CHECK IF DELETING ---
// If 'delete' is in the URL, delete the record
if (isset($_GET['delete'])) {
    $id_to_delete = (int)$_GET['delete'];
    
    $delete_query = "DELETE FROM Inventory WHERE InventoryID = $id_to_delete";
    try {
        $conn->query($delete_query);
        header("Location: inventory.php"); 
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
    
    // Grab form inputs securely
    $safe_value = $conn->real_escape_string($_POST['PartID']);
    $PartID = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['QuantityOnHand']);
    $QuantityOnHand = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['ReservedQuantity']);
    $ReservedQuantity = "'" . $safe_value . "'";

    // Validate PartID uniqueness
    $part_id_check = (int)$_POST['PartID'];
    $update_id_check = 0;
    if (isset($_POST['update_id']) && $_POST['update_id'] != '') {
        $update_id_check = (int)$_POST['update_id'];
    }
    
    $part_check_sql = "SELECT InventoryID FROM Inventory WHERE PartID = $part_id_check AND InventoryID != $update_id_check";
    $part_check_res = $conn->query($part_check_sql);
    
    if ($part_check_res && $part_check_res->num_rows > 0) {
        $error_message = "Error: This Part already has an inventory record. Please edit the existing record instead of adding a new one.";
    }

    if (empty($error_message)) {
        // If 'update_id' exists, we are UPDATING an old record
        if (isset($_POST['update_id']) && $_POST['update_id'] != '') {
            $id_to_update = (int)$_POST['update_id'];
            
            $update_record_sql = "UPDATE Inventory SET PartID = $PartID, QuantityOnHand = $QuantityOnHand, ReservedQuantity = $ReservedQuantity WHERE InventoryID = $id_to_update";
            $conn->query($update_record_sql);
            
        // If 'update_id' is empty, we are INSERTING a new record
        } else {
            $insert_record_sql = "INSERT INTO Inventory (PartID, QuantityOnHand, ReservedQuantity) VALUES ($PartID, $QuantityOnHand, $ReservedQuantity)";
            $conn->query($insert_record_sql);
        }
        
        // Reload the page
        header("Location: inventory.php");
        exit;
    }
}

// --- HANDLE SEARCH BAR & FILTERS ---
$search_query = '';
$filter_category = '';
$filter_manufacturer = '';

if (isset($_GET['search'])) {
    $search_query = $_GET['search'];
}
if (isset($_GET['category_id'])) {
    $filter_category = $_GET['category_id'];
}
if (isset($_GET['manufacturer_id'])) {
    $filter_manufacturer = $_GET['manufacturer_id'];
}

$search_keyword = $conn->real_escape_string($search_query);
$cat_id_safe = $conn->real_escape_string($filter_category);
$mfg_id_safe = $conn->real_escape_string($filter_manufacturer);

$where_conditions = [];

if ($search_keyword != '') {
    $where_conditions[] = "(" . "Inventory.InventoryID LIKE '%$search_keyword%'" . " OR " . "Part.PartName LIKE '%$search_keyword%'" . " OR " . "Inventory.QuantityOnHand LIKE '%$search_keyword%'" . " OR " . "Category.CategoryName LIKE '%$search_keyword%'" . " OR " . "Manufacturer.ManufacturerName LIKE '%$search_keyword%'" . ")";
}
if ($cat_id_safe != '') {
    $where_conditions[] = "Part.CategoryID = '$cat_id_safe'";
}
if ($mfg_id_safe != '') {
    $where_conditions[] = "Part.ManufacturerID = '$mfg_id_safe'";
}

$where_sql = "";
if (count($where_conditions) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_conditions);
}

// Combine query parts and fetch the final results for the table
$final_query = "SELECT Inventory.*, Part.PartName, Category.CategoryName, Manufacturer.ManufacturerName 
                FROM Inventory 
                LEFT JOIN Part ON Inventory.PartID = Part.PartID
                LEFT JOIN Category ON Part.CategoryID = Category.CategoryID
                LEFT JOIN Manufacturer ON Part.ManufacturerID = Manufacturer.ManufacturerID" . $where_sql . "";
$result = $conn->query($final_query);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Inventory - KompyuTek</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
    <div class="sidebar">
        <h2>KompyuTek</h2>
        <a href="index.php">Dashboard</a>
        <?php
        $category_nav_class = ""; if ("Inventory" == "Category") $category_nav_class = "active";
        $manufacturer_nav_class = ""; if ("Inventory" == "Manufacturer") $manufacturer_nav_class = "active";
        $users_nav_class = ""; if ("Inventory" == "Users") $users_nav_class = "active";
        $supplier_nav_class = ""; if ("Inventory" == "Supplier") $supplier_nav_class = "active";
        $part_nav_class = ""; if ("Inventory" == "Part") $part_nav_class = "active";
        $inventory_nav_class = ""; if ("Inventory" == "Inventory") $inventory_nav_class = "active";
        $stock_nav_class = ""; if ("Inventory" == "StockTransaction") $stock_nav_class = "active";
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
                <h1>Inventory</h1>
                <p>Manage and track your inventory records here.</p>
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
                    $hidden_id = $edit_row['InventoryID'];
                }
                ?>
                <input type="hidden" name="update_id" value="<?= $hidden_id ?>">
                
                
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
                    <label>QuantityOnHand</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['QuantityOnHand']);
                    }
                    ?>
                    <input type="text" name="QuantityOnHand" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>ReservedQuantity</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['ReservedQuantity']);
                    }
                    ?>
                    <input type="text" name="ReservedQuantity" value="<?= $input_value ?>" required>
                </div>
            
                
                <div class="form-group form-actions">
                    <?php if(isset($edit_row)): ?>
                    <a href="inventory.php" class="btn-cancel">Cancel</a>
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
            <form method="GET" class="search-form" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <select name="category_id" class="search-input" style="flex: 1; min-width: 150px;">
                    <option value="">-- All Categories --</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['CategoryID'] ?>" <?= ($filter_category == $cat['CategoryID']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['CategoryName']) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select name="manufacturer_id" class="search-input" style="flex: 1; min-width: 150px;">
                    <option value="">-- All Manufacturers --</option>
                    <?php foreach($manufacturers as $mfg): ?>
                        <option value="<?= $mfg['ManufacturerID'] ?>" <?= ($filter_manufacturer == $mfg['ManufacturerID']) ? 'selected' : '' ?>><?= htmlspecialchars($mfg['ManufacturerName']) ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search_query) ?>" class="search-input" style="flex: 2; min-width: 200px;">
                <button type="submit" class="btn-search">Filter</button>
                <?php if($search_query != '' || $filter_category != '' || $filter_manufacturer != ''): ?>
                <a href="inventory.php"><button type="button" class="btn-outline btn-clear">Clear</button></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card table-card">
            <table>
                <thead>
                    <tr>
                        <th>InventoryID</th><th>PartName</th><th>Category</th><th>Manufacturer</th><th>QuantityOnHand</th><th>ReservedQuantity</th>
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
                            if (isset($row['InventoryID'])) {
                                $table_cell_value = htmlspecialchars($row['InventoryID']);
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
                            if (isset($row['QuantityOnHand'])) {
                                $table_cell_value = htmlspecialchars($row['QuantityOnHand']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['ReservedQuantity'])) {
                                $table_cell_value = htmlspecialchars($row['ReservedQuantity']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                                    <td class="action-cell">
                                        <a href="?edit=<?= $row['InventoryID'] ?>"><button class="btn-outline btn-sm mr-sm">Edit</button></a>
                                        <a href="?delete=<?= $row['InventoryID'] ?>" onclick="return confirm('Are you sure you want to delete this record?')"><button class="btn-danger btn-sm">Delete</button></a>
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
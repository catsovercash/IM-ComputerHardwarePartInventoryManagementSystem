<?php

// Start session to verify the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// Connect to database
include 'db.php';



$error_message = '';
// --- CHECK IF EDITING ---
// If 'edit' is in the URL, fetch the record so we can fill the form
$edit_row = null;
if (isset($_GET['edit'])) {
    $id_to_edit = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM Users WHERE UserID = $id_to_edit";
    $edit_result = $conn->query($edit_query);
    if ($edit_result) {
        $edit_row = $edit_result->fetch_assoc();
    }
}

// --- CHECK IF DELETING ---
// If 'delete' is in the URL, delete the record
if (isset($_GET['delete'])) {
    $id_to_delete = (int)$_GET['delete'];
    
    $delete_query = "DELETE FROM Users WHERE UserID = $id_to_delete";
    try {
        $conn->query($delete_query);
        header("Location: users.php"); 
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
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Grab form inputs securely
    $safe_value = $conn->real_escape_string($_POST['Username']);
    $Username = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['UserPassword']);
    $UserPassword = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['FullName']);
    $FullName = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['Role']);
    $Role = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['Email']);
    $Email = "'" . $safe_value . "'";
    $safe_value = $conn->real_escape_string($_POST['IsActive']);
    $IsActive = "'" . $safe_value . "'";


    // If 'update_id' exists, we are UPDATING an old record
    if (isset($_POST['update_id']) && $_POST['update_id'] != '') {
        $id_to_update = (int)$_POST['update_id'];
        
        // Update the actual record
        $update_record_sql = "UPDATE Users SET Username = $Username, UserPassword = $UserPassword, FullName = $FullName, Role = $Role, Email = $Email, IsActive = $IsActive WHERE UserID = $id_to_update";
        $conn->query($update_record_sql);
        
        
    // If 'update_id' is empty, we are INSERTING a new record
    } else {
        // Create the record
        $insert_record_sql = "INSERT INTO Users (Username, UserPassword, FullName, Role, Email, IsActive) VALUES ($Username, $UserPassword, $FullName, $Role, $Email, $IsActive)";
        $conn->query($insert_record_sql);
        
    }
    
    // Reload the page
    header("Location: users.php");
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
    $where_sql = " WHERE (" . "Username LIKE '%$search_keyword%'" . " OR " . "FullName LIKE '%$search_keyword%'" . " OR " . "Role LIKE '%$search_keyword%'" . " OR " . "Email LIKE '%$search_keyword%'" . ")";
}

// Combine query parts and fetch the final results for the table
$final_query = "SELECT * FROM Users" . $where_sql . "";
$result = $conn->query($final_query);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Users - KompyuTek</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
    <div class="sidebar">
        <h2>KompyuTek</h2>
        <a href="index.php">Dashboard</a>
        <?php
        $category_nav_class = ""; if ("Users" == "Category") $category_nav_class = "active";
        $manufacturer_nav_class = ""; if ("Users" == "Manufacturer") $manufacturer_nav_class = "active";
        $users_nav_class = ""; if ("Users" == "Users") $users_nav_class = "active";
        $supplier_nav_class = ""; if ("Users" == "Supplier") $supplier_nav_class = "active";
        $part_nav_class = ""; if ("Users" == "Part") $part_nav_class = "active";
        $inventory_nav_class = ""; if ("Users" == "Inventory") $inventory_nav_class = "active";
        $stock_nav_class = ""; if ("Users" == "StockTransaction") $stock_nav_class = "active";
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
                <h1>Users</h1>
                <p>Manage and track your users records here.</p>
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
                    $hidden_id = $edit_row['UserID'];
                }
                ?>
                <input type="hidden" name="update_id" value="<?= $hidden_id ?>">
                
                
                <div class="form-group">
                    <label>Username</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['Username']);
                    }
                    ?>
                    <input type="text" name="Username" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>UserPassword</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['UserPassword']);
                    }
                    ?>
                    <input type="text" name="UserPassword" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>FullName</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['FullName']);
                    }
                    ?>
                    <input type="text" name="FullName" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>Role</label>
                    <select name="Role" required>
                        <?php
                        $admin_selected = "";
                        $staff_selected = "";
                        if (isset($edit_row)) {
                            if ($edit_row['Role'] == 'Admin') { $admin_selected = "selected"; }
                            if ($edit_row['Role'] == 'Staff') { $staff_selected = "selected"; }
                        }
                        ?>
                        <option value="Admin" <?= $admin_selected ?>>Admin</option>
                        <option value="Staff" <?= $staff_selected ?>>Staff</option>
                    </select>
                </div>
            
                <div class="form-group">
                    <label>Email</label>
                    <?php
                    $input_value = "";
                    if (isset($edit_row)) {
                        $input_value = htmlspecialchars($edit_row['Email']);
                    }
                    ?>
                    <input type="text" name="Email" value="<?= $input_value ?>" required>
                </div>
            
                <div class="form-group">
                    <label>Is Active</label>
                    <select name="IsActive" required>
                        <?php
                        $active_yes_selected = "";
                        $active_no_selected = "";
                        if (isset($edit_row)) {
                            if ($edit_row['IsActive'] == 1) { $active_yes_selected = "selected"; }
                            if ($edit_row['IsActive'] == 0) { $active_no_selected = "selected"; }
                        }
                        ?>
                        <option value="1" <?= $active_yes_selected ?>>Yes</option>
                        <option value="0" <?= $active_no_selected ?>>No</option>
                    </select>
                </div>
            
                
                <div class="form-group form-actions">
                    <?php if(isset($edit_row)): ?>
                    <a href="users.php" class="btn-cancel">Cancel</a>
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
                <a href="users.php"><button type="button" class="btn-outline btn-clear">Clear</button></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card table-card">
            <table>
                <thead>
                    <tr>
                        <th>UserID</th><th>Username</th><th>UserPassword</th><th>FullName</th><th>Role</th><th>Email</th><th>IsActive</th>
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
                            if (isset($row['UserID'])) {
                                $table_cell_value = htmlspecialchars($row['UserID']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['Username'])) {
                                $table_cell_value = htmlspecialchars($row['Username']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['UserPassword'])) {
                                $table_cell_value = htmlspecialchars($row['UserPassword']);
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
                            if (isset($row['Role'])) {
                                $table_cell_value = htmlspecialchars($row['Role']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['Email'])) {
                                $table_cell_value = htmlspecialchars($row['Email']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                            <?php
                            $table_cell_value = "";
                            if (isset($row['IsActive'])) {
                                $table_cell_value = htmlspecialchars($row['IsActive']);
                            }
                            ?>
                            <td><?= $table_cell_value ?></td>
        
                                    <td class="action-cell">
                                        <a href="?edit=<?= $row['UserID'] ?>"><button class="btn-outline btn-sm mr-sm">Edit</button></a>
                                        <a href="?delete=<?= $row['UserID'] ?>" onclick="return confirm('Are you sure you want to delete this record?')"><button class="btn-danger btn-sm">Delete</button></a>
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

<aside class="sidebar">
        <div class="logo">Rifat Enterprise</div>

        <ul class="nav-menu">
            <?php
                if($_SESSION['user_role'] === 'admin'){
                    echo `<a href="/dashboard"><li class="nav-item "><span>Dashboard</span></li></a>
                            <a href="/dashboard/production_management.php"><li class="nav-item active"><span>Production Management</span></li></a>
                            <a href="/dashboard/raw_material_management.php"><li class="nav-item"><span>Raw Material Inventory</span></li></a>
                            <a href="/dashboard/sales_management.php"><li class="nav-item"><span>Sales Management</span></li></a>
                            <a href="/dashboard/suppliers.php"><li class="nav-item"><span>Suppliers</span></li></a>
                            <a href="/dashboard/reports.php"><li class="nav-item"><span>Analysis</span></li></a>
                            <a href="/dashboard/settings.php"><li class="nav-item"><span>Settings</span></li></a>`;
                }else{
                    $permissions = json_decode($_SESSION['permissions'], true);

                    $menu_items = [
                        "dashboard" => "Dashboard",
                        "production_management" => "Production Management",
                        "raw_material_management" => "Raw Material Inventory",
                        "sales_management" => "Sales Management",
                        "suppliers" => "Suppliers",
                        "reports" => "Reports",
                        "settings" => "Settings"
                    ];

                    foreach ($menu_items as $page => $label) {
                        if (in_array($page, $permissions, true)) {
                            $active_class = ($_SERVER['PHP_SELF'] === "/dashboard/{$page}.php") ? 'active' : '';
                            echo "<a href=\"/dashboard/{$page}.php\"><li class=\"nav-item {$active_class}\"><span>{$label}</span></li></a>";
                        }
                    }
                }
            ?>
        </ul>
    </aside>

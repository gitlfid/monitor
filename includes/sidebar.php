<?php
// Mendapatkan nama file saat ini untuk deteksi menu aktif
$current_page = basename($_SERVER['PHP_SELF']); 

// Style link (Active & Inactive) menggunakan Tailwind
$active_link_style = "bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 shadow-sm ring-1 ring-indigo-200 dark:ring-transparent font-semibold";
$inactive_link_style = "text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-white font-medium";

// Helper icon aktif
function getIconClass($page_name, $current_page, $icon_name) {
    $fill = ($current_page == $page_name) ? 'ph-fill' : '';
    return "ph {$fill} {$icon_name} text-xl";
}
?>

<aside id="sidebar" class="group relative z-50 flex h-screen w-0 flex-col overflow-y-hidden bg-white dark:bg-[#1A222C] duration-300 ease-in-out lg:w-[280px] border-r border-slate-100 dark:border-slate-800 -translate-x-full lg:translate-x-0 absolute lg:static">
    
    <div class="flex items-center justify-between gap-2 px-6 pt-8 pb-6 lg:pt-10 lg:pb-8">
        <a href="index.php" class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/20">
                <i class="ph ph-lightning text-2xl"></i>
            </div>
            <span class="text-xl font-bold text-slate-800 dark:text-white duration-300 group-[.is-collapsed]:opacity-0 group-[.is-collapsed]:hidden">
                LinksField
            </span>
        </a>
        <button id="closeSidebarMobile" class="block lg:hidden text-slate-500 hover:text-indigo-600">
            <i class="ph ph-x text-2xl"></i>
        </button>
    </div>

    <div class="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear pb-10">
        <nav class="mt-2 px-4 lg:px-6">
            
            <h3 class="mb-4 ml-4 text-xs font-bold text-slate-400 uppercase tracking-wider group-[.is-collapsed]:hidden">Menu</h3>
            <ul class="flex flex-col gap-2 mb-6">
                <li>
                    <a href="index.php" class="relative flex items-center gap-3 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'index.php') ? $active_link_style : $inactive_link_style; ?>">
                        <i class="<?php echo getIconClass('index.php', $current_page, 'ph-squares-four'); ?>"></i>
                        <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Dashboard</span>
                    </a>
                </li>
            </ul>

            <h3 class="mb-4 ml-4 text-xs font-bold text-slate-400 uppercase tracking-wider group-[.is-collapsed]:hidden">Manage</h3>
            <ul class="flex flex-col gap-2 mb-6">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    
                    <li>
                        <a href="manage_companies.php" class="relative flex items-center gap-3 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage_companies.php') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('manage_companies.php', $current_page, 'ph-buildings'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Manage Client</span>
                        </a>
                    </li>

                    <?php $sim_active = strpos($current_page, 'sim_tracking') !== false; ?>
                    <li>
                        <button onclick="document.getElementById('submenu-sim').classList.toggle('hidden')" class="w-full relative flex items-center justify-between gap-3 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo $sim_active ? $active_link_style : $inactive_link_style; ?>">
                            <div class="flex items-center gap-3">
                                <i class="ph <?php echo $sim_active ? 'ph-fill' : ''; ?> ph-map-pin text-xl"></i>
                                <span class="group-[.is-collapsed]:hidden whitespace-nowrap">SIM Tracking</span>
                            </div>
                            <i class="ph ph-caret-down group-[.is-collapsed]:hidden"></i>
                        </button>
                        <ul id="submenu-sim" class="<?php echo $sim_active ? '' : 'hidden'; ?> mt-2 flex flex-col gap-1 pl-11 pr-4 group-[.is-collapsed]:hidden">
                            <li><a href="sim_tracking_client_po.php" class="block rounded-lg px-3 py-2 text-sm transition-colors <?php echo ($current_page == 'sim_tracking_client_po.php') ? 'text-indigo-600 font-bold dark:text-indigo-400' : 'text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-white'; ?>">Client PO</a></li>
                            <li><a href="sim_tracking_provider_po.php" class="block rounded-lg px-3 py-2 text-sm transition-colors <?php echo ($current_page == 'sim_tracking_provider_po.php') ? 'text-indigo-600 font-bold dark:text-indigo-400' : 'text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-white'; ?>">Provider PO</a></li>
                            <li><a href="sim_tracking_receive.php" class="block rounded-lg px-3 py-2 text-sm transition-colors <?php echo ($current_page == 'sim_tracking_receive.php') ? 'text-indigo-600 font-bold dark:text-indigo-400' : 'text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-white'; ?>">Receive & Delivery</a></li>
                            <li><a href="sim_tracking_status.php" class="block rounded-lg px-3 py-2 text-sm transition-colors <?php echo ($current_page == 'sim_tracking_status.php') ? 'text-indigo-600 font-bold dark:text-indigo-400' : 'text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-white'; ?>">Activation & Termination</a></li>
                        </ul>
                    </li>

                    <li>
                        <a href="manage_users.php" class="relative flex items-center gap-3 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage_users.php') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('manage_users.php', $current_page, 'ph-users'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Manage Users</span>
                        </a>
                    </li>
                    <li>
                        <a href="test_email.php" class="relative flex items-center gap-3 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'test_email.php') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('test_email.php', $current_page, 'ph-envelope-simple'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Test Email</span>
                        </a>
                    </li>
                    <li>
                        <a href="manage_api_keys.php" class="relative flex items-center gap-3 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage_api_keys.php') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('manage_api_keys.php', $current_page, 'ph-key'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Manage API</span>
                        </a>
                    </li>

                <?php endif; ?>
            </ul>

            <h3 class="mb-4 ml-4 text-xs font-bold text-slate-400 uppercase tracking-wider group-[.is-collapsed]:hidden">Operations</h3>
            <ul class="flex flex-col gap-2">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li>
                        <a href="upload_csv.php" class="relative flex items-center gap-3 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'upload_csv.php') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('upload_csv.php', $current_page, 'ph-upload-simple'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Upload Report</span>
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <a href="injection_calendar.php" class="relative flex items-center gap-3 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'injection_calendar.php') ? $active_link_style : $inactive_link_style; ?>">
                        <i class="<?php echo getIconClass('injection_calendar.php', $current_page, 'ph-calendar-check'); ?>"></i>
                        <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Injection Calendar</span>
                    </a>
                </li>
                <li>
                    <a href="sim_information.php" class="relative flex items-center gap-3 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'sim_information.php') ? $active_link_style : $inactive_link_style; ?>">
                        <i class="<?php echo getIconClass('sim_information.php', $current_page, 'ph-sim-card'); ?>"></i>
                        <span class="group-[.is-collapsed]:hidden whitespace-nowrap">SIM Information</span>
                    </a>
                </li>
                <li>
                    <a href="delivery_information.php" class="relative flex items-center gap-3 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'delivery_information.php') ? $active_link_style : $inactive_link_style; ?>">
                        <i class="<?php echo getIconClass('delivery_information.php', $current_page, 'ph-truck'); ?>"></i>
                        <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Delivery Info</span>
                    </a>
                </li>
            </ul>

        </nav>
    </div>
</aside>
<div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
    
    <header class="sticky top-0 z-40 flex w-full bg-white/80 backdrop-blur-md dark:bg-[#1A222C]/80 shadow-soft transition-all duration-300 border-b border-slate-100 dark:border-slate-800">
        <div class="flex flex-grow items-center justify-between px-4 py-4 md:px-6 2xl:px-11 h-20">
            
            <div class="flex items-center gap-4 sm:gap-6">
                <button id="sidebarToggle" class="z-50 block rounded-lg p-2 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 cursor-pointer transition-colors">
                     <i class="ph ph-list text-2xl"></i>
                </button>
            </div>

            <div class="flex items-center gap-3 2xsm:gap-6">
                <ul class="flex items-center gap-2">
                     <li>
                        <button id="darkModeToggle" class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-all">
                            <i class="ph ph-moon text-xl dark:hidden"></i>
                            <i class="ph ph-sun text-xl hidden dark:block"></i>
                        </button>
                    </li>
                </ul>

                <div class="relative">
                    <div id="profileBtn" class="flex items-center gap-3 cursor-pointer pl-4 border-l border-slate-100 dark:border-slate-700 transition-colors">
                        <span class="hidden text-right lg:block">
                            <span class="block text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                            <span class="block text-xs font-medium text-slate-400"><?= ucfirst($_SESSION['role'] ?? 'Member') ?></span>
                        </span>
                        <div class="h-11 w-11 rounded-full overflow-hidden border-2 border-white dark:border-slate-700 ring-2 ring-slate-100 dark:ring-slate-800 shadow-sm transition-all group-hover:ring-indigo-100">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username'] ?? 'User') ?>&background=random" alt="User" class="object-cover w-full h-full">
                        </div>
                        <i class="ph ph-caret-down text-slate-400 text-sm hidden lg:block"></i>
                    </div>

                    <div id="profileDropdown" class="hidden absolute right-0 mt-4 flex w-56 flex-col rounded-xl border border-slate-100 dark:border-slate-700 bg-white dark:bg-[#24303F] shadow-lg z-50 overflow-hidden transition-all origin-top-right">
                        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                            <p class="text-sm font-bold text-slate-800 dark:text-white">Hello, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></p>
                        </div>
                        <ul class="flex flex-col gap-1 px-3 py-2">
                            <li>
                                <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 hover:text-indigo-600 dark:hover:text-white transition-colors">
                                    <i class="ph ph-user text-lg"></i> My Profile
                                </a>
                            </li>
                        </ul>
                        <div class="p-3 border-t border-slate-100 dark:border-slate-700">
                             <a href="logout.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-bold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                <i class="ph ph-sign-out text-lg"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 p-4 md:p-6 lg:p-8">
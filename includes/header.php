<?php
require_once 'auth_check.php'; 
require_once 'config.php';
require_once 'functions.php';

// Ambil nama page untuk active state sidebar
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" class="antialiased">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Dashboard</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class', // Mendukung Dark Mode berbasis class
            theme: {
                extend: {
                    fontFamily: { 
                        sans: ['Inter', 'sans-serif'] 
                    },
                    boxShadow: {
                        'soft': '0 4px 6px -1px rgba(99, 102, 241, 0.05), 0 2px 4px -2px rgba(99, 102, 241, 0.05)',
                        'soft-lg': '0 10px 15px -3px rgba(99, 102, 241, 0.08), 0 4px 6px -4px rgba(99, 102, 241, 0.08)',
                    }
                }
            }
        }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        /* Fallback styling untuk plugin / hardcode warna status sebelumnya */
        .badge.bg-success { background-color: #10b981 !important; color: white; }
        .badge.bg-danger { background-color: #ef4444 !important; color: white; }
        .badge.bg-warning { background-color: #f59e0b !important; color: white; }

        /* Utility Sembunyikan Scrollbar tapi tetap bisa di-scroll */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>

<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 transition-colors duration-300">
    
    <div class="flex h-screen overflow-hidden">
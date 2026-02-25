</main> <footer class="mt-auto px-6 py-4 border-t border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-[#1A222C]/50 backdrop-blur-sm transition-colors duration-300">
            <div class="flex flex-col md:flex-row justify-between items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                <p>
                    &copy; <?php echo date("Y"); ?> 
                    <span class="font-semibold text-slate-700 dark:text-slate-300">PT. Linksfield Networks Indonesia</span>. All rights reserved.
                </p>
                <p class="flex items-center gap-1">
                    Crafted with <i class="ph-fill ph-heart text-red-500 text-base"></i> by Tim IT
                </p>
            </div>
        </footer>

    </div> </div> <script src="assets/extensions/jquery/jquery.min.js"></script>

<script src="assets/js/main.js"></script>

<script src="assets/extensions/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>

<?php
if (isset($page_scripts)) {
    echo $page_scripts;
}
?>

</body>
</html>
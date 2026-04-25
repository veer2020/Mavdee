</div><!-- /.page-content -->
</main><!-- /#main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Toast notification container -->
<div id="toast-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');

        // Prevent body scroll when sidebar is open on mobile
        if (sidebar.classList.contains('show')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    function closeSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    // Close sidebar when clicking on a link (mobile)
    document.querySelectorAll('#sidebar a').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                closeSidebar();
            }
        });
    });

    // Auto-hide flash after 4 seconds
    document.addEventListener('DOMContentLoaded', function() {
        var flash = document.querySelector('.flash-alert');
        if (flash) {
            setTimeout(function() {
                var bsAlert = bootstrap.Alert.getOrCreateInstance(flash);
                if (bsAlert) bsAlert.close();
            }, 4000);
        }

        // Image preview from URL inputs
        document.querySelectorAll('input[data-preview]').forEach(function(inp) {
            var targetId = inp.dataset.preview;

            function updatePreview() {
                var img = document.getElementById(targetId);
                if (!img) return;
                var val = inp.value.trim();
                img.src = val || '';
                img.style.display = val ? 'block' : 'none';
            }
            inp.addEventListener('input', updatePreview);
            updatePreview();
        });

        // Fix for sidebar toggle button on mobile
        var toggleBtn = document.getElementById('sidebarToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleSidebar);
        }

        // Close sidebar when pressing Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });
    });
</script>
</body>

</html>
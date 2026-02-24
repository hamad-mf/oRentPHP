</div><!-- end page content -->
</main>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        $('select.select2').select2({ width: '100%', theme: 'default' });
    });

    // ── Theme Toggle ──────────────────────────────────────
    const root = document.getElementById('html-root');

    function applyThemeIcons() {
        const isLight = root.classList.contains('light-mode');
        document.getElementById('icon-moon').style.display = isLight ? 'none' : 'block';
        document.getElementById('icon-sun').style.display = isLight ? 'block' : 'none';
    }

    function toggleTheme() {
        const isLight = root.classList.toggle('light-mode');
        localStorage.setItem('theme', isLight ? 'light' : 'dark');
        applyThemeIcons();
    }

    document.addEventListener('DOMContentLoaded', applyThemeIcons);
</script>

<?php if (isset($extraScripts))
    echo $extraScripts; ?>
</body>

</html>
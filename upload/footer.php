<?php
if(isset($_SESSION['pending_activation'])) {
    echo '</body></html>';
    exit();
}
?>
</main>
<footer class="text-center text-muted py-4 mt-4 border-top">
    <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?></p>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {

    // --- Select2 Initializer Function ---
    // This function ensures all comboboxes are initialized correctly.
    function initializeSelect2() {
        $('.select2').each(function() {
            // If the element is already a Select2 instance, destroy it first to avoid conflicts.
            if ($(this).data('select2')) {
                $(this).select2('destroy');
            }
            
            // Initialize the element with the Bootstrap 5 theme.
            // `dropdownParent` helps prevent issues where the dropdown might be hidden inside other elements (like modals or tabs).
            $(this).select2({
                theme: 'bootstrap-5',
                dropdownParent: $(this).parent()
            });
        });
    }

    // --- Theme Toggler Logic ---
    const themeToggleBtn = document.getElementById('theme-toggle');
    if(themeToggleBtn) {
        const themeIcon = themeToggleBtn.querySelector('i');
        const htmlElement = document.documentElement;

        const setTheme = (theme) => {
            htmlElement.setAttribute('data-bs-theme', theme);
            themeIcon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
            
            // Re-initialize Select2 whenever the theme changes to apply the correct dark/light styling.
            initializeSelect2();
        };
        // Set the initial theme when the page loads.
        setTheme(htmlElement.getAttribute('data-bs-theme'));

        themeToggleBtn.addEventListener('click', () => {
            const newTheme = htmlElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
            fetch('api.php?action=update_theme', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ theme: newTheme })
            }).catch(error => console.error('Error updating theme:', error));
        });
    }

    // Initial call to set up all Select2 comboboxes on the page.
    initializeSelect2();
});
</script>
</body>
</html>

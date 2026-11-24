<footer style="text-align: center; padding: 20px; margin-top: 50px; color: #64748b;">
        <p>&copy; <?php echo date("Y"); ?> <?php echo APP_NAME; ?>. Welljoel | BI23110056.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuIcon = document.getElementById('menu-icon');
            const navLinks = document.getElementById('nav-links');

            if(menuIcon && navLinks) {
                menuIcon.addEventListener('click', () => {
                    navLinks.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>
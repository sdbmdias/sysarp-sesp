<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Lógica do submenu para desktop
        const adminMenuToggle = document.getElementById('admin-menu-toggle');
        const adminSubmenu = document.getElementById('admin-submenu');
        const adminMenuItem = document.getElementById('admin-menu');

        if (adminMenuToggle && adminSubmenu) {
            const adminPages = ['listar_relprev.php', 'cadastro_aeronaves.php', 'cadastro_pilotos.php', 'cadastro_controles.php', 'cadastro_modelos.php', 'cadastro_crbm_obm.php', 'cadastro_tipos_ocorrencia.php', 'gerenciar_documentos.php', 'alertas.php'];
            const currentPage = window.location.pathname.split('/').pop();

            if (adminPages.includes(currentPage)) {
                adminMenuItem.classList.add('open');
                adminSubmenu.classList.add('open');
                adminSubmenu.style.maxHeight = adminSubmenu.scrollHeight + "px"; // Ajuste para altura correta
            }

            adminMenuToggle.addEventListener('click', function(event) {
                event.preventDefault();
                adminMenuItem.classList.toggle('open');
                if (adminMenuItem.classList.contains('open')) {
                    adminSubmenu.style.maxHeight = adminSubmenu.scrollHeight + "px";
                } else {
                    adminSubmenu.style.maxHeight = '0';
                }
            });
        }

        // Lógica do menu mobile
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');

        if (menuToggle && sidebar && overlay) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.add('open');
                overlay.classList.add('active');
            });

            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            });
        }

        // Atualiza o título da página no header mobile
        const pageTitleElement = document.querySelector('.main-content h1');
        const mobileTitleElement = document.querySelector('.mobile-header .page-title');
        if (pageTitleElement && mobileTitleElement) {
            mobileTitleElement.textContent = pageTitleElement.textContent.trim();
        }
    });
</script>
</body>
</html>
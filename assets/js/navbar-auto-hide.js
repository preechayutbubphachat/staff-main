(function (window, document) {
    function initNavbarAutoHide(options) {
        const config = options || {};
        const navbar = document.querySelector(config.selector || '.app-navbar');
        if (!navbar || navbar.dataset.autoHideBound === '1') {
            return;
        }

        navbar.dataset.autoHideBound = '1';

        let lastScrollY = window.scrollY || 0;
        let ticking = false;
        const deltaThreshold = 10;
        const topThreshold = 24;

        function shouldKeepVisible() {
            return (window.scrollY || 0) <= topThreshold
                || !!navbar.querySelector('.dropdown-menu.show')
                || !!navbar.querySelector('.navbar-collapse.show')
                || !!document.querySelector('.modal.show');
        }

        function setState(visible, scrolled) {
            navbar.classList.toggle('navbar-hidden', !visible);
            navbar.classList.toggle('navbar-scrolled', !!scrolled);
        }

        function updateNavbar() {
            const currentScrollY = window.scrollY || 0;
            const delta = currentScrollY - lastScrollY;

            if (shouldKeepVisible()) {
                setState(true, currentScrollY > topThreshold);
                lastScrollY = currentScrollY;
                ticking = false;
                return;
            }

            if (Math.abs(delta) < deltaThreshold) {
                ticking = false;
                return;
            }

            if (delta > 0) {
                setState(false, true);
            } else {
                setState(true, true);
            }

            lastScrollY = currentScrollY;
            ticking = false;
        }

        function requestUpdate() {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(updateNavbar);
        }

        window.addEventListener('scroll', requestUpdate, { passive: true });
        window.addEventListener('resize', requestUpdate);
        document.addEventListener('shown.bs.dropdown', requestUpdate);
        document.addEventListener('hidden.bs.dropdown', requestUpdate);
        document.addEventListener('shown.bs.modal', requestUpdate);
        document.addEventListener('hidden.bs.modal', requestUpdate);

        updateNavbar();
    }

    window.NavbarAutoHide = {
        init: initNavbarAutoHide
    };
})(window, document);

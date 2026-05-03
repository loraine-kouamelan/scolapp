(function () {
    function isMobile()
    {
        return window.matchMedia && window.matchMedia('(max-width: 980px)').matches;
    }

    function ensureTableScroll(root)
    {
        var scope = root || document;
        if (!scope.querySelectorAll) {
            return;
        }
        var tables = scope.querySelectorAll('table');
        for (var i = 0; i < tables.length; i++) {
            var table = tables[i];
            if (!table || !table.parentElement) {
                continue;
            }
            if (table.parentElement.classList && table.parentElement.classList.contains('table-scroll')) {
                continue;
            }
            var wrapper = document.createElement('div');
            wrapper.className = 'table-scroll';
            table.parentElement.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    }

    function syncDetailsLabels(root)
    {
        var scope = root || document;
        var detailsList = scope.querySelectorAll ? scope.querySelectorAll('details') : [];
        for (var i = 0; i < detailsList.length; i++) {
            (function (d) {
                function apply()
                {
                    var lbl = d.querySelector ? d.querySelector('[data-details-label]') : null;
                    if (!lbl) {
                        return;
                    }
                    lbl.textContent = d.open ? 'Fermer' : 'Voir';
                }
                apply();
                d.addEventListener('toggle', apply);
            })(detailsList[i]);
        }
    }

    function ensureDefaultDesktopState()
    {
        if (!isMobile()) {
            document.body.classList.add('sidebar-collapsed');
        }
    }

    function closeMobile()
    {
        document.body.classList.remove('sidebar-mobile-open');
    }

    function toggleSidebar()
    {
        if (isMobile()) {
            document.body.classList.toggle('sidebar-mobile-open');
        } else {
            document.body.classList.toggle('sidebar-collapsed');
        }
    }

    document.addEventListener('click', function (e) {
        var t = e.target;
        var btn = t && t.closest ? t.closest('.sidebar-toggle') : null;
        if (btn) {
            e.preventDefault();
            toggleSidebar();
            return;
        }

        if (document.body.classList.contains('sidebar-mobile-open')) {
            var sidebar = document.querySelector('.sidebar');
            if (sidebar && !sidebar.contains(t)) {
                closeMobile();
            }
        }
    });

    window.addEventListener('resize', function () {
        if (!isMobile()) {
            closeMobile();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeMobile();
        }
    });

    ensureDefaultDesktopState();
    syncDetailsLabels();
    ensureTableScroll();
})();

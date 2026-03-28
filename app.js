(function(){
    function isMobile(){
        return window.matchMedia && window.matchMedia('(max-width: 980px)').matches;
    }

    function ensureDefaultDesktopState(){
        if(!isMobile()){
            document.body.classList.add('sidebar-collapsed');
        }
    }

    function closeMobile(){
        document.body.classList.remove('sidebar-mobile-open');
    }

    function toggleSidebar(){
        if(isMobile()){
            document.body.classList.toggle('sidebar-mobile-open');
        } else {
            document.body.classList.toggle('sidebar-collapsed');
        }
    }

    document.addEventListener('click', function(e){
        var t = e.target;
        var btn = t && t.closest ? t.closest('.sidebar-toggle') : null;
        if(btn){
            e.preventDefault();
            toggleSidebar();
            return;
        }

        if(document.body.classList.contains('sidebar-mobile-open')){
            var sidebar = document.querySelector('.sidebar');
            if(sidebar && !sidebar.contains(t)){
                closeMobile();
            }
        }
    });

    window.addEventListener('resize', function(){
        if(!isMobile()){
            closeMobile();
        }
    });

    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){
            closeMobile();
        }
    });

    ensureDefaultDesktopState();
})();

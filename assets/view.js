(function () {
    function toggleBlock(button) {
        var targetId = button.getAttribute('data-target');
        if (!targetId) {
            return;
        }

        var wrapper = document.getElementById(targetId);
        if (!wrapper) {
            return;
        }

        var isCollapsed = wrapper.classList.contains('is-collapsed');
        if (isCollapsed) {
            wrapper.classList.remove('is-collapsed');
            button.setAttribute('aria-expanded', 'true');
            var labelOpen = button.querySelector('.fftt-parties-toggle-label');
            if (labelOpen) {
                labelOpen.textContent = 'Masquer les rencontres';
            }
        } else {
            wrapper.classList.add('is-collapsed');
            button.setAttribute('aria-expanded', 'false');
            var labelClosed = button.querySelector('.fftt-parties-toggle-label');
            if (labelClosed) {
                labelClosed.textContent = 'Voir les autres rencontres';
            }
        }
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.fftt-parties-toggle');
        if (!button) {
            return;
        }

        event.preventDefault();
        toggleBlock(button);
    });
})();

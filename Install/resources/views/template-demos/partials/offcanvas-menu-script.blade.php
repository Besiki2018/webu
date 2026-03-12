<script>
    (function () {
        'use strict';

        function panels() {
            return Array.prototype.slice.call(document.querySelectorAll('[data-webu-offcanvas]'));
        }

        function syncPanelState(panel, open) {
            if (!(panel instanceof HTMLElement)) {
                return;
            }

            panel.classList.toggle('is-open', open);
            const panelId = panel.getAttribute('data-webu-offcanvas') || '';
            Array.prototype.slice.call(document.querySelectorAll('[data-webu-offcanvas-trigger]')).forEach((trigger) => {
                if (!(trigger instanceof HTMLElement)) {
                    return;
                }
                if ((trigger.getAttribute('data-webu-offcanvas-trigger') || '') !== panelId) {
                    return;
                }
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        function openPanel(panelId) {
            panels().forEach((panel) => {
                syncPanelState(panel, (panel.getAttribute('data-webu-offcanvas') || '') === panelId);
            });
        }

        function closePanels() {
            panels().forEach((panel) => {
                syncPanelState(panel, false);
            });
        }

        document.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const trigger = target.closest('[data-webu-offcanvas-trigger]');
            if (trigger instanceof HTMLElement) {
                const panelId = trigger.getAttribute('data-webu-offcanvas-trigger') || '';
                if (panelId !== '') {
                    event.preventDefault();
                    const panel = document.querySelector('[data-webu-offcanvas="' + CSS.escape(panelId) + '"]');
                    const willOpen = !(panel instanceof HTMLElement && panel.classList.contains('is-open'));
                    if (willOpen) {
                        openPanel(panelId);
                    } else {
                        closePanels();
                    }
                }
                return;
            }

            if (target.closest('[data-webu-offcanvas-close]')) {
                event.preventDefault();
                closePanels();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closePanels();
            }
        });
    })();
</script>

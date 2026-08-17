<style id="og-sidebar-hover-expand">
    @media (min-width: 1024px) {
        /* Keep sidebar in document flow so expanding it pushes content, not overlays it. */
        body.fi-panel-admin .fi-main-sidebar {
            position: sticky !important;
            top: 0 !important;
            inset-inline-start: auto !important;
            height: 100vh !important;
            width: var(--collapsed-sidebar-width) !important;
            min-width: var(--collapsed-sidebar-width) !important;
            max-width: var(--collapsed-sidebar-width) !important;
            overflow: hidden !important;
            flex-shrink: 0 !important;
            z-index: 20 !important;
        }

        body.fi-panel-admin .fi-main-ctn {
            flex: 1 1 0% !important;
            width: auto !important;
            min-width: 0 !important;
        }

        body.fi-panel-admin .fi-main-sidebar > div,
        body.fi-panel-admin .fi-main-sidebar .fi-sidebar-nav {
            overflow: hidden !important;
        }

        /* Collapsed: hide all text regardless of Alpine / fi-sidebar-open state. */
        body.fi-panel-admin .fi-main-sidebar:not(.sidebar-hover-expanded) .fi-sidebar-item-label,
        body.fi-panel-admin .fi-main-sidebar:not(.sidebar-hover-expanded) .fi-sidebar-group-label,
        body.fi-panel-admin .fi-main-sidebar:not(.sidebar-hover-expanded) .fi-sidebar-group-button,
        body.fi-panel-admin .fi-main-sidebar:not(.sidebar-hover-expanded) .fi-sidebar-group-collapse-button,
        body.fi-panel-admin .fi-main-sidebar:not(.sidebar-hover-expanded) .fi-sidebar-header > div:first-child,
        body.fi-panel-admin .fi-main-sidebar:not(.sidebar-hover-expanded) .fi-sidebar-item-button .fi-badge {
            display: none !important;
        }

        body.fi-panel-admin .fi-main-sidebar:not(.sidebar-hover-expanded) .fi-sidebar-header {
            justify-content: center;
            padding-inline: 0.5rem;
        }

        body.fi-panel-admin .fi-main-sidebar:not(.sidebar-hover-expanded) .fi-sidebar-nav {
            padding-inline: 0.75rem;
        }

        body.fi-panel-admin .fi-sidebar-header .fi-icon-btn {
            display: none !important;
        }

        /* Expanded on hover: grow within flex layout and push main content. */
        body.fi-panel-admin .fi-main-sidebar.sidebar-hover-expanded {
            width: var(--sidebar-width) !important;
            min-width: var(--sidebar-width) !important;
            max-width: var(--sidebar-width) !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
            background-color: rgb(255 255 255) !important;
            box-shadow:
                0 0 0 1px rgb(0 0 0 / 0.05),
                4px 0 12px -2px rgb(0 0 0 / 0.08);
        }

        html.dark body.fi-panel-admin .fi-main-sidebar.sidebar-hover-expanded {
            background-color: rgb(17 24 39) !important;
            box-shadow:
                0 0 0 1px rgb(255 255 255 / 0.08),
                4px 0 12px -2px rgb(0 0 0 / 0.35);
        }

        body.fi-panel-admin .fi-main-sidebar.sidebar-hover-expanded > div,
        body.fi-panel-admin .fi-main-sidebar.sidebar-hover-expanded .fi-sidebar-nav {
            overflow-x: hidden !important;
            overflow-y: auto !important;
        }

        body.fi-panel-admin .fi-main-sidebar.sidebar-hover-expanded .fi-sidebar-item-label {
            display: block !important;
        }

        body.fi-panel-admin .fi-main-sidebar.sidebar-hover-expanded .fi-sidebar-group-button {
            display: flex !important;
        }

        body.fi-panel-admin .fi-main-sidebar.sidebar-hover-expanded .fi-sidebar-group-collapse-button,
        body.fi-panel-admin .fi-main-sidebar.sidebar-hover-expanded .fi-sidebar-header > div:first-child {
            display: inline-flex !important;
        }

        body.fi-panel-admin .fi-main-sidebar,
        body.fi-panel-admin .fi-main-ctn {
            transition:
                width 0.2s ease-in-out,
                min-width 0.2s ease-in-out,
                max-width 0.2s ease-in-out,
                flex-basis 0.2s ease-in-out;
        }
    }
</style>

<script>
    ;(function () {
        function isDesktop() {
            return window.matchMedia('(min-width: 1024px)').matches
        }

        function collapseAlpineSidebar() {
            if (window.Alpine && isDesktop()) {
                window.Alpine.store('sidebar').close()
            }
        }

        function bindSidebarHover() {
            const sidebar = document.querySelector('body.fi-panel-admin .fi-main-sidebar')

            if (!sidebar || sidebar.dataset.ogHoverBound === 'true') {
                return
            }

            sidebar.dataset.ogHoverBound = 'true'

            sidebar.addEventListener('mouseenter', () => {
                if (!isDesktop()) {
                    return
                }

                sidebar.classList.add('sidebar-hover-expanded')
                window.Alpine?.store('sidebar').open()
            })

            sidebar.addEventListener('mouseleave', () => {
                sidebar.classList.remove('sidebar-hover-expanded')

                if (isDesktop()) {
                    window.Alpine?.store('sidebar').close()
                }
            })

            collapseAlpineSidebar()
        }

        document.addEventListener('alpine:initialized', () => {
            collapseAlpineSidebar()
            bindSidebarHover()
        })

        document.addEventListener('livewire:navigated', () => {
            bindSidebarHover()
            collapseAlpineSidebar()
        })

        if (document.readyState !== 'loading') {
            bindSidebarHover()
        } else {
            document.addEventListener('DOMContentLoaded', bindSidebarHover)
        }

        window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
            const sidebar = document.querySelector('body.fi-panel-admin .fi-main-sidebar')

            if (!sidebar) {
                return
            }

            if (event.matches) {
                sidebar.classList.remove('sidebar-hover-expanded')
                collapseAlpineSidebar()
            } else {
                window.Alpine?.store('sidebar').open()
            }
        })
    })()
</script>

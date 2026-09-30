@once
    @push('styles')
        <style>
            /* Content cards in Project & Milestone workspaces */
            .project-content-card {
                background-color: #ffffff;
                border: 1px solid #e9ecef;
                border-radius: 12px;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
            }

            /* Stat cards */
            .project-stat-card {
                border-radius: 12px !important;
                box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
                transition: transform 0.15s ease, box-shadow 0.15s ease;
            }
            .project-stat-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
            }

            /* Details Accordion */
            .project-details-accordion .accordion-item {
                border: 1px solid #e9ecef;
                border-radius: 12px;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
                overflow: hidden;
            }
            .project-details-accordion .accordion-button {
                background-color: #f8fafc;
                border-left: 3px solid var(--bs-primary);
            }
            .project-details-accordion .accordion-body {
                background-color: #ffffff;
                border-top: 1px solid #e9ecef;
            }

            /* Dark Mode Adaptations */
            html.app-skin-dark .project-content-card {
                background-color: #121a2d !important;
                border-color: #1b2436 !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4) !important;
                color: #cbd5e1 !important;
            }
            html.app-skin-dark .project-content-card .text-dark {
                color: #f1f5f9 !important;
            }
            html.app-skin-dark .project-content-card .text-muted {
                color: #94a3b8 !important;
            }
            html.app-skin-dark .project-content-card .border-bottom,
            html.app-skin-dark .project-content-card .border-top {
                border-color: #1b2436 !important;
            }
            html.app-skin-dark .project-content-card .bg-light {
                background-color: #1c2438 !important;
                border-color: #1b2436 !important;
            }

            html.app-skin-dark .project-stat-card {
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3) !important;
            }
            html.app-skin-dark .project-stat-card.border {
                background-color: #121a2d !important;
                border-color: #1b2436 !important;
            }
            html.app-skin-dark .project-stat-card .text-dark {
                color: #f1f5f9 !important;
            }
            html.app-skin-dark .project-stat-card .text-muted {
                color: #94a3b8 !important;
            }
            html.app-skin-dark .project-stat-card.bg-soft-primary {
                background-color: rgba(52, 84, 209, 0.15) !important;
                border-color: rgba(52, 84, 209, 0.4) !important;
            }
            html.app-skin-dark .project-stat-card.bg-soft-success {
                background-color: rgba(23, 198, 102, 0.15) !important;
                border-color: rgba(23, 198, 102, 0.4) !important;
            }
            html.app-skin-dark .project-stat-card.bg-soft-info {
                background-color: rgba(61, 199, 190, 0.15) !important;
                border-color: rgba(61, 199, 190, 0.4) !important;
            }
            html.app-skin-dark .project-stat-card.bg-soft-warning {
                background-color: rgba(255, 162, 29, 0.15) !important;
                border-color: rgba(255, 162, 29, 0.4) !important;
            }

            html.app-skin-dark .project-details-accordion .accordion-item {
                background-color: #121a2d !important;
                border-color: #1b2436 !important;
            }
            html.app-skin-dark .project-details-accordion .accordion-button {
                background-color: #162038 !important;
                color: #f1f5f9 !important;
                border-color: #1b2436 !important;
            }
            html.app-skin-dark .project-details-accordion .accordion-button:not(.collapsed) {
                background-color: #1c2438 !important;
                color: #3454d1 !important;
            }
            html.app-skin-dark .project-details-accordion .accordion-body {
                background-color: #121a2d !important;
                border-top: 1px solid #1b2436 !important;
                color: #cbd5e1 !important;
            }
            html.app-skin-dark .project-details-accordion .accordion-body .text-dark {
                color: #f1f5f9 !important;
            }
            html.app-skin-dark .project-details-accordion .accordion-body .text-muted {
                color: #94a3b8 !important;
            }
            html.app-skin-dark .project-details-accordion .accordion-body .border-top {
                border-color: #1b2436 !important;
            }

            html.app-skin-dark .milestone-icon-badge {
                background-color: #1c2438 !important;
                border-color: #1b2436 !important;
                color: #94a3b8 !important;
            }
            html.app-skin-dark .metadata-divider {
                border-left-color: #1b2436 !important;
            }
            html.app-skin-dark .collaborator-add-btn {
                background-color: #1c2438 !important;
                border-color: #121a2d !important;
                color: #94a3b8 !important;
            }
        </style>
    @endpush
@endonce

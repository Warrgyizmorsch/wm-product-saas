/**
 * SaaS ERP - Web-to-Lead Embed & Floating Widget
 * Seamless Lead Generation integration for any website (Next.js, React, WordPress, PHP, HTML).
 * Version: 2.1.0
 */
(function () {
    'use strict';

    // Helper: Find exact script element with data attributes
    function findScriptTag() {
        if (document.currentScript && document.currentScript.src && document.currentScript.src.includes('lead-widget.js')) {
            return document.currentScript;
        }
        const targeted = document.querySelector('script[src*="lead-widget.js"]');
        if (targeted) return targeted;
        const withData = document.querySelector('script[data-tenant]');
        if (withData) return withData;
        const scripts = document.getElementsByTagName('script');
        for (let i = scripts.length - 1; i >= 0; i--) {
            if (scripts[i].src && scripts[i].src.includes('lead-widget.js')) {
                return scripts[i];
            }
        }
        return scripts[scripts.length - 1] || document.currentScript;
    }

    const currentScript = findScriptTag();
    const scriptSrc = currentScript ? currentScript.src : '';
    let hostOrigin = '';
    try {
        const urlObj = new URL(scriptSrc, window.location.href);
        hostOrigin = urlObj.origin;
    } catch (e) {
        hostOrigin = window.location.origin;
    }

    // Detect custom color attribute (ignoring legacy blue placeholder #4f46e5)
    let rawColor = currentScript?.getAttribute('data-color') || '';
    if (rawColor === '#4f46e5' || rawColor === '%234f46e5') {
        rawColor = '';
    }

    // Button label
    const rawBtnText = currentScript?.getAttribute('data-button-text') || 'Enquire Now';
    const cleanBtnText = rawBtnText.replace(/^[💬\s]+/, '').trim() || 'Enquire Now';

    const config = {
        tenant: currentScript?.getAttribute('data-tenant') || '1',
        company: currentScript?.getAttribute('data-company') || currentScript?.getAttribute('data-company-id') || '',
        branch: currentScript?.getAttribute('data-branch') || currentScript?.getAttribute('data-branch-id') || '',
        origin: hostOrigin,
        floating: currentScript?.getAttribute('data-floating') !== 'false',
        position: currentScript?.getAttribute('data-position') || 'bottom-right',
        offsetX: currentScript?.getAttribute('data-offset-x') || '',
        offsetY: currentScript?.getAttribute('data-offset-y') || '',
        buttonText: cleanBtnText,
        color: rawColor,
        theme: currentScript?.getAttribute('data-theme') || '',
        title: currentScript?.getAttribute('data-title') || '',
        subtitle: currentScript?.getAttribute('data-subtitle') || '',
    };

    // Helper: Build Form URL with tracking
    function buildFormUrl(opts) {
        opts = opts || {};
        const params = new URLSearchParams();
        const theme = opts.theme || config.theme;
        if (theme) params.set('theme', theme);

        const color = opts.color || config.color;
        if (color && color !== '#4f46e5') params.set('color', color);

        const title = opts.title || config.title;
        if (title) params.set('title', title);

        const subtitle = opts.subtitle || config.subtitle;
        if (subtitle) params.set('subtitle', subtitle);

        params.set('source', opts.source || 'Website Widget');

        const companyId = opts.company || opts.company_id || config.company;
        if (companyId) params.set('company_id', companyId);

        const branchId = opts.branch || opts.branch_id || config.branch;
        if (branchId) params.set('branch_id', branchId);

        // Pass host page UTM parameters
        try {
            const hostParams = new URLSearchParams(window.location.search);
            ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(k => {
                if (hostParams.has(k)) params.set(k, hostParams.get(k));
            });
        } catch (e) { }

        const tenantParam = opts.tenant || config.tenant;
        const queryStr = params.toString();
        return `${config.origin}/forms/lead-capture/${encodeURIComponent(tenantParam)}${queryStr ? '?' + queryStr : ''}`;
    }

    // 1. Initialize Inline Widgets
    function initInlineWidgets() {
        const containers = document.querySelectorAll('#crm-lead-widget, .crm-lead-widget, [data-crm-lead-form]');
        containers.forEach(container => {
            if (container.dataset.crmInitialized) return;
            container.dataset.crmInitialized = 'true';

            const tenant = container.getAttribute('data-tenant') || config.tenant;
            const company = container.getAttribute('data-company') || container.getAttribute('data-company-id') || config.company;
            const branch = container.getAttribute('data-branch') || container.getAttribute('data-branch-id') || config.branch;
            const theme = container.getAttribute('data-theme') || config.theme;
            const color = container.getAttribute('data-color') || config.color;
            const title = container.getAttribute('data-title') || config.title;
            const subtitle = container.getAttribute('data-subtitle') || config.subtitle;

            const iframeUrl = buildFormUrl({ tenant, company, branch, theme, color, title, subtitle, source: 'Inline Website Embed' });

            const iframe = document.createElement('iframe');
            iframe.src = iframeUrl;
            iframe.style.width = '100%';
            iframe.style.minHeight = '520px';
            iframe.style.border = 'none';
            iframe.style.borderRadius = '16px';
            iframe.style.overflow = 'hidden';
            iframe.loading = 'lazy';
            iframe.title = 'Contact Form';

            container.innerHTML = '';
            container.appendChild(iframe);
        });
    }

    // 2. Initialize Floating Button & Slide-up Popup Widget
    function initFloatingWidget() {
        if (!config.floating) return;

        // If existing root exists, remove old one to prevent stale duplicate
        const existingRoot = document.getElementById('crm-floating-lead-root');
        if (existingRoot) {
            existingRoot.remove();
        }
        const existingOverlay = document.getElementById('crm-lead-modal-overlay');
        if (existingOverlay) {
            existingOverlay.remove();
        }

        function getPositionCSS(pos, offX, offY) {
            const x = offX || '24px';
            const y = offY || '24px';
            pos = (pos || '').toLowerCase().trim();
            switch (pos) {
                case 'bottom-left':
                case 'left':
                    return `bottom: ${y} !important; left: ${x} !important; right: auto !important; top: auto !important;`;
                case 'top-right':
                    return `top: ${y} !important; right: ${x} !important; bottom: auto !important; left: auto !important;`;
                case 'top-left':
                    return `top: ${y} !important; left: ${x} !important; bottom: auto !important; right: auto !important;`;
                case 'bottom-center':
                    return `bottom: ${y} !important; left: 50% !important; right: auto !important; top: auto !important; transform: translateX(-50%) !important;`;
                case 'center-right':
                case 'middle-right':
                    return `top: 50% !important; right: 0 !important; bottom: auto !important; left: auto !important; transform: translateY(-50%) !important;`;
                case 'center-left':
                case 'middle-left':
                    return `top: 50% !important; left: 0 !important; bottom: auto !important; right: auto !important; transform: translateY(-50%) !important;`;
                case 'bottom-right':
                case 'right':
                default:
                    return `bottom: ${y} !important; right: ${x} !important; left: auto !important; top: auto !important;`;
            }
        }

        const isSideRight = config.position === 'center-right' || config.position === 'middle-right';
        const isSideLeft = config.position === 'center-left' || config.position === 'middle-left';

        let customBtnBorderRadius = '50px';
        let customBtnPadding = '12px 20px';
        if (isSideRight) {
            customBtnBorderRadius = '12px 0 0 12px';
            customBtnPadding = '12px 16px';
        } else if (isSideLeft) {
            customBtnBorderRadius = '0 12px 12px 0';
            customBtnPadding = '12px 16px';
        }

        const initialBtnColor = config.color || '#28a745';
        const positionCSS = getPositionCSS(config.position, config.offsetX, config.offsetY);

        // Inject Styles
        const style = document.createElement('style');
        style.id = 'crm-lead-widget-styles';
        style.textContent = `
            #crm-floating-lead-root {
                position: fixed !important;
                ${positionCSS}
                z-index: 2147483647 !important;
                font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
                margin: 0 !important;
                padding: 0 !important;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                pointer-events: auto !important;
            }
            .crm-floating-btn {
                background: ${initialBtnColor} !important;
                color: #ffffff !important;
                border: none !important;
                border-radius: ${customBtnBorderRadius} !important;
                padding: ${customBtnPadding} !important;
                font-size: 14px !important;
                font-weight: 600 !important;
                cursor: pointer !important;
                box-shadow: 0 8px 24px rgba(0,0,0,0.2), 0 2px 6px rgba(0,0,0,0.12) !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 8px !important;
                transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s, background 0.2s !important;
                outline: none !important;
                text-decoration: none !important;
                line-height: 1.4 !important;
                height: auto !important;
                min-height: 44px !important;
                white-space: nowrap !important;
            }
            .crm-floating-btn:hover {
                transform: scale(1.05) !important;
                box-shadow: 0 12px 30px rgba(0,0,0,0.26) !important;
            }
            .crm-lead-modal-overlay {
                display: none;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                background: rgba(15, 23, 42, 0.55) !important;
                backdrop-filter: blur(4px) !important;
                -webkit-backdrop-filter: blur(4px) !important;
                z-index: 2147483647 !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 16px !important;
                opacity: 0;
                transition: opacity 0.25s ease !important;
                box-sizing: border-box !important;
            }
            .crm-lead-modal-overlay.active {
                display: flex !important;
                opacity: 1 !important;
            }
            .crm-lead-modal-box {
                position: relative !important;
                width: 100% !important;
                max-width: 520px !important;
                max-height: calc(100vh - 32px) !important;
                max-height: calc(100dvh - 32px) !important;
                background: #ffffff !important;
                border-radius: 18px !important;
                overflow-y: auto !important;
                overflow-x: hidden !important;
                -webkit-overflow-scrolling: touch !important;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
                transform: translateY(20px) scale(0.96) !important;
                transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
                box-sizing: border-box !important;
                scrollbar-width: thin !important;
                scrollbar-color: rgba(148, 163, 184, 0.3) transparent !important;
            }
            .crm-lead-modal-box::-webkit-scrollbar {
                width: 4px !important;
            }
            .crm-lead-modal-box::-webkit-scrollbar-thumb {
                background: rgba(148, 163, 184, 0.3) !important;
                border-radius: 4px !important;
            }
            .crm-lead-modal-box::-webkit-scrollbar-track {
                background: transparent !important;
            }
            .crm-lead-modal-overlay.active .crm-lead-modal-box {
                transform: translateY(0) scale(1) !important;
            }
            .crm-modal-close-btn {
                position: absolute !important;
                top: 14px !important;
                right: 14px !important;
                width: 32px !important;
                height: 32px !important;
                background: #f1f5f9 !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 50% !important;
                color: #64748b !important;
                cursor: pointer !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                z-index: 30 !important;
                transition: all 0.2s ease !important;
                padding: 0 !important;
                box-shadow: 0 2px 6px rgba(0,0,0,0.06) !important;
            }
            .crm-modal-close-btn:hover {
                background: #e2e8f0 !important;
                color: #0f172a !important;
                transform: scale(1.05) !important;
            }
            .crm-modal-iframe {
                width: 100% !important;
                min-height: 480px !important;
                height: 500px !important;
                border: none !important;
                display: block !important;
                background: transparent !important;
            }
            .crm-modal-loader {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
                background: #ffffff !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 12px !important;
                z-index: 5 !important;
                transition: opacity 0.25s ease !important;
            }
            .crm-modal-spinner {
                width: 34px !important;
                height: 34px !important;
                border: 3px solid rgba(0, 0, 0, 0.08) !important;
                border-top-color: ${initialBtnColor} !important;
                border-radius: 50% !important;
                animation: crmSpin 0.7s linear infinite !important;
            }
            @keyframes crmSpin {
                to { transform: rotate(360deg); }
            }
            @media (max-width: 600px) {
                #crm-floating-lead-root {
                    bottom: 16px !important;
                    ${config.position.includes('left') ? 'left: 16px !important;' : 'right: 16px !important;'}
                }
                .crm-lead-modal-overlay {
                    padding: 8px !important;
                }
                .crm-lead-modal-box {
                    max-height: calc(100vh - 16px) !important;
                    max-height: calc(100dvh - 16px) !important;
                }
            }
        `;
        document.head.appendChild(style);

        // Build Elements
        const root = document.createElement('div');
        root.id = 'crm-floating-lead-root';

        const btn = document.createElement('button');
        btn.className = 'crm-floating-btn';
        btn.type = 'button';
        btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><span>${config.buttonText}</span>`;

        const overlay = document.createElement('div');
        overlay.className = 'crm-lead-modal-overlay';
        overlay.id = 'crm-lead-modal-overlay';

        const modalBox = document.createElement('div');
        modalBox.className = 'crm-lead-modal-box';

        const closeBtn = document.createElement('button');
        closeBtn.className = 'crm-modal-close-btn';
        closeBtn.type = 'button';
        closeBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`;
        closeBtn.setAttribute('aria-label', 'Close Form');

        const loader = document.createElement('div');
        loader.className = 'crm-modal-loader';
        loader.innerHTML = '<div class="crm-modal-spinner"></div>';

        const iframe = document.createElement('iframe');
        iframe.className = 'crm-modal-iframe';
        iframe.title = 'Quick Contact Form';
        // PRELOAD IMMEDIATELY in background for 0ms open
        iframe.src = buildFormUrl({ source: 'Floating Widget Button' });

        iframe.addEventListener('load', function () {
            loader.style.opacity = '0';
            setTimeout(() => {
                loader.style.display = 'none';
            }, 200);
        });

        modalBox.appendChild(closeBtn);
        modalBox.appendChild(loader);
        modalBox.appendChild(iframe);
        overlay.appendChild(modalBox);

        root.appendChild(btn);

        if (document.body) {
            document.body.appendChild(root);
            document.body.appendChild(overlay);
        }

        function openModal(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            overlay.style.display = 'flex';
            setTimeout(() => {
                overlay.classList.add('active');
            }, 10);
            if (document.body) document.body.style.overflow = 'hidden';
            if (document.documentElement) document.documentElement.style.overflow = 'hidden';
        }

        function closeModal(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            overlay.classList.remove('active');
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 250);
            if (document.body) document.body.style.overflow = '';
            if (document.documentElement) document.documentElement.style.overflow = '';
        }

        btn.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                closeModal(e);
            }
        });

        // Close on ESC key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('active')) {
                closeModal(e);
            }
        });

        // Allow ANY link/button on website with data-crm-lead-open or href="#crm-lead" to open the form
        document.addEventListener('click', function (e) {
            const trigger = e.target.closest('[data-crm-lead-open], [href="#crm-lead"], [href="#lead-enquiry"], .crm-lead-trigger');
            if (trigger) {
                e.preventDefault();
                openModal();
            }
        });

        // Expose API to window
        window.CRMLeadWidget = {
            open: openModal,
            close: closeModal
        };

        // Listen for events from iframe
        window.addEventListener('message', function (event) {
            if (!event.data) return;
            if (event.data.type === 'CRM_LEAD_FORM_INIT') {
                if (event.data.color) {
                    const fBtn = document.querySelector('.crm-floating-btn');
                    if (fBtn && !config.color) {
                        fBtn.style.setProperty('background', event.data.color, 'important');
                    }
                }
            } else if (event.data.type === 'CRM_LEAD_RESIZE' && event.data.height) {
                const modalIframe = overlay.querySelector('.crm-modal-iframe');
                if (modalIframe) {
                    const cleanH = Math.ceil(Number(event.data.height)) || 480;
                    modalIframe.style.setProperty('height', cleanH + 'px', 'important');
                }
            } else if (event.data.type === 'CRM_LEAD_CLOSE_MODAL') {
                closeModal();
            }
        });

        // Auto-fetch live tenant settings from CRM backend
        try {
            fetch(`${config.origin}/forms/config/${encodeURIComponent(config.tenant)}`, {
                headers: { 'Accept': 'application/json' }
            }).then(r => r.json()).then(data => {
                if (data && data.color && !config.color) {
                    btn.style.setProperty('background', data.color, 'important');
                }
            }).catch(() => { });
        } catch (e) { }
    }

    // Safe execution boot
    function boot() {
        if (!document.body) {
            setTimeout(boot, 50);
            return;
        }
        initInlineWidgets();
        initFloatingWidget();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

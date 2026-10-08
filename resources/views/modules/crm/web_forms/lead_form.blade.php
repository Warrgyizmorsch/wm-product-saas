<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} | {{ $tenant->name ?? 'Contact Us' }}</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        if (window.self !== window.top) {
            document.documentElement.classList.add('in-iframe');
        }
    </script>
    <style>
        :root {
            --primary: {{ $primaryColor ?: '#4f46e5' }};
            --primary-hover: {{ $primaryColor ? $primaryColor . 'ee' : '#4338ca' }};
            --primary-ring: {{ $primaryColor ? $primaryColor . '33' : 'rgba(79, 70, 229, 0.2)' }};
            --bg-page: {{ $theme === 'dark' ? '#0f172a' : '#f8fafc' }};
            --bg-card: {{ $theme === 'dark' ? '#1e293b' : '#ffffff' }};
            --text-main: {{ $theme === 'dark' ? '#f8fafc' : '#1e293b' }};
            --text-muted: {{ $theme === 'dark' ? '#94a3b8' : '#64748b' }};
            --border-color: {{ $theme === 'dark' ? '#334155' : '#e2e8f0' }};
            --input-bg: {{ $theme === 'dark' ? '#0f172a' : '#ffffff' }};
            --input-border: {{ $theme === 'dark' ? '#334155' : '#cbd5e1' }};
            --radius-md: 10px;
            --radius-lg: 16px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        html, body {
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.3) transparent;
        }

        body {
            background-color: var(--bg-page);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 12px;
        }

        /* Seamless iframe embed (No double card/border & Perfect scrolling) */
        html.in-iframe,
        html.in-iframe body {
            background-color: var(--bg-card) !important;
            width: 100% !important;
            height: auto !important;
            min-height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            display: block !important;
            overflow: visible !important;
        }

        html.in-iframe .form-card {
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            padding: 20px 22px 20px !important;
            max-width: 100% !important;
            width: 100% !important;
            min-height: auto !important;
            background-color: transparent !important;
            box-sizing: border-box !important;
        }

        html.in-iframe .success-screen {
            padding: 60px 20px !important;
        }

        ::-webkit-scrollbar {
            width: 4px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.3);
            border-radius: 4px;
        }

        .form-card {
            width: 100%;
            max-width: 520px;
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.04), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
        }

        .form-header {
            margin-bottom: 18px;
            text-align: left;
        }

        .form-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
            letter-spacing: -0.02em;
        }

        .form-header p {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .col-span-2 {
            grid-column: span 2;
        }

        .col-span-1 {
            grid-column: span 1;
        }

        @media (max-width: 480px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .col-span-1, .col-span-2 {
                grid-column: span 1;
            }
            .form-card {
                padding: 18px 14px;
            }
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 4px;
        }

        .form-label .required {
            color: #ef4444;
            margin-left: 2px;
        }

        .form-control {
            width: 100%;
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: var(--radius-md);
            padding: 9px 12px;
            font-size: 13px;
            color: var(--text-main);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-ring);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            min-height: 72px;
            resize: vertical;
        }

        /* Radio & Checkbox Styles */
        .option-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 4px;
        }

        .option-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            color: var(--text-main);
            cursor: pointer;
        }

        .option-item input {
            accent-color: var(--primary);
            cursor: pointer;
            width: 15px;
            height: 15px;
        }

        .btn-submit {
            width: 100%;
            background-color: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            padding: 11px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 14px;
            box-shadow: 0 4px 12px var(--primary-ring);
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-submit:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        /* Honeypot Spam Trap (Hidden) */
        .hp-field {
            display: none !important;
            visibility: hidden !important;
        }

        /* Success & Error Alert */
        .alert {
            display: none;
            padding: 12px 14px;
            border-radius: var(--radius-md);
            font-size: 12.5px;
            margin-bottom: 14px;
            line-height: 1.4;
        }

        .alert-success {
            background-color: {{ $theme === 'dark' ? '#064e3b' : '#ecfdf5' }};
            color: {{ $theme === 'dark' ? '#6ee7b7' : '#047857' }};
            border: 1px solid {{ $theme === 'dark' ? '#047857' : '#a7f3d0' }};
        }

        .alert-danger {
            background-color: {{ $theme === 'dark' ? '#7f1d1d' : '#fef2f2' }};
            color: {{ $theme === 'dark' ? '#fca5a5' : '#b91c1c' }};
            border: 1px solid {{ $theme === 'dark' ? '#b91c1c' : '#fecaca' }};
        }

        /* Success Screen */
        .success-screen {
            display: none;
            text-align: center;
            padding: 24px 8px;
        }

        .success-icon {
            width: 52px;
            height: 52px;
            background-color: {{ $theme === 'dark' ? '#064e3b' : '#ecfdf5' }};
            color: #10b981;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 12px;
            border: 2px solid #10b981;
        }

        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #ffffff;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .footer-badge {
            margin-top: 16px;
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 0.02em;
        }
    </style>
</head>
<body>

<div class="form-card">
    <div id="formContainer">
        <div class="form-header">
            <h2>{{ $title }}</h2>
            <p>{{ $subtitle }}</p>
        </div>

        <div id="alertSuccess" class="alert alert-success"></div>
        <div id="alertError" class="alert alert-danger"></div>

        <form id="leadCaptureForm" action="/forms/lead-capture" method="POST">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <input type="hidden" name="company_id" id="form_company_id" value="{{ $companyId ?? '' }}">
            <input type="hidden" name="branch_id" id="form_branch_id" value="{{ $branchId ?? '' }}">
            <input type="hidden" name="source" value="{{ $source }}">
            <input type="hidden" name="redirect_url" value="{{ $redirectUrl }}">

            <!-- UTM & Tracking Tags -->
            <input type="hidden" name="utm_source" id="utm_source">
            <input type="hidden" name="utm_medium" id="utm_medium">
            <input type="hidden" name="utm_campaign" id="utm_campaign">
            <input type="hidden" name="utm_term" id="utm_term">
            <input type="hidden" name="utm_content" id="utm_content">
            <input type="hidden" name="page_url" id="page_url">

            <!-- Anti-Spam Honeypot -->
            <div class="hp-field">
                <input type="text" name="_hp_website" tabindex="-1" autocomplete="off">
            </div>

            <!-- Dynamic Fields Grid -->
            <div class="form-grid">
                @php
                    $fieldsList = $fields ?? [];
                @endphp

                @foreach($fieldsList as $f)
                    @php
                        $fId = $f['id'] ?? $f['name'] ?? 'field_' . $loop->index;
                        $fName = $f['name'] ?? $fId;
                        $fLabel = $f['label'] ?? 'Field';
                        $fType = $f['type'] ?? 'text';
                        $fPlaceholder = $f['placeholder'] ?? '';
                        $fRequired = !empty($f['required']);
                        $fWidth = ($f['width'] ?? 'half') === 'full' ? 'col-span-2' : 'col-span-1';
                        $fOptions = $f['options'] ?? [];
                        if (is_string($fOptions)) {
                            $fOptions = array_map('trim', explode(',', $fOptions));
                        }
                    @endphp

                    <div class="form-group {{ $fWidth }}">
                        <label class="form-label" for="{{ $fId }}">
                            {{ $fLabel }}
                            @if($fRequired) <span class="required">*</span> @endif
                        </label>

                        @if($fType === 'select')
                            <select class="form-control" id="{{ $fId }}" name="{{ $fName }}" {{ $fRequired ? 'required' : '' }}>
                                <option value="">{{ $fPlaceholder ?: 'Select ' . $fLabel }}</option>
                                @foreach($fOptions as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>

                        @elseif($fType === 'radio')
                            <div class="option-group">
                                @foreach($fOptions as $idx => $opt)
                                    <label class="option-item">
                                        <input type="radio" name="{{ $fName }}" value="{{ $opt }}" {{ ($fRequired && $idx === 0) ? 'required' : '' }}>
                                        <span>{{ $opt }}</span>
                                    </label>
                                @endforeach
                            </div>

                        @elseif($fType === 'checkbox')
                            <div class="option-group">
                                @foreach($fOptions as $opt)
                                    <label class="option-item">
                                        <input type="checkbox" name="{{ $fName }}[]" value="{{ $opt }}">
                                        <span>{{ $opt }}</span>
                                    </label>
                                @endforeach
                            </div>

                        @elseif($fType === 'textarea')
                            <textarea class="form-control" id="{{ $fId }}" name="{{ $fName }}" placeholder="{{ $fPlaceholder }}" {{ $fRequired ? 'required' : '' }}></textarea>

                        @elseif($fType === 'date')
                            <input type="date" class="form-control" id="{{ $fId }}" name="{{ $fName }}" {{ $fRequired ? 'required' : '' }}>

                        @elseif($fType === 'number')
                            <input type="number" class="form-control" id="{{ $fId }}" name="{{ $fName }}" placeholder="{{ $fPlaceholder }}" {{ $fRequired ? 'required' : '' }}>

                        @elseif($fType === 'email')
                            <input type="email" class="form-control" id="{{ $fId }}" name="{{ $fName }}" placeholder="{{ $fPlaceholder }}" {{ $fRequired ? 'required' : '' }}>

                        @elseif($fType === 'tel')
                            <input type="tel" class="form-control" id="{{ $fId }}" name="{{ $fName }}" placeholder="{{ $fPlaceholder }}" {{ $fRequired ? 'required' : '' }}>

                        @else
                            <input type="text" class="form-control" id="{{ $fId }}" name="{{ $fName }}" placeholder="{{ $fPlaceholder }}" {{ $fRequired ? 'required' : '' }}>
                        @endif
                    </div>
                @endforeach
            </div>

            <button type="submit" class="btn-submit" id="btnSubmit">
                <span class="spinner" id="btnSpinner"></span>
                <span id="btnTextSpan">{{ $btnText }}</span>
            </button>
        </form>

        <div class="footer-badge" style="display: flex; align-items: center; justify-content: center; gap: 5px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.7;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <span>Secured by <strong>{{ $tenant->name ?? 'ERP CRM' }}</strong></span>
        </div>
    </div>

    <!-- Success Screen (Post Submission) -->
    <div id="successScreen" class="success-screen">
        <div class="success-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <h2 style="font-size: 19px; font-weight: 700; margin-bottom: 8px;">Enquiry Received!</h2>
        <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; max-width: 380px; margin: 0 auto;" id="successMsgText">
            Thank you! Your enquiry has been submitted successfully. Our team will contact you shortly.
        </p>
    </div>
</div>

<script>
    // Extract UTM & referrer parameters from URL
    (function parseTracking() {
        const urlParams = new URLSearchParams(window.location.search);
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(param => {
            const val = urlParams.get(param);
            if (val) {
                const el = document.getElementById(param);
                if (el) el.value = val;
            }
        });
        const pageUrlEl = document.getElementById('page_url');
        if (pageUrlEl) {
            pageUrlEl.value = window.location.href;
        }

        // Notify parent iframe about initialized theme color & height
        function sendIframeHeight() {
            if (window.parent !== window) {
                const card = document.querySelector('.form-card');
                let height = 0;
                if (card) {
                    height = Math.ceil(card.getBoundingClientRect().height);
                }
                if (!height) {
                    height = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
                }
                window.parent.postMessage({
                    type: 'CRM_LEAD_RESIZE',
                    height: height
                }, '*');
            }
        }

        if (window.parent !== window) {
            window.parent.postMessage({
                type: 'CRM_LEAD_FORM_INIT',
                color: '{{ $primaryColor }}'
            }, '*');
            sendIframeHeight();
            window.addEventListener('load', sendIframeHeight);
            window.addEventListener('resize', sendIframeHeight);
            if (window.ResizeObserver) {
                new ResizeObserver(sendIframeHeight).observe(document.body);
            }
        }
    })();

    const form = document.getElementById('leadCaptureForm');
    const btnSubmit = document.getElementById('btnSubmit');
    const btnSpinner = document.getElementById('btnSpinner');
    const btnTextSpan = document.getElementById('btnTextSpan');
    const alertError = document.getElementById('alertError');
    const formContainer = document.getElementById('formContainer');
    const successScreen = document.getElementById('successScreen');
    const successMsgText = document.getElementById('successMsgText');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        alertError.style.display = 'none';

        // UI Loading state
        btnSubmit.disabled = true;
        btnSpinner.style.display = 'inline-block';
        btnTextSpan.textContent = 'Submitting...';

        const formData = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // If redirect_url provided
                const redirectUrl = formData.get('redirect_url');
                if (redirectUrl && redirectUrl.trim() !== '') {
                    const sep = redirectUrl.includes('?') ? '&' : '?';
                    window.top.location.href = redirectUrl + sep + 'lead_status=success&lead_number=' + encodeURIComponent(data.lead_number || '');
                    return;
                }

                // Show Success Screen
                if (data.message) {
                    successMsgText.textContent = data.message;
                }
                formContainer.style.display = 'none';
                successScreen.style.display = 'block';

                // Send postMessage to parent window if inside iframe
                if (window.parent !== window) {
                    window.parent.postMessage({
                        type: 'CRM_LEAD_SUBMITTED',
                        lead_id: data.lead_id,
                        lead_number: data.lead_number
                    }, '*');
                }
            } else {
                let errorMsg = data.message || 'Something went wrong. Please check your inputs.';
                if (data.errors) {
                    const firstError = Object.values(data.errors)[0];
                    if (Array.isArray(firstError)) {
                        errorMsg = firstError[0];
                    }
                }
                alertError.textContent = errorMsg;
                alertError.style.display = 'block';
            }
        } catch (err) {
            alertError.textContent = 'Network error. Please check your connection and try again.';
            alertError.style.display = 'block';
        } finally {
            btnSubmit.disabled = false;
            btnSpinner.style.display = 'none';
            btnTextSpan.textContent = '{{ $btnText }}';
        }
    });

    function resetLeadForm() {
        form.reset();
        successScreen.style.display = 'none';
        formContainer.style.display = 'block';
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Live Preview listener from Admin Builder
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'CRM_UPDATE_PREVIEW') {
            const { fields, config } = event.data;
            if (config) {
                const headerH2 = document.querySelector('.form-header h2');
                const headerP = document.querySelector('.form-header p');
                const btnSpan = document.getElementById('btnTextSpan');

                if (headerH2 && config.title) headerH2.textContent = config.title;
                if (headerP && config.subtitle) headerP.textContent = config.subtitle;
                if (btnSpan && config.btn_text) btnSpan.textContent = config.btn_text;

                if (config.company_id !== undefined) {
                    const compEl = document.getElementById('form_company_id');
                    if (compEl) compEl.value = config.company_id || '';
                }
                if (config.branch_id !== undefined) {
                    const brEl = document.getElementById('form_branch_id');
                    if (brEl) brEl.value = config.branch_id || '';
                }

                if (config.color) {
                    document.documentElement.style.setProperty('--primary', config.color);
                    document.documentElement.style.setProperty('--primary-hover', config.color + 'ee');
                    document.documentElement.style.setProperty('--primary-ring', config.color + '33');
                }
                if (config.theme) {
                    const isDark = config.theme === 'dark';
                    document.documentElement.style.setProperty('--bg-page', isDark ? '#0f172a' : '#f8fafc');
                    document.documentElement.style.setProperty('--bg-card', isDark ? '#1e293b' : '#ffffff');
                    document.documentElement.style.setProperty('--text-main', isDark ? '#f8fafc' : '#1e293b');
                    document.documentElement.style.setProperty('--text-muted', isDark ? '#94a3b8' : '#64748b');
                    document.documentElement.style.setProperty('--border-color', isDark ? '#334155' : '#e2e8f0');
                    document.documentElement.style.setProperty('--input-bg', isDark ? '#0f172a' : '#ffffff');
                    document.documentElement.style.setProperty('--input-border', isDark ? '#334155' : '#cbd5e1');
                }
            }
            if (Array.isArray(fields)) {
                renderLiveFields(fields);
            }
        }
    });

    function renderLiveFields(fields) {
        const grid = document.querySelector('.form-grid');
        if (!grid) return;
        grid.innerHTML = '';

        fields.forEach((f, idx) => {
            const fId = f.id || f.name || ('field_' + idx);
            const fName = f.name || fId;
            const fLabel = f.label || 'Field';
            const fType = f.type || 'text';
            const fPlaceholder = f.placeholder || '';
            const fRequired = !!f.required;
            const fWidth = (f.width === 'full') ? 'col-span-2' : 'col-span-1';
            let fOptions = f.options || [];
            if (typeof fOptions === 'string') {
                fOptions = fOptions.split(',').map(s => s.trim()).filter(Boolean);
            }

            const group = document.createElement('div');
            group.className = `form-group ${fWidth}`;

            let inputHtml = '';
            if (fType === 'select') {
                let optHtml = `<option value="">${escapeHtml(fPlaceholder || 'Select ' + fLabel)}</option>`;
                fOptions.forEach(opt => {
                    optHtml += `<option value="${escapeHtml(opt)}">${escapeHtml(opt)}</option>`;
                });
                inputHtml = `<select class="form-control" id="${fId}" name="${fName}" ${fRequired ? 'required' : ''}>${optHtml}</select>`;
            } else if (fType === 'radio') {
                let radHtml = '<div class="option-group">';
                fOptions.forEach((opt, oIdx) => {
                    radHtml += `
                        <label class="option-item">
                            <input type="radio" name="${fName}" value="${escapeHtml(opt)}" ${(fRequired && oIdx === 0) ? 'required' : ''}>
                            <span>${escapeHtml(opt)}</span>
                        </label>
                    `;
                });
                radHtml += '</div>';
                inputHtml = radHtml;
            } else if (fType === 'checkbox') {
                let chkHtml = '<div class="option-group">';
                fOptions.forEach(opt => {
                    chkHtml += `
                        <label class="option-item">
                            <input type="checkbox" name="${fName}[]" value="${escapeHtml(opt)}">
                            <span>${escapeHtml(opt)}</span>
                        </label>
                    `;
                });
                chkHtml += '</div>';
                inputHtml = chkHtml;
            } else if (fType === 'textarea') {
                inputHtml = `<textarea class="form-control" id="${fId}" name="${fName}" placeholder="${escapeHtml(fPlaceholder)}" ${fRequired ? 'required' : ''}></textarea>`;
            } else if (fType === 'date') {
                inputHtml = `<input type="date" class="form-control" id="${fId}" name="${fName}" ${fRequired ? 'required' : ''}>`;
            } else if (fType === 'number') {
                inputHtml = `<input type="number" class="form-control" id="${fId}" name="${fName}" placeholder="${escapeHtml(fPlaceholder)}" ${fRequired ? 'required' : ''}>`;
            } else if (fType === 'email') {
                inputHtml = `<input type="email" class="form-control" id="${fId}" name="${fName}" placeholder="${escapeHtml(fPlaceholder)}" ${fRequired ? 'required' : ''}>`;
            } else if (fType === 'tel') {
                inputHtml = `<input type="tel" class="form-control" id="${fId}" name="${fName}" placeholder="${escapeHtml(fPlaceholder)}" ${fRequired ? 'required' : ''}>`;
            } else {
                inputHtml = `<input type="text" class="form-control" id="${fId}" name="${fName}" placeholder="${escapeHtml(fPlaceholder)}" ${fRequired ? 'required' : ''}>`;
            }

            group.innerHTML = `
                <label class="form-label" for="${fId}">
                    ${escapeHtml(fLabel)}
                    ${fRequired ? '<span class="required">*</span>' : ''}
                </label>
                ${inputHtml}
            `;
            grid.appendChild(group);
        });
    }
</script>

</body>
</html>

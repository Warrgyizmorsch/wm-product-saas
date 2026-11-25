    <style>
        /* PO-IMPL-06: read-only engineering workspace */
        .po-ws .po-op-card { border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px; background: #fff; transition: box-shadow .2s, opacity .2s; }
        .po-ws .po-op-card.is-dimmed { opacity: .55; }
        .po-ws .po-op-card.is-highlight { box-shadow: 0 0 0 3px rgba(59,130,246,.45); }
        .po-ws .po-op-card.is-incomplete { border-color: #dc3545; }
        .po-ws .po-stage { border-left: 3px solid #3b82f6; padding-left: 12px; margin-bottom: 18px; }
        .po-ws .po-num { font-family: var(--bs-font-monospace, monospace); text-align: right; white-space: nowrap; }
        .po-ws .po-drawer td { background: #f8fafc; }
        .po-ws tr.po-row { cursor: pointer; }
        .po-ws .po-pill { font-size: 12px; padding: 4px 10px; border-radius: 999px; }
        .po-modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 1060; display: flex; align-items: flex-start; justify-content: center; padding: 40px 16px; overflow-y: auto; }
        .po-modal { background: #fff; border-radius: 10px; width: 100%; max-width: 960px; box-shadow: 0 20px 50px rgba(0,0,0,.25); }
    </style>

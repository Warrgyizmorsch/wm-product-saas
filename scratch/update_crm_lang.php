<?php

$keys = [
    'sales' => [
        'en' => 'Sales',
        'hi' => 'बिक्री',
        'bg' => 'Продажби'
    ],
    'immediate_payment' => [
        'en' => 'Immediate Payment',
        'hi' => 'तत्काल भुगतान',
        'bg' => 'Незабавно плащаने'
    ],
    'invoice_number' => [
        'en' => 'Invoice Number',
        'hi' => 'चालान संख्या',
        'bg' => 'Номер на фактура'
    ],
    'gst_option' => [
        'en' => 'GST Option',
        'hi' => 'जीएसटी विकल्प',
        'bg' => 'Опция за ДДС'
    ],
    'disc' => [
        'en' => 'Discount (%)',
        'hi' => 'छूट (%)',
        'bg' => 'Отстъпка (%)'
    ],
    'tax_rate' => [
        'en' => 'Tax Rate',
        'hi' => 'कर दर',
        'bg' => 'Данъчна ставка'
    ],
    'select_product_ph' => [
        'en' => 'Select product...',
        'hi' => 'सामग्री चुनें...',
        'bg' => 'Изберете продукт...'
    ],
    'invoice_status' => [
        'en' => 'Invoice Status',
        'hi' => 'चालान स्थिति',
        'bg' => 'Статус на фактура'
    ],
    'status_partially_paid' => [
        'en' => 'Partially Paid',
        'hi' => 'आंशिक भुगतान',
        'bg' => 'Частично платен'
    ],
    'status_paid' => [
        'en' => 'Paid',
        'hi' => 'भुगतान किया गया',
        'bg' => 'Платен'
    ],
    'options' => [
        'en' => 'Options',
        'hi' => 'विकल्प',
        'bg' => 'Опции'
    ],
    'record_payment' => [
        'en' => 'Record Payment',
        'hi' => 'भुगतान दर्ज करें',
        'bg' => 'Запис на плащане'
    ],
    'print' => [
        'en' => 'Print',
        'hi' => 'प्रिंट',
        'bg' => 'Печат'
    ],
    'create' => [
        'en' => 'Create',
        'hi' => 'बनाएं',
        'bg' => 'Създайте'
    ],
    'create_crm_deal_title' => [
        'en' => 'Create CRM Deal',
        'hi' => 'सीआरएम सौदा बनाएं',
        'bg' => 'Създайте CRM сделка'
    ],
    'create_deal' => [
        'en' => 'Create Deal',
        'hi' => 'सौदा बनाएं',
        'bg' => 'Създайте сделка'
    ],
    'discussion_notes_summary' => [
        'en' => 'Discussion Notes / Summary',
        'hi' => 'चर्चा टिप्पणी / सारांश',
        'bg' => 'Резюме на бележки от дискусия'
    ],
    'next_activity_type' => [
        'en' => 'Next Activity Type',
        'hi' => 'अगली गतिविधि का प्रकार',
        'bg' => 'Тип на следващата дейност'
    ],
    'open_deals' => [
        'en' => 'Open Deals',
        'hi' => 'खुले सौदे',
        'bg' => 'Отворени сделки'
    ],
    'view_record' => [
        'en' => 'View Record',
        'hi' => 'रिकॉर्ड देखें',
        'bg' => 'Преглед на запис'
    ],
    'crm' => [
        'en' => 'CRM',
        'hi' => 'सीआरएम',
        'bg' => 'CRM'
    ],
    'create_quotation' => [
        'en' => 'Create Quotation',
        'hi' => 'कोटेशन बनाएं',
        'bg' => 'Създайте оферта'
    ],
    'sales_person' => [
        'en' => 'Salesperson',
        'hi' => 'बिक्री प्रतिनिधि',
        'bg' => 'Търговски представител'
    ],
    'total' => [
        'en' => 'Total',
        'hi' => 'कुल',
        'bg' => 'Общо'
    ],
];

$nestedKeys = [
    'tabs' => [
        'untouched' => [
            'en' => 'Untouched',
            'hi' => 'अछूता',
            'bg' => 'Недокоснати'
        ],
        'duplicates' => [
            'en' => 'Duplicates',
            'hi' => 'डुप्लिकेट',
            'bg' => 'Дубликати'
        ]
    ],
    'activity_types' => [
        'WhatsApp' => [
            'en' => 'WhatsApp Message',
            'hi' => 'व्हाट्सएप संदेश',
            'bg' => 'WhatsApp съобщение'
        ]
    ]
];

foreach (['en', 'hi', 'bg'] as $lang) {
    $filePath = __DIR__ . "/../lang/{$lang}/crm.php";
    $data = require $filePath;

    foreach ($keys as $k => $trans) {
        if (!isset($data[$k])) {
            $data[$k] = $trans[$lang];
        }
    }

    foreach ($nestedKeys as $parent => $sub) {
        if (!isset($data[$parent])) {
            $data[$parent] = [];
        }
        foreach ($sub as $k => $trans) {
            if (!isset($data[$parent][$k])) {
                $data[$parent][$k] = $trans[$lang];
            }
        }
    }

    $export = "<?php\n\nreturn " . var_export($data, true) . ";\n";
    file_put_contents($filePath, $export);
    echo "Updated lang/{$lang}/crm.php\n";
}


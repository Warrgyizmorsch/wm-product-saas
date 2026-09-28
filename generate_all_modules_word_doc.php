<?php

/**
 * Complete ERP Master REST API Specification Generator
 * Generates an executive-grade Word document (.docx) containing:
 * - CRM Leads Module APIs
 * - CRM Deals Module APIs
 * - Sales Module APIs (Customers, Orders, Invoices, Payments, Dispatches)
 * - Purchase Module APIs (Vendors, Orders, GRNs, Bills, Payments)
 * - Inventory Module APIs (Products, Warehouses, Transfers, Adjustments, Batches, Serials, Ledger)
 */

class FullApiDocxGenerator
{
    private string $content = '';

    public function addTitle(string $text): void
    {
        $clean = htmlspecialchars($text, ENT_XML1);
        $this->content .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="300" w:after="200"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="48"/><w:color w:val="0F172A"/><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/></w:rPr><w:t>' . $clean . '</w:t></w:r></w:p>';
    }

    public function addSubtitle(string $text): void
    {
        $clean = htmlspecialchars($text, ENT_XML1);
        $this->content .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="100" w:after="400"/></w:pPr><w:r><w:rPr><w:sz w:val="24"/><w:color w:val="475569"/><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/></w:rPr><w:t>' . $clean . '</w:t></w:r></w:p>';
    }

    public function addHeading1(string $text): void
    {
        $clean = htmlspecialchars($text, ENT_XML1);
        $this->content .= '<w:p><w:pPr><w:spacing w:before="400" w:after="160"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="34"/><w:color w:val="1E40AF"/><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/></w:rPr><w:t>' . $clean . '</w:t></w:r></w:p>';
    }

    public function addHeading2(string $text): void
    {
        $clean = htmlspecialchars($text, ENT_XML1);
        $this->content .= '<w:p><w:pPr><w:spacing w:before="260" w:after="120"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="26"/><w:color w:val="0D9488"/><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/></w:rPr><w:t>' . $clean . '</w:t></w:r></w:p>';
    }

    public function addParagraph(string $text): void
    {
        $clean = htmlspecialchars($text, ENT_XML1);
        $this->content .= '<w:p><w:pPr><w:spacing w:before="60" w:after="100"/><w:rPr><w:sz w:val="20"/><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/></w:rPr></w:pPr><w:r><w:rPr><w:sz w:val="20"/><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/></w:rPr><w:t>' . $clean . '</w:t></w:r></w:p>';
    }

    public function addBadge(string $method, string $endpoint): void
    {
        $cleanMethod   = htmlspecialchars($method, ENT_XML1);
        $cleanEndpoint = htmlspecialchars($endpoint, ENT_XML1);
        $color = match($method) {
            'GET' => '059669',
            'POST' => '2563EB',
            'PUT', 'PATCH' => 'D97706',
            'DELETE' => 'DC2626',
            default => '4B5563'
        };

        $this->content .= '<w:p><w:pPr><w:spacing w:before="160" w:after="80"/></w:pPr>' .
            '<w:r><w:rPr><w:b/><w:sz w:val="20"/><w:color w:val="FFFFFF"/><w:highlight w:val="darkBlue"/></w:rPr><w:t> ' . $cleanMethod . ' </w:t></w:r>' .
            '<w:r><w:rPr><w:b/><w:sz w:val="22"/><w:color w:val="' . $color . '"/></w:rPr><w:t>  ' . $cleanEndpoint . '</w:t></w:r></w:p>';
    }

    public function addCodeBlock(string $json): void
    {
        $cleanJson = htmlspecialchars($json, ENT_XML1);
        $lines = explode("\n", $cleanJson);
        $inner = '';
        foreach ($lines as $line) {
            $inner .= '<w:r><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="18"/><w:color w:val="1E293B"/></w:rPr><w:t xml:space="preserve">' . $line . '</w:t></w:r><w:r><w:br/></w:r>';
        }

        $this->content .= '<w:p><w:pPr><w:pBdr><w:left w:val="single" w:sz="24" w:space="12" w:color="3B82F6"/><w:top w:val="single" w:sz="6" w:space="8" w:color="E2E8F0"/><w:right w:val="single" w:sz="6" w:space="8" w:color="E2E8F0"/><w:bottom w:val="single" w:sz="6" w:space="8" w:color="E2E8F0"/></w:pBdr><w:shd w:val="clear" w:color="auto" w:fill="F8FAFC"/><w:spacing w:before="100" w:after="140"/><w:ind w:left="140" w:right="140"/></w:pPr>' . $inner . '</w:p>';
    }

    public function addTable(array $headers, array $rows): void
    {
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders><w:top w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/><w:bottom w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/><w:insideH w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/><w:insideV w:val="none"/><w:left w:val="none"/><w:right w:val="none"/></w:tblBorders><w:tblCellMar><w:top w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/><w:left w:w="120" w:type="dxa"/><w:right w:w="120" w:type="dxa"/></w:tblCellMar></w:tblPr>';

        // Header Row
        $xml .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>';
        foreach ($headers as $h) {
            $cleanH = htmlspecialchars($h, ENT_XML1);
            $xml .= '<w:tc><w:tcPr><w:shd w:val="clear" w:color="auto" w:fill="F1F5F9"/></w:tcPr><w:p><w:pPr><w:spacing w:before="60" w:after="60"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="19"/><w:color w:val="0F172A"/></w:rPr><w:t>' . $cleanH . '</w:t></w:r></w:p></w:tc>';
        }
        $xml .= '</w:tr>';

        // Data Rows
        foreach ($rows as $row) {
            $xml .= '<w:tr>';
            foreach ($row as $cell) {
                $cleanCell = htmlspecialchars($cell, ENT_XML1);
                $xml .= '<w:tc><w:p><w:pPr><w:spacing w:before="40" w:after="40"/></w:pPr><w:r><w:rPr><w:sz w:val="18"/><w:color w:val="334155"/></w:rPr><w:t>' . $cleanCell . '</w:t></w:r></w:p></w:tc>';
            }
            $xml .= '</w:tr>';
        }

        $xml .= '</w:tbl><w:p><w:pPr><w:spacing w:after="120"/></w:pPr></w:p>';
        $this->content .= $xml;
    }

    public function generateZip(string $outputPath): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>' .
            '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>' .
            '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // word/_rels/document.xml.rels
        $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>';
        $zip->addFromString('word/_rels/document.xml.rels', $docRels);

        // word/document.xml
        $docXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' .
            '<w:body>' . $this->content . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr></w:body></w:document>';
        $zip->addFromString('word/document.xml', $docXml);

        $zip->close();
        return true;
    }
}

$doc = new FullApiDocxGenerator();

// Document Header
$doc->addTitle('Complete ERP REST API Specification Guide');
$doc->addSubtitle('Enterprise CRM (Leads, Deals), Sales, Purchase, and Inventory REST API Kits');

$doc->addHeading1('1. API Architecture & Global Authentication');
$doc->addParagraph('All endpoints in this ERP kit are built upon Laravel Sanctum REST standards with full Multi-Tenant, Multi-Company, and Multi-Branch scoping.');
$doc->addTable(
    ['Header Name', 'Sample Value', 'Description'],
    [
        ['Authorization', 'Bearer 1|abc123xyz...', 'Required. Sanctum Bearer API token obtained from /api/auth/login.'],
        ['Accept', 'application/json', 'Required. Enforces JSON responses and 422 error payloads.'],
        ['Content-Type', 'application/json', 'Required for POST, PUT, and PATCH requests.'],
        ['X-Company-Id', '1', 'Optional. Overrides default active company context.'],
        ['X-Branch-Id', '1', 'Optional. Scopes requests to a specific branch.']
    ]
);

// SECTION 2: CRM LEADS
$doc->addHeading1('2. CRM Lead Management REST API Kit');
$doc->addBadge('GET', '/api/crm/leads');
$doc->addParagraph('List, search, filter, and paginate all leads.');
$doc->addTable(
    ['Parameter', 'Type', 'Required', 'Description'],
    [
        ['search', 'string', 'No', 'Searches lead name, company, email, phone, city.'],
        ['status_id', 'integer', 'No', 'Filter by Lead Status Master ID.'],
        ['priority', 'string', 'No', 'Filter by priority: low, medium, high, urgent.'],
        ['owner_id', 'integer', 'No', 'Filter by assigned sales representative user ID.'],
        ['from_date', 'date (Y-m-d)', 'No', 'Created on or after date.'],
        ['to_date', 'date (Y-m-d)', 'No', 'Created on or before date.'],
        ['has_deal', 'boolean', 'No', 'Pass 1 for converted leads, 0 for unconverted.'],
        ['sort_by', 'string', 'No', 'Sort column: id, name, company_name, score, created_at.'],
        ['sort_direction', 'string', 'No', 'asc or desc (default: desc).'],
        ['per_page', 'integer', 'No', 'Number of records per page (default: 15, max: 100).']
    ]
);

$doc->addBadge('POST', '/api/crm/leads');
$doc->addParagraph('Create a new CRM lead with full field validation & auto-scoring.');
$doc->addCodeBlock(json_encode([
    'name'           => 'Vikram Singhania',
    'company_name'   => 'Singhania Industrial Corp',
    'email'          => 'vikram@singhania.com',
    'phone'          => '+919876543210',
    'status_id'      => 1,
    'priority'       => 'high',
    'lead_source'    => 'Website',
    'estimated_value'=> 250000.00,
    'owner_id'       => 1,
    'city'           => 'Mumbai',
    'state'          => 'Maharashtra',
    'country'        => 'India',
    'notes'          => 'Inquired for 500 units of Finished Good A'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// SECTION 3: CRM DEALS
$doc->addHeading1('3. CRM Deal Management REST API Kit');
$doc->addBadge('GET', '/api/crm/deals');
$doc->addParagraph('List deals with pipeline stage metrics, filters, and linked accounts.');
$doc->addTable(
    ['Parameter', 'Type', 'Required', 'Description'],
    [
        ['search', 'string', 'No', 'Search deal title, deal number, account name, contact name.'],
        ['status_id', 'integer', 'No', 'Filter by Deal Pipeline Stage ID.'],
        ['owner_id', 'integer', 'No', 'Filter by deal owner user ID.'],
        ['account_id', 'integer', 'No', 'Filter by linked CRM Account ID.'],
        ['min_amount', 'numeric', 'No', 'Minimum deal value.'],
        ['max_amount', 'numeric', 'No', 'Maximum deal value.']
    ]
);

$doc->addBadge('POST', '/api/crm/deals');
$doc->addParagraph('Create a new deal with products, probability, expected close date.');
$doc->addCodeBlock(json_encode([
    'title'               => 'Enterprise Supply Agreement 2026',
    'deal_status_id'      => 2,
    'crm_account_id'      => 1,
    'crm_contact_id'      => 1,
    'amount'              => 750000.00,
    'probability'         => 70,
    'expected_close_date' => '2026-10-31',
    'owner_id'            => 1,
    'source'              => 'Referral',
    'notes'               => 'Contract under legal review'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$doc->addBadge('PATCH', '/api/crm/deals/{id}/move-stage');
$doc->addParagraph('Move deal across Kanban pipeline stages.');
$doc->addCodeBlock(json_encode([
    'deal_status_id' => 3,
    'probability'    => 85,
    'notes'          => 'Moved to Negotiation stage after client feedback'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// SECTION 4: SALES MODULE
$doc->addHeading1('4. Sales Module REST API Kit');
$doc->addHeading2('4.1 Customers API');
$doc->addBadge('GET', '/api/sales/customers');
$doc->addParagraph('List customers with active sales order and invoice count metrics.');

$doc->addBadge('POST', '/api/sales/customers');
$doc->addParagraph('Create a new customer profile.');
$doc->addCodeBlock(json_encode([
    'name'             => 'Apex Retail Solutions Ltd',
    'company_name'     => 'Apex Retail',
    'email'            => 'procurement@apexretail.in',
    'phone'            => '+919812345678',
    'gstin'            => '27AAAAA0000A1Z5',
    'status'           => 'active',
    'billing_address'  => 'Plot 45, Phase 2, MIDC, Andheri East, Mumbai 400093',
    'shipping_address' => 'Warehouse 12, Bhiwandi Logistics Park, Thane 421302',
    'opening_balance'  => 0.00
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$doc->addHeading2('4.2 Sales Orders API');
$doc->addBadge('POST', '/api/sales/orders');
$doc->addParagraph('Create Sales Order with multi-line item calculations.');
$doc->addCodeBlock(json_encode([
    'customer_id'      => 1,
    'order_date'       => '2026-09-26',
    'shipment_date'    => '2026-10-05',
    'status'           => 'Draft',
    'sales_person_id'  => 1,
    'payment_terms'    => 'Net 30',
    'discount_type'    => 'fixed',
    'tax_type'         => 'exclusive',
    'gst_type'         => 'cgst_sgst',
    'freight_amount'   => 1500.00,
    'shipping_charges' => 500.00,
    'items' => [
        [
            'product_id'   => 1,
            'warehouse_id' => 1,
            'item_name'    => 'Industrial Hydraulic Valve 50mm',
            'quantity'     => 10,
            'unit_price'   => 4500.00,
            'tax_rate'     => 18.00,
            'discount'     => 500.00,
            'description'  => 'Heavy duty cast iron valve'
        ]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$doc->addBadge('POST', '/api/sales/orders/{id}/convert-to-invoice');
$doc->addParagraph('1-Click convert Sales Order into Tax Invoice.');

$doc->addBadge('POST', '/api/sales/orders/{id}/convert-to-dispatch');
$doc->addParagraph('1-Click generate Delivery Challan / Dispatch Order from Sales Order.');

$doc->addHeading2('4.3 Invoices & Customer Payments API');
$doc->addBadge('POST', '/api/sales/invoices');
$doc->addParagraph('Create Tax Invoice directly.');

$doc->addBadge('POST', '/api/sales/payments');
$doc->addParagraph('Record customer payment and auto-allocate balance to invoices.');
$doc->addCodeBlock(json_encode([
    'customer_id'    => 1,
    'payment_date'   => '2026-09-26',
    'amount'         => 52500.00,
    'payment_method' => 'Bank Transfer',
    'reference_no'   => 'HDFC-NEFT-9847291',
    'notes'          => 'Payment received against Invoice INV-00001',
    'allocations'    => [
        [
            'invoice_id' => 1,
            'amount'     => 52500.00
        ]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// SECTION 5: PURCHASE MODULE
$doc->addHeading1('5. Purchase Module REST API Kit');
$doc->addHeading2('5.1 Vendors API');
$doc->addBadge('POST', '/api/purchase/vendors');
$doc->addCodeBlock(json_encode([
    'name'            => 'National Steel & Alloy Suppliers',
    'company_name'    => 'National Steel Ltd',
    'email'           => 'sales@nationalsteel.com',
    'phone'           => '+919822334455',
    'gstin'           => '27BBBBB1111B1Z2',
    'pan'             => 'BBBBB1111B',
    'status'          => 'active',
    'payment_terms'   => 'Net 45',
    'billing_address' => 'Plot 101, Industrial Estate, Pune 411019'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$doc->addHeading2('5.2 Purchase Orders & GRN');
$doc->addBadge('POST', '/api/purchase/orders');
$doc->addCodeBlock(json_encode([
    'vendor_id'                 => 1,
    'date'                      => '2026-09-26',
    'delivery_date'             => '2026-10-10',
    'status'                    => 'Draft',
    'reference'                 => 'RFQ-2026-089',
    'supplier_quotation_number' => 'SQN-5542',
    'items' => [
        [
            'product_id'   => 1,
            'warehouse_id' => 1,
            'quantity'     => 100,
            'unit_price'   => 3200.00,
            'tax_rate'     => 18.00,
            'discount'     => 0,
            'description'  => 'Grade 304 Stainless Steel Rods'
        ]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$doc->addBadge('POST', '/api/purchase/orders/{id}/convert-to-grn');
$doc->addParagraph('Convert Purchase Order into Goods Receipt Note (GRN) upon physical arrival.');

$doc->addBadge('POST', '/api/purchase/bills');
$doc->addParagraph('Create Vendor Bill and allocate payments.');
$doc->addBadge('POST', '/api/purchase/payments');
$doc->addParagraph('Record outgoing Vendor Payment and reconcile vendor ledger.');

// SECTION 6: INVENTORY MODULE
$doc->addHeading1('6. Inventory Module REST API Kit');
$doc->addHeading2('6.1 Products & Warehouses API');
$doc->addBadge('GET', '/api/inventory/products/export');
$doc->addParagraph('Full product catalog export with variants, barcodes, stock levels, images, and pricing.');

$doc->addBadge('POST', '/api/inventory/warehouses');
$doc->addCodeBlock(json_encode([
    'name'           => 'Central Finished Goods Warehouse',
    'code'           => 'WH-CENTRAL-01',
    'address'        => 'Sector 8, Logistics Hub',
    'city'           => 'Navi Mumbai',
    'state'          => 'Maharashtra',
    'pincode'        => '400705',
    'contact_person' => 'Rajesh Sharma',
    'phone'          => '+919988776655',
    'status'         => 'active',
    'is_default'     => true
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$doc->addHeading2('6.2 Stock Transfers (Inter-Warehouse)');
$doc->addBadge('POST', '/api/inventory/transfers');
$doc->addParagraph('Create stock transfer between two warehouses.');
$doc->addCodeBlock(json_encode([
    'from_warehouse_id' => 1,
    'to_warehouse_id'   => 2,
    'transfer_date'     => '2026-09-26',
    'status'            => 'In Transit',
    'notes'             => 'Stock replenishment for Branch 2',
    'items' => [
        [
            'product_id' => 1,
            'quantity'   => 25
        ]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$doc->addHeading2('6.3 Stock Adjustments & Physical Count');
$doc->addBadge('POST', '/api/inventory/adjustments');
$doc->addParagraph('Record positive or negative inventory adjustments.');
$doc->addCodeBlock(json_encode([
    'warehouse_id'    => 1,
    'adjustment_date' => '2026-09-26',
    'reason'          => 'Quarterly Physical Audit Discrepancy',
    'items' => [
        [
            'product_id' => 1,
            'type'       => 'increase',
            'quantity'   => 5,
            'unit_cost'  => 3200.00
        ]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$doc->addHeading2('6.4 Batch & Serial Number Tracking');
$doc->addBadge('POST', '/api/inventory/batches');
$doc->addParagraph('Create and track manufacturing batches with expiry dates.');
$doc->addCodeBlock(json_encode([
    'product_id'         => 1,
    'warehouse_id'       => 1,
    'batch_number'       => 'BATCH-2026-SEP-01',
    'quantity'           => 500,
    'manufacturing_date' => '2026-09-01',
    'expiry_date'        => '2028-08-31'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$doc->addBadge('POST', '/api/inventory/serials');
$doc->addParagraph('Track individual item serial numbers with life-cycle statuses.');
$doc->addCodeBlock(json_encode([
    'product_id'    => 1,
    'warehouse_id'  => 1,
    'batch_id'      => 1,
    'serial_number' => 'SRN-2026-009182',
    'purchase_rate' => 3200.00,
    'status'        => 'Available'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$doc->addHeading2('6.5 Stock Transaction Ledger');
$doc->addBadge('GET', '/api/inventory/ledger');
$doc->addParagraph('Query full audit trail of stock movements (IN/OUT, GRN, Invoices, Adjustments, Transfers) with running balance.');
$doc->addTable(
    ['Parameter', 'Type', 'Required', 'Description'],
    [
        ['product_id', 'integer', 'No', 'Filter stock transactions for a single product.'],
        ['warehouse_id', 'integer', 'No', 'Filter transactions in a specific warehouse.'],
        ['type', 'string', 'No', 'IN or OUT.'],
        ['reference_type', 'string', 'No', 'GRN, Invoice, Stock Adjustment, Transfer, Manufacturing.'],
        ['from_date', 'date (Y-m-d)', 'No', 'Transactions on or after date.'],
        ['to_date', 'date (Y-m-d)', 'No', 'Transactions on or before date.']
    ]
);

$outputPath = __DIR__ . '/Complete_ERP_REST_API_Specification_Guide.docx';
if ($doc->generateZip($outputPath)) {
    echo "SUCCESS: Complete ERP REST API Specification Guide generated at: " . $outputPath . "\n";
} else {
    echo "ERROR: Failed to generate document.\n";
}

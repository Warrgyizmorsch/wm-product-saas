<?php

namespace App\Domains\Accounting\Support;

/**
 * Stable identities for the accounts every tenant is provisioned with.
 *
 * A chart-of-accounts code is a label the tenant owns and may renumber; a
 * system key is the platform's handle on "the Accounts Receivable account"
 * and never changes. Posting logic should resolve accounts through
 * SystemAccountService::get(SystemAccount::X, $tenantId), never by code.
 *
 * TEMPLATE maps each key to the code it was originally provisioned with. It
 * is FROZEN: keys and codes here must never be renamed or reused, because the
 * system_key backfill migration and existing tenant rows depend on them. Add
 * new rows; don't edit old ones.
 */
final class SystemAccount
{
    // Control and cash accounts
    public const CASH = 'cash_in_hand';
    public const BANK = 'bank_account';
    public const AR = 'accounts_receivable';
    public const AP = 'accounts_payable';
    public const INVENTORY = 'inventory';
    public const WORK_IN_PROGRESS = 'work_in_progress';
    public const SUSPENSE = 'suspense_account';

    // Advances
    public const LOANS_AND_ADVANCES = 'loans_and_advances';
    public const ADVANCE_TO_SUPPLIERS = 'advance_to_suppliers';
    public const ADVANCE_TO_EMPLOYEES = 'advance_to_employees';
    public const ADVANCE_FROM_CUSTOMERS = 'advance_from_customers';

    // GST
    public const INPUT_TAX = 'duties_and_taxes_input_credit';
    public const INPUT_CGST = 'input_cgst';
    public const INPUT_SGST = 'input_sgst';
    public const INPUT_IGST = 'input_igst';
    public const OUTPUT_TAX = 'duties_and_taxes_output';
    public const OUTPUT_CGST = 'output_cgst';
    public const OUTPUT_SGST = 'output_sgst';
    public const OUTPUT_IGST = 'output_igst';
    public const TDS_PAYABLE = 'tds_payable';

    // Income and expense
    public const SALES_REVENUE = 'sales_revenue';
    public const SALES_RETURNS = 'sales_returns_and_allowances';
    public const MISC_INCOME = 'miscellaneous_income';
    public const COGS = 'cost_of_goods_sold';
    public const OTHER_EXPENSE = 'other_expense';
    public const ROUND_OFF = 'round_off';

    // Foreign exchange
    public const FX_GAIN = 'foreign_exchange_gain';
    public const FX_LOSS = 'foreign_exchange_loss';

    // Fixed assets
    public const FIXED_ASSETS = 'fixed_assets';
    public const ACCUMULATED_DEPRECIATION = 'accumulated_depreciation';
    public const REVALUATION_RESERVE = 'revaluation_reserve';
    public const GAIN_ON_ASSET_DISPOSAL = 'gain_on_sale_of_fixed_assets';
    public const DEPRECIATION_EXPENSE = 'depreciation_expense';
    public const LOSS_ON_ASSET_DISPOSAL = 'loss_on_sale_of_fixed_assets';
    public const IMPAIRMENT_LOSS = 'impairment_loss';

    /** @var array<string, string> system_key => originally provisioned code. FROZEN. */
    public const TEMPLATE = [
        'assets' => '1000',
        'liabilities' => '2000',
        'equity' => '3000',
        'income' => '4000',
        'expenses' => '5000',
        'share_capital' => '3010',
        'reserves_and_surplus' => '3020',
        'partners_proprietors_capital' => '3030',
        'drawings' => '3040',
        'revaluation_reserve' => '3200',
        'secured_loans' => '2400',
        'term_loan_bank' => '2401',
        'vehicle_loan' => '2402',
        'unsecured_loans' => '2410',
        'unsecured_loan_director' => '2411',
        'unsecured_loan_others' => '2412',
        'accounts_payable' => '2010',
        'creditors_for_expenses' => '2015',
        'duties_and_taxes_output' => '2100',
        'output_cgst' => '2110',
        'output_sgst' => '2120',
        'output_igst' => '2130',
        'tds_payable' => '2140',
        'tcs_payable' => '2150',
        'pf_payable' => '2160',
        'esi_payable' => '2170',
        'professional_tax_payable' => '2180',
        'taxes_payable_other' => '2020',
        'salary_payable' => '2030',
        'provision_for_expenses' => '2320',
        'provision_for_warranty' => '2330',
        'audit_fee_payable' => '2340',
        'provision_for_taxation' => '2300',
        'provision_for_bad_debts' => '2310',
        'advance_from_customers' => '2200',
        'outstanding_expenses' => '2070',
        'statutory_dues_payable' => '2080',
        'suspense_account' => '2900',
        'preliminary_expenses_to_be_written_off' => '1720',
        'fixed_assets' => '1500',
        'land_and_building' => '1501',
        'plant_and_machinery' => '1502',
        'office_equipment' => '1503',
        'computers_and_it_equipment' => '1504',
        'furniture_and_fixtures' => '1505',
        'vehicles' => '1506',
        'accumulated_depreciation' => '1510',
        'fixed_deposits' => '1700',
        'mutual_funds' => '1710',
        'cash_in_hand' => '1010',
        'bank_account' => '1020',
        'hdfc_bank_current_ac' => '1021',
        'icici_bank_current_ac' => '1022',
        'razorpay_settlement_account' => '1023',
        'accounts_receivable' => '1100',
        'debtors_cod_pending' => '1110',
        'inventory' => '1200',
        'stock_in_hand_finished_goods' => '1201',
        'stock_in_hand_raw_material' => '1202',
        'stock_in_transit' => '1203',
        'work_in_progress' => '1204',
        'loans_and_advances' => '1400',
        'advance_to_suppliers' => '1410',
        'advance_to_employees' => '1420',
        'prepaid_expenses' => '1430',
        'security_deposits' => '1300',
        'tds_receivable' => '1640',
        'gst_refund_receivable' => '1650',
        'duties_and_taxes_input_credit' => '1600',
        'input_cgst' => '1610',
        'input_sgst' => '1620',
        'input_igst' => '1630',
        'sales_revenue' => '4010',
        'sales_website_d2c' => '4011',
        'sales_offline_retail' => '4012',
        'sales_b2b_corporate' => '4013',
        'installation_charges_income' => '4014',
        'sales_returns_and_allowances' => '4030',
        'discount_allowed' => '4031',
        'service_revenue' => '4020',
        'purchase_raw_material' => '5020',
        'purchase_finished_goods' => '5021',
        'purchase_returns' => '5022',
        'discount_received' => '5023',
        'job_work_income' => '4040',
        'interest_received' => '4910',
        'scrap_sale_income' => '4920',
        'miscellaneous_income' => '4900',
        'foreign_exchange_gain' => '4930',
        'gain_on_sale_of_fixed_assets' => '4940',
        'cost_of_goods_sold' => '5010',
        'freight_and_forwarding_inward' => '5030',
        'wages_factory_warehouse' => '5031',
        'packing_material_expense' => '5032',
        'power_and_fuel_production' => '5033',
        'salary_and_wages_staff' => '5100',
        'staff_welfare_expenses' => '5500',
        'rent_expense' => '5200',
        'electricity_expense' => '5300',
        'telephone_and_internet_expense' => '5510',
        'office_supplies_stationery' => '5520',
        'repairs_and_maintenance' => '5530',
        'insurance_expense' => '5540',
        'legal_and_professional_charges' => '5550',
        'audit_fees' => '5560',
        'bank_charges' => '5570',
        'payment_gateway_charges_razorpay' => '5580',
        'shopify_subscription_and_app_fees' => '5590',
        'odoo_subscription_license_fees' => '5600',
        'freight_and_forwarding_outward' => '5610',
        'cod_handling_charges' => '5620',
        'courier_and_postage' => '5630',
        'installation_and_technician_charges_vms' => '5640',
        'warranty_and_after_sales_expense' => '5650',
        'advertising_and_marketing_expense' => '5660',
        'whatsapp_email_sms_api_charges' => '5670',
        'myoperator_ivr_charges' => '5680',
        'software_and_subscription_charges' => '5690',
        'travel_and_conveyance' => '5700',
        'printing_and_stationery' => '5710',
        'depreciation_expense' => '5400',
        'interest_on_loan' => '5720',
        'round_off' => '5730',
        'foreign_exchange_loss' => '5740',
        'other_expense' => '5900',
        'loss_on_sale_of_fixed_assets' => '5910',
        'impairment_loss' => '5920',
    ];

    /** The code an account was provisioned with, or null for an unknown key. */
    public static function templateCode(string $key): ?string
    {
        return self::TEMPLATE[$key] ?? null;
    }

    /** The system key for a template code, or null if the code isn't in the template. */
    public static function keyForTemplateCode(string $code): ?string
    {
        $key = array_search($code, self::TEMPLATE, true);

        return $key === false ? null : $key;
    }

    private function __construct()
    {
    }
}

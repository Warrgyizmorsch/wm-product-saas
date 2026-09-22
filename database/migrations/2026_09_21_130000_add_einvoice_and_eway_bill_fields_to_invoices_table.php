<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // E-Invoice (IRN) Fields
            $table->string('einvoice_status', 30)->default('Pending')->after('status');
            $table->string('irn', 100)->nullable()->after('einvoice_status');
            $table->string('ack_no', 50)->nullable()->after('irn');
            $table->dateTime('ack_date')->nullable()->after('ack_no');
            $table->longText('signed_qrcode')->nullable()->after('ack_date');
            $table->longText('signed_invoice')->nullable()->after('signed_qrcode');
            $table->text('einvoice_error')->nullable()->after('signed_invoice');

            // E-Way Bill Fields
            $table->string('eway_bill_status', 30)->default('Pending')->after('einvoice_error');
            $table->string('eway_bill_no', 50)->nullable()->after('eway_bill_status');
            $table->dateTime('eway_bill_date')->nullable()->after('eway_bill_no');
            $table->dateTime('eway_bill_valid_till')->nullable()->after('eway_bill_date');
            $table->string('transporter_id', 30)->nullable()->after('eway_bill_valid_till');
            $table->string('transporter_name', 150)->nullable()->after('transporter_id');
            $table->string('transport_mode', 20)->nullable()->after('transporter_name'); // 1-Road, 2-Rail, 3-Air, 4-Ship
            $table->string('vehicle_no', 30)->nullable()->after('transport_mode');
            $table->string('vehicle_type', 20)->nullable()->after('vehicle_no'); // R-Regular, O-Over Dimensional
            $table->string('transport_doc_no', 50)->nullable()->after('vehicle_type'); // LR / RR / BL / AWB No
            $table->date('transport_doc_date')->nullable()->after('transport_doc_no');
            $table->integer('distance_km')->nullable()->after('transport_doc_date');
            $table->text('eway_bill_error')->nullable()->after('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'einvoice_status',
                'irn',
                'ack_no',
                'ack_date',
                'signed_qrcode',
                'signed_invoice',
                'einvoice_error',
                'eway_bill_status',
                'eway_bill_no',
                'eway_bill_date',
                'eway_bill_valid_till',
                'transporter_id',
                'transporter_name',
                'transport_mode',
                'vehicle_no',
                'vehicle_type',
                'transport_doc_no',
                'transport_doc_date',
                'distance_km',
                'eway_bill_error',
            ]);
        });
    }
};

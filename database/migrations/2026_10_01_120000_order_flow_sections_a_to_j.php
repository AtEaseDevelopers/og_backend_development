<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O&G 15 Sept requirements, sections A–J:
 * salesperson ownership + SA locations, order form fields, order type control,
 * quotation versions / customer review, proforma on the order, payment submissions
 * with approvals, admin release, COD block, refund notes, billing gate before CSN,
 * CSN pending-assignment + claim, multi-trip job sheets, in-route lorry transfer,
 * notification history and system settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- configuration ---------------------------------------------------
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // --- A. Salesperson / SA location ------------------------------------
        Schema::create('sa_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->string('code', 20);
            $table->string('name');
            $table->string('csn_prefix', 10);
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('sa_location_id')->nullable()->after('customer_id')->constrained('sa_locations')->nullOnDelete();
            $table->string('ordering_token', 64)->nullable()->unique()->after('sa_location_id');
        });

        // --- C. Customer consent / payment-term ------------------------------
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('pricing_reconfirmation_required')->default(true)->after('portal_approved');
            $table->string('consent_letter_type')->nullable()->after('pricing_reconfirmation_required');
            $table->date('consent_valid_from')->nullable()->after('consent_letter_type');
            $table->date('consent_valid_until')->nullable()->after('consent_valid_from');
            $table->string('consent_document_path')->nullable()->after('consent_valid_until');
            $table->string('default_order_type')->nullable()->after('consent_document_path');
        });

        // --- A/B. Enquiry (order form intake) --------------------------------
        Schema::table('portal_enquiries', function (Blueprint $table) {
            $table->foreignId('salesperson_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->boolean('salesperson_locked')->default(false)->after('salesperson_id');
            $table->string('source', 30)->default('portal')->after('salesperson_locked'); // salesperson_link|portal|walk_in|admin
            $table->foreignId('sa_location_id')->nullable()->after('source')->constrained('sa_locations')->nullOnDelete();
            $table->string('order_type', 20)->nullable()->after('sa_location_id');
            $table->string('payment_method', 30)->nullable()->after('order_type');
            $table->string('customer_do_number')->nullable()->after('payment_method');
            $table->json('attachments')->nullable()->after('payload');
            $table->foreignId('locked_by')->nullable()->after('attachments')->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            $table->timestamp('lock_heartbeat_at')->nullable()->after('locked_at');
            $table->foreignId('attended_by')->nullable()->after('lock_heartbeat_at')->constrained('users')->nullOnDelete();
            $table->timestamp('attended_at')->nullable()->after('attended_by');
        });

        // --- D/E/F/G. Order main record (quotation) --------------------------
        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('number');
            $table->foreignId('root_quotation_id')->nullable()->after('version')->constrained('quotations')->nullOnDelete();
            $table->foreignId('revision_of_id')->nullable()->after('root_quotation_id')->constrained('quotations')->nullOnDelete();
            $table->unsignedInteger('accepted_version')->nullable()->after('revision_of_id');
            $table->string('confirmation_channel', 30)->nullable()->after('accepted_version');
            $table->string('confirmed_by_name')->nullable()->after('confirmation_channel');
            $table->text('consent_evidence')->nullable()->after('confirmed_by_name');
            $table->string('order_type', 20)->nullable()->after('consent_evidence');
            $table->string('payment_method', 30)->nullable()->after('order_type');
            $table->string('customer_do_number')->nullable()->after('payment_method');
            $table->foreignId('sa_location_id')->nullable()->after('customer_do_number')->constrained('sa_locations')->nullOnDelete();
            $table->boolean('salesperson_locked')->default(false)->after('sa_location_id');
            $table->json('attachments')->nullable()->after('salesperson_locked');
            $table->json('destination_types')->nullable()->after('attachments');
            $table->boolean('pricing_reconfirmation_required')->nullable()->after('destination_types');
            $table->string('rejection_category', 40)->nullable()->after('rejection_reason');
            $table->timestamp('pending_review_since')->nullable()->after('sent_at');
            $table->timestamp('closed_at')->nullable()->after('pending_review_since');
            $table->string('closed_reason')->nullable()->after('closed_at');
            $table->boolean('cod_blocked')->default(false)->after('closed_reason');
            $table->string('cod_block_reason')->nullable()->after('cod_blocked');
            $table->foreignId('cod_blocked_by')->nullable()->after('cod_block_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('cod_blocked_at')->nullable()->after('cod_blocked_by');
            $table->timestamp('released_at')->nullable()->after('cod_blocked_at');
            $table->foreignId('released_by')->nullable()->after('released_at')->constrained('users')->nullOnDelete();
            $table->text('release_reason')->nullable()->after('released_by');
            $table->decimal('release_outstanding', 15, 2)->nullable()->after('release_reason');
            $table->string('billing_status', 30)->default('not_started')->after('release_outstanding');
            $table->text('billing_error')->nullable()->after('billing_status');
            $table->timestamp('billed_at')->nullable()->after('billing_error');
            $table->decimal('paid_amount', 15, 2)->default(0)->after('billed_at');
            $table->foreignId('locked_by')->nullable()->after('paid_amount')->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            $table->timestamp('lock_heartbeat_at')->nullable()->after('locked_at');
        });

        // existing rows are their own root
        DB::table('quotations')->whereNull('root_quotation_id')->update(['root_quotation_id' => DB::raw('id')]);

        Schema::table('quotation_destinations', function (Blueprint $table) {
            $table->string('drop_off_type', 30)->nullable()->after('google_maps_url');
            $table->string('service_type', 20)->nullable()->after('drop_off_type');
            $table->decimal('minimum_charge_applied', 15, 2)->nullable()->after('service_type');
        });

        Schema::create('drop_off_min_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('drop_off_type', 30);
            $table->decimal('minimum_charge', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        // --- E. Proforma belongs to the order, not (only) to a CSN -----------
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->dropForeign(['consignment_note_id']);
        });
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('consignment_note_id')->nullable()->change();
            $table->foreign('consignment_note_id')->references('id')->on('consignment_notes')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->after('number')->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->after('quotation_id')->constrained()->nullOnDelete();
            $table->decimal('paid_amount', 15, 2)->default(0)->after('total_amount');
            $table->text('payment_instructions')->nullable()->after('status');
            $table->timestamp('issued_at')->nullable()->after('payment_instructions');
        });

        // --- F. Payment submissions with approval levels ---------------------
        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proforma_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitted_channel', 20)->default('portal'); // portal|counter|admin
            $table->decimal('amount', 15, 2);
            $table->date('payment_date')->nullable();
            $table->string('method', 30);
            $table->string('bank_account')->nullable();
            $table->string('reference')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('level1_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('level1_at')->nullable();
            $table->foreignId('level2_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('level2_at')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('refund_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('bank_account')->nullable();
            $table->string('knock_off_invoice_number')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('draft'); // draft|completed
            $table->string('autocount_sync_status', 20)->default('not_synced');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->foreignId('proforma_invoice_id')->nullable()->after('quotation_id')->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->after('proforma_invoice_id')->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable()->after('autocount_sync_status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
            $table->foreignId('payment_submission_id')->nullable()->after('quotation_id');
            $table->foreignId('approved_by')->nullable()->after('received_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        // --- H. CSN record ----------------------------------------------------
        Schema::table('consignment_notes', function (Blueprint $table) {
            $table->foreignId('salesperson_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            $table->foreignId('sa_location_id')->nullable()->after('salesperson_id')->constrained('sa_locations')->nullOnDelete();
            $table->string('sa_prefix', 10)->nullable()->after('sa_location_id');
            $table->string('order_type', 20)->nullable()->after('billing_type');
            $table->string('invoice_number')->nullable()->after('order_type');
            $table->string('proforma_number')->nullable()->after('invoice_number');
            $table->foreignId('transfer_code_id')->nullable()->after('proforma_number')->constrained('transfer_codes')->nullOnDelete();
            $table->string('customer_do_number')->nullable()->after('transfer_code_id');
            $table->string('service_type', 20)->nullable()->after('customer_do_number');
            $table->string('drop_off_type', 30)->nullable()->after('service_type');
            $table->foreignId('claimed_by')->nullable()->after('storekeeper_id')->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('claimed_by');
            $table->string('claim_channel', 20)->nullable()->after('claimed_at'); // admin|store|driver
            $table->foreignId('assigned_by')->nullable()->after('claim_channel')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by');
            $table->boolean('transfer_claim_pending')->default(false)->after('assigned_at');
        });

        // --- I. Trips: several job sheets per lorry per day ------------------
        Schema::table('job_sheets', function (Blueprint $table) {
            $table->unsignedInteger('trip_no')->default(1)->after('operating_date');
            $table->foreignId('checked_in_lorry_id')->nullable()->after('checked_in_at')->constrained('lorries')->nullOnDelete();
            $table->timestamp('planned_departure_at')->nullable()->after('checked_in_lorry_id');
            $table->timestamp('departed_at')->nullable()->after('planned_departure_at');
            $table->timestamp('arrived_at')->nullable()->after('departed_at');
            $table->timestamp('completed_at')->nullable()->after('arrived_at');
            $table->index('lorry_id', 'job_sheets_lorry_id_idx');
        });
        Schema::table('job_sheets', function (Blueprint $table) {
            $table->dropUnique('job_sheet_lorry_date');
            $table->unique(['lorry_id', 'operating_date', 'trip_no'], 'job_sheet_lorry_date_trip');
        });

        Schema::table('driver_check_ins', function (Blueprint $table) {
            $table->boolean('lorry_confirmed')->default(true)->after('lorry_id');
        });

        // --- J. In-route transfer audit --------------------------------------
        Schema::table('job_sheet_transfers', function (Blueprint $table) {
            $table->foreignId('consignment_note_id')->nullable()->after('delivery_order_id')->constrained()->nullOnDelete();
            $table->foreignId('from_lorry_id')->nullable()->after('to_job_sheet_id')->constrained('lorries')->nullOnDelete();
            $table->foreignId('to_lorry_id')->nullable()->after('from_lorry_id')->constrained('lorries')->nullOnDelete();
            $table->foreignId('from_driver_id')->nullable()->after('to_lorry_id')->constrained('drivers')->nullOnDelete();
            $table->foreignId('to_driver_id')->nullable()->after('from_driver_id')->constrained('drivers')->nullOnDelete();
            $table->boolean('in_route')->default(false)->after('to_driver_id');
            $table->timestamp('handover_at')->nullable()->after('in_route');
            $table->string('handover_location')->nullable()->after('handover_at');
        });

        // --- L (used by D–J). Notification history ---------------------------
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 60);
            $table->string('channel', 20);
            $table->string('status', 20)->default('sent');
            $table->string('recipient_type', 20)->default('customer');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_contact')->nullable();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->text('whatsapp_url')->nullable();
            $table->text('error')->nullable();
            $table->nullableMorphs('notifiable');
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');

        Schema::table('job_sheet_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consignment_note_id');
            $table->dropConstrainedForeignId('from_lorry_id');
            $table->dropConstrainedForeignId('to_lorry_id');
            $table->dropConstrainedForeignId('from_driver_id');
            $table->dropConstrainedForeignId('to_driver_id');
            $table->dropColumn(['in_route', 'handover_at', 'handover_location']);
        });

        Schema::table('driver_check_ins', fn (Blueprint $table) => $table->dropColumn('lorry_confirmed'));

        Schema::table('job_sheets', function (Blueprint $table) {
            $table->dropUnique('job_sheet_lorry_date_trip');
            $table->unique(['lorry_id', 'operating_date'], 'job_sheet_lorry_date');
            $table->dropIndex('job_sheets_lorry_id_idx');
            $table->dropConstrainedForeignId('checked_in_lorry_id');
            $table->dropColumn(['trip_no', 'planned_departure_at', 'departed_at', 'arrived_at', 'completed_at']);
        });

        Schema::table('consignment_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salesperson_id');
            $table->dropConstrainedForeignId('sa_location_id');
            $table->dropConstrainedForeignId('transfer_code_id');
            $table->dropConstrainedForeignId('claimed_by');
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn([
                'sa_prefix', 'order_type', 'invoice_number', 'proforma_number', 'customer_do_number',
                'service_type', 'drop_off_type', 'claimed_at', 'claim_channel', 'assigned_at', 'transfer_claim_pending',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['payment_submission_id', 'approved_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_id');
            $table->dropConstrainedForeignId('proforma_invoice_id');
            $table->dropConstrainedForeignId('payment_id');
            $table->dropColumn('sent_at');
        });

        Schema::dropIfExists('refund_notes');
        Schema::dropIfExists('payment_submissions');

        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['paid_amount', 'payment_instructions', 'issued_at']);
        });

        Schema::dropIfExists('drop_off_min_charges');

        Schema::table('quotation_destinations', fn (Blueprint $table) => $table->dropColumn(['drop_off_type', 'service_type', 'minimum_charge_applied']));

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('root_quotation_id');
            $table->dropConstrainedForeignId('revision_of_id');
            $table->dropConstrainedForeignId('sa_location_id');
            $table->dropConstrainedForeignId('cod_blocked_by');
            $table->dropConstrainedForeignId('released_by');
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn([
                'version', 'accepted_version', 'confirmation_channel', 'confirmed_by_name', 'consent_evidence',
                'order_type', 'payment_method', 'customer_do_number', 'salesperson_locked', 'attachments', 'destination_types',
                'pricing_reconfirmation_required', 'rejection_category', 'pending_review_since', 'closed_at', 'closed_reason',
                'cod_blocked', 'cod_block_reason', 'cod_blocked_at', 'released_at', 'release_reason', 'release_outstanding',
                'billing_status', 'billing_error', 'billed_at', 'paid_amount', 'locked_at', 'lock_heartbeat_at',
            ]);
        });

        Schema::table('portal_enquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salesperson_id');
            $table->dropConstrainedForeignId('sa_location_id');
            $table->dropConstrainedForeignId('locked_by');
            $table->dropConstrainedForeignId('attended_by');
            $table->dropColumn([
                'salesperson_locked', 'source', 'order_type', 'payment_method', 'customer_do_number',
                'attachments', 'locked_at', 'lock_heartbeat_at', 'attended_at',
            ]);
        });

        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn([
            'pricing_reconfirmation_required', 'consent_letter_type', 'consent_valid_from',
            'consent_valid_until', 'consent_document_path', 'default_order_type',
        ]));

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sa_location_id');
            $table->dropColumn('ordering_token');
        });

        Schema::dropIfExists('sa_locations');
        Schema::dropIfExists('system_settings');
    }
};

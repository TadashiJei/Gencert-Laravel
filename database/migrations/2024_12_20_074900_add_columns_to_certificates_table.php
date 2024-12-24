<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('certificate_number')->unique()->after('user_id');
            $table->json('custom_fields')->nullable()->after('recipient_email');
            $table->string('language')->default('en')->after('custom_fields');
            $table->timestamp('issue_date')->nullable()->after('language');
            $table->timestamp('expiry_date')->nullable()->after('issue_date');
            $table->boolean('auto_renewal')->default(false)->after('status');
            $table->foreignId('created_by')->nullable()->after('auto_renewal')->constrained('users');
            $table->dropColumn('format');
            $table->dropColumn('data');
            $table->dropColumn('generated_at');
            $table->dropColumn('sent_at');
        });
    }

    public function down()
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'certificate_number',
                'custom_fields',
                'language',
                'issue_date',
                'expiry_date',
                'auto_renewal',
                'created_by'
            ]);
            $table->string('format')->default('pdf');
            $table->json('data');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_at')->nullable();
        });
    }
};

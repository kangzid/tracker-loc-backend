<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

if (!Schema::hasTable('hris_document_categories')) {
    Schema::create('hris_document_categories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->string('name'); // e.g. KTP, KK, Ijazah, Sertifikat, Kontrak
        $table->string('code')->nullable();
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();

        $table->index(['tenant_id', 'is_active']);
    });
    echo "Created hris_document_categories table!" . PHP_EOL;
}

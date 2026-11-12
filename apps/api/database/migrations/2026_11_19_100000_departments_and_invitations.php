<?php

use App\Support\Departments;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name_en');
            $t->string('name_ar')->nullable();
            // The permissions this department's roles can be built from (its area of work).
            $t->json('scope');
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->boolean('is_department_head')->default(false);
        });

        Schema::create('invitations', function (Blueprint $t) {
            $t->id();
            $t->string('token_hash', 64)->unique();
            $t->string('email');
            $t->string('role');
            $t->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->unsignedBigInteger('partner_id')->nullable();
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->boolean('as_head')->default(false);
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
        });

        Departments::ensureDefault();
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('department_id'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_department_head'));
        Schema::dropIfExists('departments');
    }
};

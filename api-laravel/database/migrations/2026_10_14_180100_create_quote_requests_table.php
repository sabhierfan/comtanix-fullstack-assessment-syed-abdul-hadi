<?php

use App\Enums\QuoteStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('client_name', 120);
            $table->string('email', 191);
            $table->string('company', 150)->nullable();
            $table->string('service_type', 40);
            $table->string('budget_range', 20);
            $table->text('message');
            $table->string('status', 20)->default(QuoteStatus::New->value);
            // Foreign key: must point to a real user. If that user is deleted, it becomes NULL (unassigned).
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitted_ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes are like the index at the back of a book: they let MySQL find rows fast.
            // Each one matches a list filter + "newest first", e.g. WHERE status = ? ORDER BY created_at.
            // The (assigned_to, created_at) index is also used by the foreign key above.
            $table->index(['status', 'created_at']);
            $table->index(['assigned_to', 'created_at']);
            $table->index(['service_type', 'created_at']);
            $table->index('created_at');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};

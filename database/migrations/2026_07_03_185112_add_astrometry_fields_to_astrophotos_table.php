<?php

use App\Enums\AstrometryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->string('astrometry_status')->default(AstrometryStatus::Idle->value)->after('views_count');
            $table->unsignedBigInteger('astrometry_submission_id')->nullable()->after('astrometry_status');
            $table->decimal('ra', 10, 6)->nullable()->after('astrometry_submission_id');
            $table->decimal('dec', 10, 6)->nullable()->after('ra');
            $table->decimal('pixel_scale', 8, 3)->nullable()->after('dec');
            $table->decimal('field_radius', 8, 3)->nullable()->after('pixel_scale');
            $table->decimal('orientation', 6, 2)->nullable()->after('field_radius');
            $table->json('objects_in_field')->nullable()->after('orientation');
            $table->timestamp('astrometry_solved_at')->nullable()->after('objects_in_field');
        });
    }

    public function down(): void
    {
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->dropColumn([
                'astrometry_status',
                'astrometry_submission_id',
                'ra',
                'dec',
                'pixel_scale',
                'field_radius',
                'orientation',
                'objects_in_field',
                'astrometry_solved_at',
            ]);
        });
    }
};

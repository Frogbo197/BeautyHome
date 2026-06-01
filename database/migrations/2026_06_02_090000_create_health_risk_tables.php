<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('risk_rules')) {
            Schema::create('risk_rules', function (Blueprint $table) {
                $table->id('ID');
                $table->string('Code', 100)->unique();
                $table->string('Category', 50)->index();
                $table->string('Severity', 20)->default('low');
                $table->unsignedTinyInteger('Score')->default(0);
                $table->unsignedInteger('CoolingMinutes')->default(1440);
                $table->unsignedTinyInteger('TrendWindowDays')->default(1);
                $table->unsignedTinyInteger('MinOccurrences')->default(1);
                $table->boolean('AdminVisible')->default(false);
                $table->boolean('Enabled')->default(true);
                $table->string('Description', 500)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('risk_events')) {
            Schema::create('risk_events', function (Blueprint $table) {
                $table->id('ID');
                $table->unsignedBigInteger('NguoiDungID')->index();
                $table->unsignedBigInteger('RuleID')->nullable()->index();
                $table->string('RuleCode', 100)->index();
                $table->string('Category', 50)->index();
                $table->string('Severity', 20)->index();
                $table->unsignedTinyInteger('RiskScore')->default(0);
                $table->string('Title', 255);
                $table->text('Message');
                $table->string('Action', 500)->nullable();
                $table->string('Status', 50)->default('open')->index();
                $table->boolean('NotifyUser')->default(true);
                $table->boolean('VisibleToAdmin')->default(false)->index();
                $table->string('SourceTable', 100)->nullable();
                $table->unsignedBigInteger('SourceID')->nullable();
                $table->json('Metadata')->nullable();
                $table->timestamp('FirstDetectedAt')->nullable();
                $table->timestamp('LastDetectedAt')->nullable()->index();
                $table->unsignedInteger('OccurrenceCount')->default(1);
                $table->timestamp('ResolvedAt')->nullable();
                $table->timestamps();
            });
        }

        $now = now('Asia/Ho_Chi_Minh');
        foreach ($this->defaultRules() as $rule) {
            DB::table('risk_rules')->updateOrInsert(
                ['Code' => $rule['Code']],
                array_merge($rule, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_events');
        Schema::dropIfExists('risk_rules');
    }

    private function defaultRules(): array
    {
        return [
            ['Code' => 'water_low_trend', 'Category' => 'water', 'Severity' => 'medium', 'Score' => 35, 'CoolingMinutes' => 1440, 'TrendWindowDays' => 3, 'MinOccurrences' => 3, 'AdminVisible' => false, 'Enabled' => true, 'Description' => 'Low water intake for 3 consecutive days'],
            ['Code' => 'water_high_day', 'Category' => 'water', 'Severity' => 'medium', 'Score' => 45, 'CoolingMinutes' => 1440, 'TrendWindowDays' => 1, 'MinOccurrences' => 1, 'AdminVisible' => true, 'Enabled' => true, 'Description' => 'Unusually high water intake in a day'],
            ['Code' => 'activity_low_trend', 'Category' => 'activity', 'Severity' => 'low', 'Score' => 20, 'CoolingMinutes' => 1440, 'TrendWindowDays' => 3, 'MinOccurrences' => 3, 'AdminVisible' => false, 'Enabled' => true, 'Description' => 'Low movement for 3 consecutive days'],
            ['Code' => 'activity_high_day', 'Category' => 'activity', 'Severity' => 'medium', 'Score' => 40, 'CoolingMinutes' => 1440, 'TrendWindowDays' => 1, 'MinOccurrences' => 1, 'AdminVisible' => true, 'Enabled' => true, 'Description' => 'Activity volume is much higher than user baseline'],
            ['Code' => 'medication_missed_trend', 'Category' => 'medication', 'Severity' => 'medium', 'Score' => 45, 'CoolingMinutes' => 1440, 'TrendWindowDays' => 7, 'MinOccurrences' => 2, 'AdminVisible' => true, 'Enabled' => true, 'Description' => 'Multiple missed medication doses recently'],
            ['Code' => 'medication_over_schedule', 'Category' => 'medication', 'Severity' => 'high', 'Score' => 85, 'CoolingMinutes' => 60, 'TrendWindowDays' => 1, 'MinOccurrences' => 1, 'AdminVisible' => true, 'Enabled' => true, 'Description' => 'Medication taken more times than configured schedule'],
            ['Code' => 'medication_repeated_short_time', 'Category' => 'medication', 'Severity' => 'high', 'Score' => 80, 'CoolingMinutes' => 60, 'TrendWindowDays' => 1, 'MinOccurrences' => 1, 'AdminVisible' => true, 'Enabled' => true, 'Description' => 'Same medication recorded repeatedly in a short period'],
            ['Code' => 'calorie_low_trend', 'Category' => 'nutrition', 'Severity' => 'medium', 'Score' => 35, 'CoolingMinutes' => 1440, 'TrendWindowDays' => 2, 'MinOccurrences' => 2, 'AdminVisible' => false, 'Enabled' => true, 'Description' => 'Very low calorie intake trend'],
            ['Code' => 'calorie_high_day', 'Category' => 'nutrition', 'Severity' => 'medium', 'Score' => 35, 'CoolingMinutes' => 1440, 'TrendWindowDays' => 1, 'MinOccurrences' => 1, 'AdminVisible' => false, 'Enabled' => true, 'Description' => 'High calorie intake in a day'],
            ['Code' => 'weight_fast_change', 'Category' => 'weight', 'Severity' => 'medium', 'Score' => 55, 'CoolingMinutes' => 10080, 'TrendWindowDays' => 7, 'MinOccurrences' => 1, 'AdminVisible' => true, 'Enabled' => true, 'Description' => 'Weight changed rapidly between records'],
            ['Code' => 'health_positive_streak', 'Category' => 'motivation', 'Severity' => 'low', 'Score' => 0, 'CoolingMinutes' => 4320, 'TrendWindowDays' => 5, 'MinOccurrences' => 5, 'AdminVisible' => false, 'Enabled' => true, 'Description' => 'Motivational message for maintaining good habits'],
        ];
    }
};

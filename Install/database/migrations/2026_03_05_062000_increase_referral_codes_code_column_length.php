<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ReferralCode::generateUniqueCode() returns a UUID (36 chars); code was string(20).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite does not support ALTER COLUMN / MODIFY; recreate table with new column size.
            DB::statement('CREATE TABLE referral_codes_temp (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                code VARCHAR(36) NOT NULL,
                slug VARCHAR(50) NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                total_clicks INTEGER NOT NULL DEFAULT 0,
                total_signups INTEGER NOT NULL DEFAULT 0,
                total_conversions INTEGER NOT NULL DEFAULT 0,
                total_earnings REAL NOT NULL DEFAULT 0,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE (code),
                UNIQUE (slug)
            )');
            DB::statement('INSERT INTO referral_codes_temp SELECT id, user_id, code, slug, is_active, total_clicks, total_signups, total_conversions, total_earnings, created_at, updated_at FROM referral_codes');
            DB::statement('DROP TABLE referral_codes');
            DB::statement('ALTER TABLE referral_codes_temp RENAME TO referral_codes');
            DB::statement('CREATE UNIQUE INDEX referral_codes_code_unique ON referral_codes (code)');
            DB::statement('CREATE UNIQUE INDEX referral_codes_slug_unique ON referral_codes (slug)');
            DB::statement('CREATE INDEX referral_codes_user_id_is_active_index ON referral_codes (user_id, is_active)');
        } else {
            DB::statement('ALTER TABLE referral_codes MODIFY code VARCHAR(36) NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE TABLE referral_codes_temp (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                code VARCHAR(20) NOT NULL,
                slug VARCHAR(50) NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                total_clicks INTEGER NOT NULL DEFAULT 0,
                total_signups INTEGER NOT NULL DEFAULT 0,
                total_conversions INTEGER NOT NULL DEFAULT 0,
                total_earnings REAL NOT NULL DEFAULT 0,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE (code),
                UNIQUE (slug)
            )');
            DB::statement('INSERT INTO referral_codes_temp SELECT id, user_id, code, slug, is_active, total_clicks, total_signups, total_conversions, total_earnings, created_at, updated_at FROM referral_codes');
            DB::statement('DROP TABLE referral_codes');
            DB::statement('ALTER TABLE referral_codes_temp RENAME TO referral_codes');
            DB::statement('CREATE UNIQUE INDEX referral_codes_code_unique ON referral_codes (code)');
            DB::statement('CREATE UNIQUE INDEX referral_codes_slug_unique ON referral_codes (slug)');
            DB::statement('CREATE INDEX referral_codes_user_id_is_active_index ON referral_codes (user_id, is_active)');
        } else {
            DB::statement('ALTER TABLE referral_codes MODIFY code VARCHAR(20) NOT NULL');
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('last_name');
        });

        $corporateRoleId = DB::table('roles')->where('name', 'corporate')->value('id');

        if ($corporateRoleId) {
            $users = DB::table('users')->where('role_id', $corporateRoleId)->get();

            foreach ($users as $user) {
                $address = (string) ($user->address ?? '');
                $first = (string) ($user->first_name ?? '');
                $last = (string) ($user->last_name ?? '');

                // Legacy hack: company in first_name, contact stuffed into address.
                if (preg_match('/^(.*) \(Contact: (.+)\)$/u', $address, $matches)) {
                    $contact = trim($matches[2]);
                    $parts = preg_split('/\s+/', $contact, 2);
                    DB::table('users')->where('id', $user->id)->update([
                        'company_name' => $first !== '' ? $first : 'Corporate Partner',
                        'first_name' => $parts[0] ?: 'Buyer',
                        'last_name' => $parts[1] ?? '',
                        'address' => trim($matches[1]),
                    ]);

                    continue;
                }

                // Signup hack without parseable contact: company was stored as first_name alone.
                if ($last === '' && $first !== '') {
                    DB::table('users')->where('id', $user->id)->update([
                        'company_name' => $first,
                        'first_name' => 'Buyer',
                        'last_name' => 'User',
                    ]);

                    continue;
                }

                // Seeded person-style corporate accounts.
                DB::table('users')->where('id', $user->id)->update([
                    'company_name' => 'Middo Demo Corp',
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('company_name');
        });
    }
};

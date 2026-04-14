<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Hash any plaintext session_tokens into the password field.
 * Required before removing the plaintext fallback in AuthService.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Find users with session_token but no hashed password
        $users = DB::table('usuarios')
            ->whereNotNull('session_token')
            ->where('session_token', '!=', '')
            ->where(function ($q) {
                $q->whereNull('password')->orWhere('password', '');
            })
            ->get(['id', 'session_token']);

        foreach ($users as $user) {
            DB::table('usuarios')
                ->where('id', $user->id)
                ->update(['password' => Hash::make($user->session_token)]);
        }
    }

    public function down(): void
    {
        // Cannot reverse — hashed passwords cannot be unhashed
    }
};

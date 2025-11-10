<?php

namespace App\Services;

use App\Enums\TransactionName;
use App\Models\User;
use App\Models\CustomTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CustomWalletService
{
    /**
     * Get user's wallet balance (direct DB query for performance)
     */
    public function getBalance(User $user): float
    {
        return (float) $user->balance;
    }


    /**
     * Deposit amount to user's wallet (high-performance direct DB operation)
     */
    public function deposit(User $user, float $amount, TransactionName $transactionName, array $meta = []): bool
    {
        if ($amount <= 0) {
            return false;
        }

        try {
            DB::transaction(function () use ($user, $amount, $transactionName, $meta) {
                // Lock the user row for update
                $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
                
                $oldBalance = (float) $lockedUser->balance;
                $newBalance = $oldBalance + $amount;

                Log::debug('CustomWalletService::deposit - Before update', [
                    'user_id' => $user->id,
                    'user_name' => $user->user_name,
                    'old_balance' => $oldBalance,
                    'amount' => $amount,
                    'new_balance' => $newBalance,
                    'transaction_name' => $transactionName->value
                ]);

                // Update balance directly in users table
                $lockedUser->update(['balance' => $newBalance]);

                // Refresh to get updated balance
                $lockedUser->refresh();
                $actualNewBalance = (float) $lockedUser->balance;

                Log::debug('CustomWalletService::deposit - After update', [
                    'user_id' => $user->id,
                    'user_name' => $user->user_name,
                    'expected_new_balance' => $newBalance,
                    'actual_new_balance' => $actualNewBalance,
                    'update_successful' => $actualNewBalance == $newBalance
                ]);

                // Log transaction
                $this->logTransaction($user, $user, $amount, 'deposit', $transactionName, $oldBalance, $newBalance, $meta);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('CustomWalletService::deposit failed', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Withdraw amount from user's wallet (high-performance direct DB operation)
     */
    public function withdraw(User $user, float $amount, TransactionName $transactionName, array $meta = []): bool
    {
        if ($amount <= 0) {
            return false;
        }

        try {
            DB::transaction(function () use ($user, $amount, $transactionName, $meta) {
                // Lock the user row for update
                $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
                
                if (!$lockedUser || $lockedUser->balance < $amount) {
                    throw new \Exception('Insufficient balance');
                }

                $oldBalance = (float) $lockedUser->balance;
                $newBalance = $oldBalance - $amount;

                Log::debug('CustomWalletService::withdraw - Before update', [
                    'user_id' => $user->id,
                    'user_name' => $user->user_name,
                    'old_balance' => $oldBalance,
                    'amount' => $amount,
                    'new_balance' => $newBalance,
                    'transaction_name' => $transactionName->value
                ]);

                // Update balance directly in users table
                $lockedUser->update(['balance' => $newBalance]);

                // Refresh to get updated balance
                $lockedUser->refresh();
                $actualNewBalance = (float) $lockedUser->balance;

                Log::debug('CustomWalletService::withdraw - After update', [
                    'user_id' => $user->id,
                    'user_name' => $user->user_name,
                    'expected_new_balance' => $newBalance,
                    'actual_new_balance' => $actualNewBalance,
                    'update_successful' => $actualNewBalance == $newBalance
                ]);

                // Log transaction
                $this->logTransaction($user, $user, $amount, 'withdraw', $transactionName, $oldBalance, $newBalance, $meta);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('CustomWalletService::withdraw failed', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Transfer amount between users (high-performance direct DB operation)
     */
    public function transfer(User $from, User $to, float $amount, TransactionName $transactionName, array $meta = []): bool
    {
        if ($amount <= 0) {
            return false;
        }

        try {
            DB::transaction(function () use ($from, $to, $amount, $transactionName, $meta) {
                // Lock both users for update (order by ID to prevent deadlocks)
                $fromUser = User::where('id', $from->id)->lockForUpdate()->first();
                $toUser = User::where('id', $to->id)->lockForUpdate()->first();

                if ($fromUser->balance < $amount) {
                    throw new \Exception('Insufficient balance for transfer');
                }

                $fromOldBalance = (float) $fromUser->balance;
                $toOldBalance = (float) $toUser->balance;
                $fromNewBalance = $fromOldBalance - $amount;
                $toNewBalance = $toOldBalance + $amount;

                // Update both balances directly in users table
                $fromUser->update(['balance' => $fromNewBalance]);
                $toUser->update(['balance' => $toNewBalance]);

                // Log both transactions
                $this->logTransaction($from, $to, $amount, 'withdraw', $transactionName, $fromOldBalance, $fromNewBalance, $meta);
                $this->logTransaction($to, $from, $amount, 'deposit', $transactionName, $toOldBalance, $toNewBalance, $meta);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('CustomWalletService::transfer failed', [
                'from_user_id' => $from->id,
                'to_user_id' => $to->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Force transfer (admin operation) - bypasses balance checks
     */
    public function forceTransfer(User $from, User $to, float $amount, TransactionName $transactionName, array $meta = []): bool
    {
        if ($amount <= 0) {
            return false;
        }

        try {
            DB::transaction(function () use ($from, $to, $amount, $transactionName, $meta) {
                // Lock both users for update
                $fromUser = User::where('id', $from->id)->lockForUpdate()->first();
                $toUser = User::where('id', $to->id)->lockForUpdate()->first();

                $fromOldBalance = (float) $fromUser->balance;
                $toOldBalance = (float) $toUser->balance;
                $fromNewBalance = $fromOldBalance - $amount;
                $toNewBalance = $toOldBalance + $amount;

                // Update both balances (force transfer allows negative balance)
                $fromUser->update(['balance' => $fromNewBalance]);
                $toUser->update(['balance' => $toNewBalance]);

                // Log both transactions
                $this->logTransaction($from, $to, $amount, 'withdraw', $transactionName, $fromOldBalance, $fromNewBalance, array_merge($meta, ['forced' => true]));
                $this->logTransaction($to, $from, $amount, 'deposit', $transactionName, $toOldBalance, $toNewBalance, array_merge($meta, ['forced' => true]));
            });

            return true;
        } catch (\Exception $e) {
            Log::error('CustomWalletService::forceTransfer failed', [
                'from_user_id' => $from->id,
                'to_user_id' => $to->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if user has sufficient balance
     */
    public function hasBalance(User $user, float $amount): bool
    {
        return $user->balance >= $amount;
    }


    /**
     * Get transaction history for a user
     */
    public function getTransactionHistory(User $user, int $limit = 50, int $offset = 0)
    {
        return CustomTransaction::where('user_id', $user->id)
            ->orWhere('target_user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /**
     * Log transaction for audit trail
     */
    private function logTransaction(User $user, User $targetUser, float $amount, string $type, TransactionName $transactionName, float $oldBalance, float $newBalance, array $meta = []): void
    {
        CustomTransaction::create([
            'user_id' => $user->id,
            'target_user_id' => $targetUser->id,
            'amount' => $amount,
            'type' => $type,
            'transaction_name' => $transactionName->value,
            'old_balance' => $oldBalance,
            'new_balance' => $newBalance,
            'meta' => json_encode($meta),
            'uuid' => Str::uuid()->toString(),
            'confirmed' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Initialize wallet for new user (balance already exists in users table)
     */
    public function initializeWallet(User $user, float $initialBalance = 0.0): bool
    {
        try {
            // Balance is already initialized in users table, just ensure it's set
            if ($user->balance == 0 && $initialBalance > 0) {
                $user->update(['balance' => $initialBalance]);
            }
            return true;
        } catch (\Exception $e) {
            Log::error('CustomWalletService::initializeWallet failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get wallet statistics
     */
    public function getWalletStats(): array
    {
        $totalUsers = User::count();
        $totalBalance = User::sum('balance');
        $activeUsers = User::where('status', 1)->count();

        return [
            'total_users' => $totalUsers,
            'total_balance' => $totalBalance,
            'active_users' => $activeUsers,
            'average_balance' => $totalUsers > 0 ? $totalBalance / $totalUsers : 0
        ];
    }
}

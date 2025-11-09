<?php

namespace App\Services;

use App\Enums\TransactionName;
use App\Enums\UserType;
use App\Models\TransferLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class WalletService
{
    /**
     * Credit balance to a user (e.g. capital injection).
     */
    public function deposit(User $recipient, int|float|string $amount, TransactionName $transactionName, array $meta = []): User
    {
        $normalizedAmount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($recipient, $normalizedAmount, $transactionName, $meta) {
            $recipient = $this->lockUser($recipient->id);
            $recipient->balance = $this->addAmount($recipient->balance, $normalizedAmount);
            $recipient->save();

            $this->recordTransferLog(null, $recipient, $normalizedAmount, $transactionName, $meta);

            return $recipient->refresh();
        });
    }

    /**
     * Debit balance from a user (e.g. manual adjustment).
     */
    public function withdraw(User $user, int|float|string $amount, TransactionName $transactionName, array $meta = []): User
    {
        $normalizedAmount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($user, $normalizedAmount, $transactionName, $meta) {
            $user = $this->lockUser($user->id);
            $this->ensureSufficientBalance($user, $normalizedAmount);

            $user->balance = $this->subtractAmount($user->balance, $normalizedAmount);
            $user->save();

            $this->recordTransferLog($user, null, $normalizedAmount, $transactionName, $meta);

            return $user->refresh();
        });
    }

    /**
     * Transfer balance from one user to another following hierarchy rules.
     */
    public function transfer(User $from, User $to, int|float|string $amount, TransactionName $transactionName, array $meta = []): void
    {
        $normalizedAmount = $this->normalizeAmount($amount);
        $this->validateTransferFlow($from, $to);

        DB::transaction(function () use ($from, $to, $normalizedAmount, $transactionName, $meta) {
            $fromLocked = $this->lockUser($from->id);
            $toLocked = $this->lockUser($to->id);

            $this->ensureSufficientBalance($fromLocked, $normalizedAmount);

            $fromLocked->balance = $this->subtractAmount($fromLocked->balance, $normalizedAmount);
            $toLocked->balance = $this->addAmount($toLocked->balance, $normalizedAmount);

            $fromLocked->save();
            $toLocked->save();

            $this->recordTransferLog($fromLocked, $toLocked, $normalizedAmount, $transactionName, $meta);
        });
    }

    private function normalizeAmount(int|float|string $amount): int
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Amount must be numeric.');
        }

        $normalized = (int) $amount;

        if ($normalized <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        return $normalized;
    }

    private function lockUser(int $userId): User
    {
        return User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
    }

    private function ensureSufficientBalance(User $user, int $amount): void
    {
        if ((int) $user->balance < $amount) {
            throw new RuntimeException("User {$user->id} has insufficient balance.");
        }
    }

    private function validateTransferFlow(User $from, User $to): void
    {
        $fromType = $this->resolveUserType($from);
        $toType = $this->resolveUserType($to);

        $isOwnerToAgent = $fromType === UserType::Owner && $toType === UserType::Agent && $to->agent_id === $from->id;
        $isAgentToPlayer = $fromType === UserType::Agent && $toType === UserType::Player && $to->agent_id === $from->id;
        $isSystemToOwner = $fromType === UserType::SystemWallet && $toType === UserType::Owner;

        if ($isOwnerToAgent || $isAgentToPlayer || $isSystemToOwner) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Transfers from %s to %s are not permitted.',
            $fromType->name,
            $toType->name
        ));
    }

    private function resolveUserType(User $user): UserType
    {
        return UserType::from((int) $user->type);
    }

    private function addAmount(string|int $currentBalance, int $amount): string
    {
        return (string) ((int) $currentBalance + $amount);
    }

    private function subtractAmount(string|int $currentBalance, int $amount): string
    {
        return (string) ((int) $currentBalance - $amount);
    }

    private function recordTransferLog(?User $from, ?User $to, int $amount, TransactionName $transactionName, array $meta = []): void
    {
        if (! $from || ! $to) {
            return;
        }

        TransferLog::create([
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'amount' => $this->formatAmountForLog($amount),
            'type' => $transactionName->value,
            'description' => $meta['description'] ?? null,
            'meta' => empty($meta) ? null : $meta,
        ]);
    }

    private function formatAmountForLog(int $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}


<?php

namespace App\Services;

use App\Enums\TransactionName;
use App\Enums\UserType;
use App\Models\CustomTransaction;
use App\Models\TransferLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class WalletService
{
    private const SCALE = 4;

    /**
     * Credit balance to a user (e.g. capital injection).
     */
    public function deposit(User $recipient, int|float|string $amount, TransactionName $transactionName, array $meta = []): User
    {
        $normalizedAmount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($recipient, $normalizedAmount, $transactionName, $meta) {
            $recipient = $this->lockUser($recipient->id);
            $beforeBalance = $this->toScaledString($recipient->balance);

            $recipient->balance = $this->addAmount($beforeBalance, $normalizedAmount);
            $recipient->save();
            $afterBalance = $this->toScaledString($recipient->balance);

            $this->recordTransferLog(null, $recipient, $normalizedAmount, $transactionName, $meta);
            $this->recordCustomTransaction(
                $recipient,
                'deposit',
                $transactionName,
                $normalizedAmount,
                $beforeBalance,
                $afterBalance,
                $meta
            );

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
            $beforeBalance = $this->toScaledString($user->balance);
            $this->ensureSufficientBalance($beforeBalance, $normalizedAmount, $user);

            $user->balance = $this->subtractAmount($beforeBalance, $normalizedAmount);
            $user->save();
            $afterBalance = $this->toScaledString($user->balance);

            $this->recordTransferLog($user, null, $normalizedAmount, $transactionName, $meta);
            $this->recordCustomTransaction(
                $user,
                'withdraw',
                $transactionName,
                $normalizedAmount,
                $beforeBalance,
                $afterBalance,
                $meta
            );

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

            $fromBefore = $this->toScaledString($fromLocked->balance);
            $toBefore = $this->toScaledString($toLocked->balance);

            $this->ensureSufficientBalance($fromBefore, $normalizedAmount, $fromLocked);

            $fromLocked->balance = $this->subtractAmount($fromBefore, $normalizedAmount);
            $toLocked->balance = $this->addAmount($toBefore, $normalizedAmount);

            $fromLocked->save();
            $toLocked->save();

            $fromAfter = $this->toScaledString($fromLocked->balance);
            $toAfter = $this->toScaledString($toLocked->balance);

            $this->recordTransferLog($fromLocked, $toLocked, $normalizedAmount, $transactionName, $meta);

            $this->recordCustomTransaction(
                $fromLocked,
                'withdraw',
                $transactionName,
                $normalizedAmount,
                $fromBefore,
                $fromAfter,
                $meta + ['direction' => 'debit']
            );

            $this->recordCustomTransaction(
                $toLocked,
                'deposit',
                $transactionName,
                $normalizedAmount,
                $toBefore,
                $toAfter,
                $meta + ['direction' => 'credit']
            );
        });
    }

    private function normalizeAmount(int|float|string $amount): string
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Amount must be numeric.');
        }

        $normalized = $this->toScaledString($amount);

        if (bccomp($normalized, '0', self::SCALE) <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        return $normalized;
    }

    private function lockUser(int $userId): User
    {
        return User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
    }

    private function ensureSufficientBalance(string $currentBalance, string $amount, User $user): void
    {
        if (bccomp($currentBalance, $amount, self::SCALE) < 0) {
            throw new RuntimeException("User {$user->id} has insufficient balance.");
        }
    }

    private function validateTransferFlow(User $from, User $to): void
    {
        $fromType = $this->resolveUserType($from);
        $toType = $this->resolveUserType($to);

        $isOwnerToAgent = $fromType === UserType::Owner
            && $toType === UserType::Agent
            && $to->agent_id === $from->id;
        $isAgentToPlayer = $fromType === UserType::Agent
            && $toType === UserType::Player
            && $to->agent_id === $from->id;
        $isSystemToOwner = $fromType === UserType::SystemWallet && $toType === UserType::Owner;
        $isAgentToOwner = $fromType === UserType::Agent
            && $toType === UserType::Owner
            && $from->agent_id === $to->id;
        $isPlayerToAgent = $fromType === UserType::Player
            && $toType === UserType::Agent
            && $from->agent_id === $to->id;

        if ($isOwnerToAgent || $isAgentToPlayer || $isSystemToOwner || $isAgentToOwner || $isPlayerToAgent) {
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

    private function addAmount(string|int|float $currentBalance, string $amount): string
    {
        return bcadd($this->toScaledString($currentBalance), $amount, self::SCALE);
    }

    private function subtractAmount(string|int|float $currentBalance, string $amount): string
    {
        return bcsub($this->toScaledString($currentBalance), $amount, self::SCALE);
    }

    private function recordTransferLog(?User $from, ?User $to, string $amount, TransactionName $transactionName, array $meta = []): void
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

    private function formatAmountForLog(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function recordCustomTransaction(
        User $user,
        string $type,
        TransactionName $transactionName,
        string $amount,
        string $beforeBalance,
        string $afterBalance,
        array $meta = []
    ): void {
        CustomTransaction::create([
            'user_id' => $user->id,
            'transaction_name' => $transactionName->value,
            'type' => $type,
            'amount' => $amount,
            'before_balance' => $beforeBalance,
            'after_balance' => $afterBalance,
            'meta' => empty($meta) ? null : $meta,
        ]);
    }

    private function toScaledString(string|int|float $value): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Amount must be numeric.');
        }

        return bcadd((string) $value, '0', self::SCALE);
    }
}

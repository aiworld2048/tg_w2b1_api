<?php

namespace Database\Seeders;

use App\Enums\TransactionName;
use App\Enums\UserType;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $walletService = new WalletService;

        // Create owner with large initial capital
        $owner = $this->createUser(
            UserType::Owner,
            'Owner',
            'OWNER001',
            '09123456789',
            null,
            'OWNER'.Str::random(6)
        );
        $walletService->deposit($owner, 500_000_000, TransactionName::CapitalDeposit);

        // Create system wallet
        $systemWallet = $this->createUser(
            UserType::SystemWallet,
            'System Wallet',
            'SYS001',
            '09222222222',
            null,
            'SYS'.Str::random(6)
        );
        $walletService->deposit($systemWallet, 500 * 100_0000, TransactionName::CapitalDeposit);

        $agentK = $this->createUser(
            UserType::Agent,
            'Agent K',
            'AGENT1000',
            '0911234561',
            $owner->id,
            'PONEWINE22X'
        );
        $walletService->transfer($owner, $agentK, 2_000_000, TransactionName::CreditTransfer);

        // Create 10 agents
        for ($i = 1; $i <= 10; $i++) {
            $agent = $this->createUser(
                UserType::Agent,
                "Agent $i",
                'AGENTPW'.str_pad($i, 3, '0', STR_PAD_LEFT),
                '091123456'.str_pad($i, 2, '0', STR_PAD_LEFT),
                $owner->id,
                'AGENTPW'.Str::random(6)
            );
            // Random initial balance between 1.5M to 2.5M
            $initialBalance = rand(15, 25) * 100_000;
            $walletService->transfer($owner, $agent, $initialBalance, TransactionName::CreditTransfer);

            // Create players directly under each agent (no sub-agents)
            for ($k = 1; $k <= 4; $k++) {
                $player = $this->createUser(
                    UserType::Player,
                    "Player $i-$k",
                    'PLAYER'.str_pad($i, 2, '0', STR_PAD_LEFT).str_pad($k, 2, '0', STR_PAD_LEFT),
                    '091111111'.str_pad($i, 1, '0', STR_PAD_LEFT).str_pad($k, 2, '0', STR_PAD_LEFT),
                    $agent->id,
                    'PLAYER'.Str::random(6)
                );
                // Fixed initial balance of 10,000
                $initialBalance = 10000;
                $walletService->transfer($agent, $player, $initialBalance, TransactionName::CreditTransfer);
            }
        }

        // Add SKP0101 player with overwrite functionality
        $this->addPlayerSKP0101($owner->id, $walletService);
    }

    private function addPlayerSKP0101(int $ownerId, WalletService $walletService): void
    {
        // Find first agent to assign this player to
        $agent = User::where('type', UserType::Agent->value)
            ->where('agent_id', $ownerId)
            ->first();

        if (! $agent) {
            throw new \Exception('No agent found to assign SKP0101 player to');
        }

        // Create or refresh player
        $player = User::updateOrCreate(
            ['user_name' => 'SKP0101'],
            [
                'name' => 'SKP Player',
                'phone' => '09123456789',
                'password' => Hash::make('gscplus'),
                'agent_id' => $agent->id,
                'status' => 1,
                'is_changed_password' => 1,
                'type' => UserType::Player->value,
                'referral_code' => 'SKP'.Str::random(6),
            ]
        );
        $player->balance = 0;
        $player->save();

        // Initial balance of 10,000
        $walletService->transfer($agent, $player, 10000, TransactionName::CreditTransfer);

        echo "Created player SKP0101\n";
    }

    private function createUser(
        UserType $type,
        string $name,
        string $user_name,
        string $phone,
        ?int $parent_id = null,
        ?string $referral_code = null
    ): User {
        return User::create([
            'name' => $name,
            'user_name' => $user_name,
            'phone' => $phone,
            'password' => Hash::make('gscplus'),
            'agent_id' => $parent_id,
            'status' => 1,
            'is_changed_password' => 1,
            'type' => $type->value,
            'referral_code' => $referral_code,
        ]);
    }
}

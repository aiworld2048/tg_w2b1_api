<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GameTypeProductTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gameTypeIds = DB::table('game_types')
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [Str::upper($code) => $id])
            ->all();

        $productMappings = [
            1 => ['type' => 'SLOT', 'image' => 'PG_Soft.png'],
            2 => ['type' => 'LIVE_CASINO_PREMIUM', 'image' => 'Pragmatic_Play_Casino.png'],
            3 => ['type' => 'LIVE_CASINO', 'image' => 'Pragmatic_Play_Casino.png'],
            4 => ['type' => 'SLOT', 'image' => 'pp_play.png'],
            5 => ['type' => 'SLOT', 'image' => 'live_22.png'],
            6 => ['type' => 'SLOT', 'image' => 'cq_9.png'],
            7 => ['type' => 'FISHING', 'image' => 'Cq9Fishing.png'],
            8 => ['type' => 'SLOT', 'image' => 'ji_li.png'],
            9 => ['type' => 'FISHING', 'image' => 'JiliFishing.png'],
            10 => ['type' => 'LIVE_CASINO', 'image' => 'Jili-tcg_Casino.png'],
            11 => ['type' => 'POKER', 'image' => 'jili.png'],
            12 => ['type' => 'SLOT', 'image' => 'j_db.png'],
            13 => ['type' => 'FISHING', 'image' => 'JDBFishing.png'],
            14 => ['type' => 'OTHER', 'image' => 'j_db.png'],
            15 => ['type' => 'SLOT', 'image' => 'joker.png'],
            16 => ['type' => 'OTHER', 'image' => 'jo_ker.png'],
            17 => ['type' => 'FISHING', 'image' => 'JokerFishing.png'],
            18 => ['type' => 'SPORT_BOOK', 'image' => 'SBO_bet_Sport_Book.png'],
            19 => ['type' => 'LIVE_CASINO', 'image' => 'Yee_Bet_Casino.png'],
            20 => ['type' => 'LIVE_CASINO', 'image' => 'playtech.png'],
            21 => ['type' => 'SLOT', 'image' => 'playtech.png'],
            22 => ['type' => 'LIVE_CASINO', 'image' => 'AP.png'],
            23 => ['type' => 'LIVE_CASINO', 'image' => 'Sa-Gaming_Casino.png'],
            24 => ['type' => 'SLOT', 'image' => 'spadegaming.png'],
            25 => ['type' => 'FISHING', 'image' => 'SpadegamingFishing.png'],
            26 => ['type' => 'LIVE_CASINO', 'image' => 'wm_new_casino.png'],
            27 => ['type' => 'SLOT', 'image' => 'ha_banero.png'],
            28 => ['type' => 'SPORT_BOOK', 'image' => 'Wbet_Sport_Book.png'],
            29 => ['type' => 'LIVE_CASINO', 'image' => 'Playace_casino.png'],
            30 => ['type' => 'COCK_FIGHTING', 'image' => 'S_Sport.png'],
            31 => ['type' => 'SPORT_BOOK', 'image' => 'Saba_Sport_Book.png'],
            32 => ['type' => 'LIVE_CASINO', 'image' => 'Dream_Gaming_Casino.png'],
            33 => ['type' => 'LIVE_CASINO', 'image' => 'BGaming.png'],
            34 => ['type' => 'FISHING', 'image' => 'BigGammingFishing.png'],
            35 => ['type' => 'SLOT', 'image' => 'Advantplay.png'],
            36 => ['type' => 'SLOT', 'image' => 'Play_Star.png'],
            37 => ['type' => 'SLOT', 'image' => 'play_star_slot.png'],
            38 => ['type' => 'FISHING', 'image' => 'play_star_slot.png'],
            39 => ['type' => 'SLOT', 'image' => 'BGaming.png'],
            40 => ['type' => 'SLOT', 'image' => 'MrSlotty.png'],
            41 => ['type' => 'SLOT', 'image' => 'MrSlotty.png'],
            42 => ['type' => 'SLOT', 'image' => 'MrSlotty.png'],
            43 => ['type' => 'SLOT', 'image' => 'MrSlotty.png'],
            44 => ['type' => 'SLOT', 'image' => 'Gaming_World.png'],
            45 => ['type' => 'SLOT', 'image' => 'MrSlotty.png'],
            46 => ['type' => 'SLOT', 'image' => 'MrSlotty.png'],
            47 => ['type' => 'SLOT', 'image' => 'MrSlotty.png'],
            48 => ['type' => 'SLOT', 'image' => 'MrSlotty.png'],
            49 => ['type' => 'SLOT', 'image' => 'MrSlotty.png'],
            50 => ['type' => 'SLOT', 'image' => 'MrSlotty.png'],
            51 => ['type' => 'SLOT', 'image' => 'MrSlotty.png'],
            52 => ['type' => 'SLOT', 'image' => 'Playace.png'],
            53 => ['type' => 'LIVE_CASINO', 'image' => 'Playace_casino.png'],
            54 => ['type' => 'SLOT', 'image' => 'booming_game.png'],
            55 => ['type' => 'OTHER', 'image' => 'spribe.png'],
            56 => ['type' => 'POKER', 'image' => 'Wow-gamming.png'],
            57 => ['type' => 'SLOT', 'image' => 'Wow-gamming.png'],
            58 => ['type' => 'LIVE_CASINO', 'image' => 'ai_livecasino.png'],
            59 => ['type' => 'SLOT', 'image' => 'Hacksaw.png'],
            60 => ['type' => 'SLOT', 'image' => 'Bigpot.png'],
            61 => ['type' => 'OTHER', 'image' => 'imoon.jfif'],
            62 => ['type' => 'SLOT', 'image' => 'Pascal-gaming.png'],
            63 => ['type' => 'SLOT', 'image' => 'Epicwin.png'],
            64 => ['type' => 'SLOT', 'image' => 'Fachi.png'],
            65 => ['type' => 'FISHING', 'image' => 'FachaiFishing.png'],
            66 => ['type' => 'SLOT', 'image' => 'novomatic.png'],
            67 => ['type' => 'SLOT', 'image' => 'novomatic.png'],
            68 => ['type' => 'SPORT_BOOK', 'image' => 'S_Sport.png'],
            69 => ['type' => 'OTHER', 'image' => 'aviatrix.jfif'],
            70 => ['type' => 'SLOT', 'image' => 'SmartSoft.png'],
            71 => ['type' => 'LIVE_CASINO', 'image' => 'WorldEntertainment.png'],
            72 => ['type' => 'SPORT_BOOK', 'image' => 'WorldEntertainment.png'],
            73 => ['type' => 'SLOT', 'image' => 'WorldEntertainment.png'],
            74 => ['type' => 'SPORT_BOOK', 'image' => 'S_Sport.png'],
            75 => ['type' => 'SLOT', 'image' => 'rich_88.png'],
            76 => ['type' => 'LIVE_CASINO', 'image' => 'King_855_Casino.png'],
            77 => ['type' => 'LIVE_CASINO', 'image' => 'king_855.png'],
            78 => ['type' => 'LIVE_CASINO', 'image' => 'AP.png'],
            79 => ['type' => 'LIVE_CASINO', 'image' => 'ai_livecasino.png'],
            80 => ['type' => 'SPORT_BOOK', 'image' => 'S_Sport.png'],
        ];

        DB::table('game_type_product')->truncate();

        $records = [];

        foreach ($productMappings as $productId => $config) {
            $code = Str::upper($config['type']);
            $code = $code === 'OTHER' ? 'OTHERS' : $code;

            if (! isset($gameTypeIds[$code])) {
                throw new InvalidArgumentException("Unknown game type code [{$config['type']}] for product [{$productId}].");
            }

            $imagePath = public_path('assets/img/game_logo/' . $config['image']);

            if (! File::exists($imagePath)) {
                throw new InvalidArgumentException("Logo file [{$config['image']}] not found for product [{$productId}].");
            }

            $records[] = [
                'product_id' => $productId,
                'game_type_id' => $gameTypeIds[$code],
                'image' => $config['image'],
                'rate' => $config['rate'] ?? 1.0000,
            ];
        }

        DB::table('game_type_product')->insert($records);
    }
}

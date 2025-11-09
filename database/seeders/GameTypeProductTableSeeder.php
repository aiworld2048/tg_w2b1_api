<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameTypeProductTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            // Corrected mappings based on products.sql data
            // Product 1: Yee Bet - LIVE_CASINO (game_type_id: 2)
            ['product_id' => 1, 'game_type_id' => 2, 'image' => 'Yee_Bet_Casino.png', 'rate' => 1.0000],

            // Product 2: SA Gaming - LIVE_CASINO (game_type_id: 2)
            ['product_id' => 2, 'game_type_id' => 2, 'image' => 'Sa-Gaming_Casino.png', 'rate' => 1.0000],

            // Product 3: SpadeGaming - SLOT (game_type_id: 1)
            ['product_id' => 3, 'game_type_id' => 1, 'image' => 'spadegaming.png', 'rate' => 1.0000],

            // Product 4: Live22 - SLOT (game_type_id: 1)
            ['product_id' => 4, 'game_type_id' => 1, 'image' => 'live_22.png', 'rate' => 1.0000],

            // Product 5: WMCasino - LIVE_CASINO (game_type_id: 2)
            ['product_id' => 5, 'game_type_id' => 2, 'image' => 'wm_new_casino.png', 'rate' => 1.0000],

            // Product 6: PG Soft - SLOT (game_type_id: 1)
            ['product_id' => 6, 'game_type_id' => 1, 'image' => 'PG_Soft.png', 'rate' => 1.0000],

            // Product 7: PragmaticPlay - LIVE_CASINO (game_type_id: 2)
            ['product_id' => 7, 'game_type_id' => 2, 'image' => 'Pragmatic_Play_Casino.png', 'rate' => 1.0000],

            // Product 8: PragmaticPlay - SLOT (game_type_id: 1)
            ['product_id' => 8, 'game_type_id' => 1, 'image' => 'pp_play.png', 'rate' => 1.0000],

            // Product 9: PragmaticPlay - LIVE_CASINO_PREMIUM (game_type_id: 14)
            ['product_id' => 9, 'game_type_id' => 14, 'image' => 'Pragmatic_Play_Casino.png', 'rate' => 1.0000],

            // Product 10: Dream Gaming - LIVE_CASINO (game_type_id: 2)
            ['product_id' => 10, 'game_type_id' => 2, 'image' => 'Dream_Gaming_Casino.png', 'rate' => 1.0000],

            // Product 11: AdvantPlay - SLOT (game_type_id: 1)
            ['product_id' => 11, 'game_type_id' => 1, 'image' => 'Advantplay.png', 'rate' => 1.0000],

            // Product 12: JDB - SLOT (game_type_id: 1)
            ['product_id' => 12, 'game_type_id' => 1, 'image' => 'j_db.png', 'rate' => 1.0000],

            // Product 13: PlayStar - SLOT (game_type_id: 1)
            ['product_id' => 13, 'game_type_id' => 1, 'image' => 'play_star_slot.png', 'rate' => 1.0000],

            // Product 14: CQ9 - SLOT (game_type_id: 1)
            ['product_id' => 14, 'game_type_id' => 1, 'image' => 'cq_9.png', 'rate' => 1.0000],

            // Product 15: Jili - SLOT (game_type_id: 1)
            ['product_id' => 15, 'game_type_id' => 1, 'image' => 'ji_li.png', 'rate' => 1.0000],

            // Product 16: PlayAce - LIVE_CASINO (game_type_id: 2)
            ['product_id' => 16, 'game_type_id' => 2, 'image' => 'Playace_casino.png', 'rate' => 1.0000],

            // Product 17: BoomingGames - SLOT (game_type_id: 1)
            ['product_id' => 17, 'game_type_id' => 1, 'image' => 'booming_game.png', 'rate' => 1.0000],

            // Product 18: WOW GAMING - SLOT (game_type_id: 1)
            ['product_id' => 18, 'game_type_id' => 1, 'image' => 'Wow-gamming.png', 'rate' => 1.0000],

            // Product 19: AI Live Casino - LIVE_CASINO (game_type_id: 2)
            ['product_id' => 19, 'game_type_id' => 2, 'image' => 'ai_livecasino.png', 'rate' => 1.0000],

            // Product 20: HACKSAW - SLOT (game_type_id: 1)
            ['product_id' => 20, 'game_type_id' => 1, 'image' => 'Hacksaw.png', 'rate' => 1.0000],

            // Product 21: BIGPOT - SLOT (game_type_id: 1)
            ['product_id' => 21, 'game_type_id' => 1, 'image' => 'Bigpot.png', 'rate' => 1.0000],

            // Product 22: EPICWIN - SLOT (game_type_id: 1)
            ['product_id' => 22, 'game_type_id' => 1, 'image' => 'Epicwin.png', 'rate' => 1.0000],

            // Product 23: Fachai - SLOT (game_type_id: 1)
            ['product_id' => 23, 'game_type_id' => 1, 'image' => 'Fachi.png', 'rate' => 1.0000],

            // Product 24: N2 (NOVOMatic) - SLOT (game_type_id: 1)
            ['product_id' => 24, 'game_type_id' => 1, 'image' => 'novomatic.png', 'rate' => 1.0000],

            // Product 25: SmartSoft - SLOT (game_type_id: 1)
            ['product_id' => 25, 'game_type_id' => 1, 'image' => 'SmartSoft.png', 'rate' => 1.0000],
        ];

        DB::table('game_type_product')->insert($data);
    }
}

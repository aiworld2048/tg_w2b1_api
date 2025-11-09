<?php

namespace App\Enums;

enum DigitTransactionName: string
{
    use HasLabelTrait;

    case Morning = 'morning';
    case Evening = 'evening';
    case DigitBreak = 'two_limit_digit_break';
    case Game_Type = 'two_digit';
    case TwoDigitBet = 'two_digit_bet';
    case TwoDigitBetWin = 'two_digit_bet_win';
    case TwoDigitBetLoss = 'two_digit_bet_loss';
    case TwoDigitBetCancel = 'two_digit_bet_cancel';
    case TwoDigitBetRollback = 'two_digit_bet_rollback';
    case TwoDigitBetBuyIn = 'two_digit_bet_buy_in';
    case TwoDigitBetBuyOut = 'two_digit_bet_buy_out';
    case ThreeDigitBet = 'three_digit_bet';
    case ThreeDigitBetWin = 'three_digit_bet_win';
    case ThreeDigitBetLoss = 'three_digit_bet_loss';
    case ThreeDigitBetCancel = 'three_digit_bet_cancel';
    case ThreeDigitBetRollback = 'three_digit_bet_rollback';
    case ThreeDigitBetBuyIn = 'three_digit_bet_buy_in';
    case ThreeDigitBetBuyOut = 'three_digit_bet_buy_out';

}

<?php

namespace App\Enums;

enum UserType: int
{
    case Owner = 10;
    case Agent = 20;
    case Player = 30;
    case SystemWallet = 40;

    public static function usernameLength(UserType $type): int
    {
        return match ($type) {
            self::Owner => 1,
            self::Agent => 2,
            self::Player => 3,
            self::SystemWallet => 4,
        };
    }

    public static function childUserType(UserType $type): UserType
    {
        return match ($type) {
            self::Owner => self::Agent,
            self::Agent => self::Player,
            self::Player, self::SystemWallet => self::Player,
        };
    }

    public static function canHaveChild(UserType $parent, UserType $child): bool
    {
        return match ($parent) {
            self::Owner => $child === self::Agent,
            self::Agent => $child === self::Player,
            self::Player, self::SystemWallet => false,
        };
    }
}

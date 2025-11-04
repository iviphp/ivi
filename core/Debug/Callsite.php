<?php

declare(strict_types=1);

namespace Ivi\Core\Debug;

final class Callsite
{
    /** @var array<string,mixed>|null */
    private static ?array $last = null;

    /** @param array{class:string,method:string,file:string,line:int} $info */
    public static function set(array $info): void
    {
        self::$last = $info;
    }

    /** @return array{class:string,method:string,file:string,line:int}|null */
    public static function get(): ?array
    {
        return self::$last;
    }

    public static function clear(): void
    {
        self::$last = null;
    }
}

<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Utils;

class Path
{
    public static function root()
    {
        return realpath(__DIR__ . '/../../../../..');
    }

    public static function app()
    {
        return self::root() . '/app';
    }

    public static function public()
    {
        return self::root() . '/public';
    }

    public static function config()
    {
        return self::root() . '/config';
    }

    public static function templates()
    {
        return self::root() . '/templates';
    }

    public static function storage()
    {
        return self::root() . '/storage';
    }

    public static function migrations()
    {
        return self::root() . '/migrations';
    }

    public static function modules()
    {
        return self::root() . '/modules';
    }
}

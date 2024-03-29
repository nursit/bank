<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitcc127513c15ee585a2f2fdba816d0796
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Stripe\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Stripe\\' => 
        array (
            0 => __DIR__ . '/..' . '/stripe/stripe-php/lib',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitcc127513c15ee585a2f2fdba816d0796::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitcc127513c15ee585a2f2fdba816d0796::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitcc127513c15ee585a2f2fdba816d0796::$classMap;

        }, null, ClassLoader::class);
    }
}

<?php

class SV_WarningImprovements_Listener
{
    const AddonNameSpace = 'SV_WarningImprovements_';

    public static function load_class($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.$class;
    }
}

<?php

namespace App\Enums;

enum Instance: string
{
    // Hellfire Citadel
    case HellfireRamparts = 'Hellfire Ramparts';
    case BloodFurnace = 'The Blood Furnace';
    case ShatteredHalls = 'The Shattered Halls';

    // Coilfang Reservoir
    case SlavePens = 'The Slave Pens';
    case Underbog = 'The Underbog';
    case Steamvault = 'The Steamvault';

    // Auchindoun
    case AuchenaiCrypts = 'Auchenai Crypts';
    case ManaTombs = 'Mana-Tombs';
    case SethekkHalls = 'Sethekk Halls';
    case ShadowLabyrinth = 'Shadow Labyrinth';

    // Caverns of Time
    case OldHillsbradFoothills = 'Old Hillsbrad Foothills';
    case BlackMorass = 'The Black Morass';

    // Tempest Keep
    case Mechanar = 'The Mechanar';
    case Botanica = 'The Botanica';
    case Arcatraz = 'The Arcatraz';

    // The Isle of Quel'Danas
    case MagistersTerrace = 'Magisters’ Terrace';

    // Battlegrounds
    case AlteracValley = 'Alterac Valley';
    case ArathiBasin = 'Arathi Basin';
    case EyeOfTheStorm = 'Eye of the Storm';
    case WarsongGulch = 'Warsong Gulch';
}

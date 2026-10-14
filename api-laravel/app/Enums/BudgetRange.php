<?php

namespace App\Enums;

enum BudgetRange: string
{
    case Under5k = 'under_5k';
    case From5kTo15k = '5k_15k';
    case From15kTo50k = '15k_50k';
    case Over50k = 'over_50k';
}

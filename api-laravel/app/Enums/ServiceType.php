<?php

namespace App\Enums;

enum ServiceType: string
{
    case WebDevelopment = 'web_development';
    case MobileApp = 'mobile_app';
    case UiUxDesign = 'ui_ux_design';
    case CloudDevops = 'cloud_devops';
    case Consulting = 'consulting';
}

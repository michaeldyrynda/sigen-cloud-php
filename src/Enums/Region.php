<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Enums;

enum Region: string
{
    case AsiaPacific = 'https://api-apac.sigencloud.com';

    case Australia = 'https://api-aus.sigencloud.com';

    case China = 'https://api-cn.sigencloud.com';

    case Europe = 'https://api-eu.sigencloud.com';

    case UnitedStates = 'https://api-us.sigencloud.com';
}

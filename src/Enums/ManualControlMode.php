<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Enums;

/**
 * The battery actions offered by the mySigen app's Instant Manual Control.
 */
enum ManualControlMode: string
{
    case Charging = '0';

    case Discharging = '1';

    case HoldBattery = '2';

    case SelfConsumption = '3';

    public function hasPowerLimit(): bool
    {
        return in_array($this, [
            self::Charging,
            self::Discharging,
        ]);
    }
}

# Sigen Cloud PHP

A [Saloon](https://docs.saloon.dev) SDK for the Sigenergy cloud API used by the mySigen app.

> [!WARNING]
> This uses the private mySigen app API, not Sigenergy's official OpenAPI. It is undocumented, logs in with your mySigen account, and may change without notice when Sigenergy updates the app.

Unlike the official OpenAPI and local Modbus control, the app API changes settings while the plant is in VPP Scheduling mode, such as when it is enrolled in Amber SmartShift.

## Installation

```bash
composer require dyrynda/sigen-cloud-php
```

Requires PHP 8.4+ with `ext-openssl`, and Saloon v4.

## Usage

```php
use Dyrynda\SigenCloud\Enums\Region;
use Dyrynda\SigenCloud\SigenCloud;

$sigen = new SigenCloud('you@example.com', 'your-mysigen-password', Region::Australia);

$station = $sigen->stations()->current();

$sigen->solar()->powerLimit($station->id);       // null when unlimited, otherwise kW
$sigen->solar()->setPowerLimit($station->id, 0); // stop PV generation
$sigen->solar()->setPowerLimit($station->id, 5); // cap PV at 5 kW
$sigen->solar()->removePowerLimit($station->id); // back to unlimited
```

### Battery manual control

Instant manual control overrides the plant's active strategy, such as a VPP, for 30 to 120 minutes. It is the same switch as Instant Manual Control in the mySigen app.

```php
use Dyrynda\SigenCloud\Enums\ManualControlMode;

$sigen->battery()->startManualControl($station->id, ManualControlMode::Charging, minutes: 30);
$sigen->battery()->startManualControl($station->id, ManualControlMode::HoldBattery, minutes: 30);
$sigen->battery()->stopManualControl($station->id); // hand back to the active strategy

$control = $sigen->battery()->manualControl($station->id);
$control->enabled; // false while the battery follows its strategy
$control->mode;    // ManualControlMode, or null when off
$control->endsAt;  // DateTimeImmutable, or null when off

$sigen->battery()->manualControlDisplay($station->id); // ['batteryPower' => 12.2, 'batterySoc' => 90.5]
```

The override ends by itself when its duration runs out. Stop it explicitly when you no longer want it, rather than waiting for it to expire.

### Other resources

Each operation takes the station ID explicitly. DC charger and V2X operations also take a charger serial number.

```php
$stationId = $station->id;

$sigen->stations()->energyFlow($stationId);
$sigen->stations()->topology($stationId);
$sigen->battery()->levels($stationId);
$sigen->battery()->peakShavingSchedule($stationId);
$sigen->grid()->exportLimit($stationId);
$sigen->operatingModes()->current($stationId);
$sigen->tariffs()->priceCost($stationId);

$sigen->dcCharger()->status($stationId, $chargerSerial);
$sigen->v2x()->dischargeInfo($stationId, $chargerSerial);
```

Changing the operating mode can take a plant out of VPP scheduling. AIO power operations can shut down the plant; do not use them without understanding the consequences. Not all write endpoints have been verified against physical equipment.

### Access tokens

The connector logs in on its first request. Access tokens last about 12 hours. To avoid logging in on every run, cache the token and pass it back in:

```php
$sigen = new SigenCloud($username, $password, Region::Australia, $cache->get('sigen:access_token'));

$cache->forever('sigen:access_token', $sigen->accessToken());
```

When the cached access token has expired or is within a minute of expiring, `accessToken()` exchanges its refresh token for a new one. If Sigen rejects the refresh token, it logs in with the password instead. Cache the token beyond `expiresAt` so the refresh token is still there when the access token runs out.

### Errors

The API often reports failures with HTTP 200 and a non-zero `code`. The connector treats those as failures, so every failed request throws `Dyrynda\SigenCloud\Exceptions\SigenCloudException`, a Saloon `RequestException`, with Sigen's message. Two subclasses let you react to specific failures:

- `SigenCloudAuthenticationException` when Sigen rejects the credentials or token (HTTP 401, or code 401 or 403)
- `SigenCloudRateLimitException` when Sigen rate limits the request (HTTP 429 or code 429)

`setPowerLimit()` throws an `InvalidArgumentException` for negative limits, and `startManualControl()` for durations outside 30 to 120 minutes, without sending a request.

## Structure

| Path | Purpose |
| --- | --- |
| `src/SigenCloud.php` | The connector: base URL, authentication, token handling and error detection |
| `src/Resources/` | Grouped station, solar, battery, grid, operating mode, tariff, charger, V2X and AIO operations |
| `src/Requests/` | One Saloon request per endpoint |
| `src/Data/` | Response DTOs and settings, including station topology and battery schedules |
| `src/Enums/` | `Region` base URLs and battery `ManualControlMode`s |
| `src/Support/PasswordEncrypter.php` | Encrypts the password the way the mySigen app does before login |

To add an endpoint, add a request in `src/Requests/`, then expose it from a resource. Add a new resource and an accessor on `SigenCloud` when it doesn't fit an existing one.

## Testing

```bash
composer test
composer lint
```

Tests use Pest and Saloon's `MockClient`. `tests/Pest.php` calls `Config::preventStrayRequests()`, so any request a test hasn't mocked throws instead of reaching Sigenergy.

## Credits

The API mapping is based on [solidfox/sigenergy-cloud](https://github.com/solidfox/sigenergy-cloud).

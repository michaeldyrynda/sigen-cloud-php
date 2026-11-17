<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Resources;

use Dyrynda\SigenCloud\Exceptions\SigenCloudRateLimitException;
use Dyrynda\SigenCloud\Requests\GetAioOnOffStateRequest;
use Dyrynda\SigenCloud\Requests\GetAioPowerStatusRequest;
use Dyrynda\SigenCloud\Requests\UpdateAioPowerRequest;
use RuntimeException;
use Saloon\Http\BaseResource;
use Throwable;

final class AioResource extends BaseResource
{
    public function onOffState(int $stationId, ?string $serial = null): bool
    {
        $serial = $this->serial($stationId, $serial);

        return (bool) $this->connector->send(new GetAioOnOffStateRequest($stationId, $serial))->json('data');
    }

    public function powerOn(int $stationId, ?string $serial = null): bool
    {
        $serial = $this->serial($stationId, $serial);
        $data = $this->connector->send(new GetAioPowerStatusRequest($stationId, $serial))->json('data');

        if (is_array($data)) {
            foreach (['powerOn', 'isPowerOn', 'status'] as $key) {
                if (isset($data[$key])) {
                    return is_string($data[$key]) ? $data[$key] !== '' : (bool) $data[$key];
                }
            }
        }

        return (bool) $data;
    }

    /**
     * Restart only a station whose home status is running and matches the requested station ID.
     * Power-on recovery is best effort; process termination cannot be recovered here.
     * This synchronous method cannot shield recovery from cancellation as Python's asyncio implementation does.
     *
     * @return array<string, mixed>
     */
    public function restart(int $stationId, ?string $serial = null, float $timeoutSeconds = 180, float $pollSeconds = 2, bool $waitEvdcOffline = true, float $evdcOfflineWaitSeconds = 120, float $offDwellSeconds = 0, ?bool $waitEvdcStatusChange = null, ?float $evdcStatusWaitSeconds = null): array
    {
        $startedAt = hrtime(true) / 1e9;
        $waitEvdcOffline = $waitEvdcStatusChange ?? $waitEvdcOffline;
        $evdcOfflineWaitSeconds = $evdcStatusWaitSeconds ?? $evdcOfflineWaitSeconds;
        $serial = $this->serial($stationId, $serial);
        $home = (new StationResource($this->connector))->current();
        if ($home->id !== $stationId) {
            throw new RuntimeException('Station home does not match the requested station.');
        }
        if (! in_array($home->status, [0, 1, 2], true)) {
            throw new RuntimeException('Cannot restart a station that is not known to be running.');
        }

        $beforeHome = $home;
        $beforePower = $this->powerOn($stationId, $serial);
        try {
            $evdcBefore = (new StationResource($this->connector))->topologyEvdcStatus($stationId);
        } catch (Throwable) {
            $evdcBefore = ['offline' => null, 'error' => 'topology_read_failed'];
        }

        $poweredOff = false;
        $evdcWaitAborted = null;
        $evdcAfterOff = null;
        $evdcOfflineSeen = false;
        $offlineAt = null;
        $evdcOfflineAt = null;
        $onlineAt = null;
        $offCommandAt = hrtime(true) / 1e9;
        $cycleFailure = null;
        $recoveryFailure = null;
        try {
            $this->togglePower($stationId, $serial);
            $deadline = hrtime(true) / 1e9 + $timeoutSeconds;
            do {
                if ($pollSeconds > 0) {
                    usleep((int) ($pollSeconds * 1_000_000));
                }
                $home = (new StationResource($this->connector))->current();
                if ($home->id !== $stationId) {
                    throw new RuntimeException('Station home does not match the requested station.');
                }
                if (in_array($home->status, [3, 5, 6], true)) {
                    $poweredOff = true;
                    $offlineAt = hrtime(true) / 1e9;
                    break;
                }
            } while (hrtime(true) / 1e9 < $deadline);
            if ($poweredOff && $waitEvdcOffline && $evdcOfflineWaitSeconds > 0) {
                $deadline = hrtime(true) / 1e9 + $evdcOfflineWaitSeconds;
                do {
                    if ($pollSeconds > 0) {
                        usleep((int) ($pollSeconds * 1_000_000));
                    }
                    try {
                        $evdcAfterOff = (new StationResource($this->connector))->topologyEvdcStatus($stationId);
                    } catch (SigenCloudRateLimitException $exception) {
                        $evdcAfterOff = ['offline' => null, 'error' => 'rate_limited', 'detail' => $exception->getMessage()];
                        $evdcWaitAborted = 'topology_rate_limited';
                        break;
                    } catch (Throwable $exception) {
                        $evdcAfterOff = ['offline' => null, 'error' => $exception->getMessage()];

                        continue;
                    }
                    if ($evdcAfterOff['offline'] === true) {
                        $evdcOfflineSeen = true;
                        $evdcOfflineAt = hrtime(true) / 1e9;
                        break;
                    }
                } while (hrtime(true) / 1e9 < $deadline);
            }
            if ($poweredOff && $offDwellSeconds > 0) {
                usleep((int) ($offDwellSeconds * 1_000_000));
            }
        } catch (Throwable $exception) {
            $cycleFailure = $exception;
        } finally {
            $onCommandAt = hrtime(true) / 1e9;
            try {
                $this->connector->send(new UpdateAioPowerRequest($stationId, $serial, true));
            } catch (Throwable $firstRecoveryFailure) {
                try {
                    $this->connector->send(new UpdateAioPowerRequest($stationId, $serial, true));
                } catch (Throwable $exception) {
                    $recoveryFailure = new RuntimeException($firstRecoveryFailure->getMessage().'; '.$exception->getMessage(), previous: $firstRecoveryFailure);
                }
            }
        }

        try {
            $deadline = hrtime(true) / 1e9 + $timeoutSeconds;
            $powerOn = false;
            $verificationFailure = null;
            do {
                if ($pollSeconds > 0) {
                    usleep((int) ($pollSeconds * 1_000_000));
                }
                try {
                    $home = (new StationResource($this->connector))->current();
                    if ($home->id !== $stationId) {
                        throw new RuntimeException('Station home does not match the requested station.');
                    }
                    $powerOn = $this->powerOn($stationId, $serial);
                    $verificationFailure = null;
                } catch (Throwable $exception) {
                    $verificationFailure = $exception;
                    if (hrtime(true) / 1e9 >= $deadline) {
                        throw $exception;
                    }

                    continue;
                }
                if (in_array($home->status, [0, 1, 2], true) && $powerOn) {
                    $onlineAt = hrtime(true) / 1e9;
                    break;
                }
            } while (hrtime(true) / 1e9 < $deadline);
            if ($verificationFailure !== null) {
                throw $verificationFailure;
            }
        } catch (Throwable $exception) {
            try {
                $this->connector->send(new UpdateAioPowerRequest($stationId, $serial, true));
            } catch (Throwable $retryFailure) {
                $recoveryFailure = $retryFailure;
            }
            $retryVerificationFailure = null;
            $retryDeadline = hrtime(true) / 1e9 + max(0, $timeoutSeconds);
            do {
                if ($pollSeconds > 0 && $timeoutSeconds > 0) {
                    usleep((int) (min($pollSeconds, max(0, $retryDeadline - hrtime(true) / 1e9)) * 1_000_000));
                }
                try {
                    $retryHome = (new StationResource($this->connector))->current();
                    if ($retryHome->id !== $stationId) {
                        throw new RuntimeException('Station home does not match the requested station.');
                    }
                    $retryPowerOn = $this->powerOn($stationId, $serial);
                    $retryVerificationFailure = null;
                    if (in_array($retryHome->status, [0, 1, 2], true) && $retryPowerOn) {
                        break;
                    }
                } catch (Throwable $retryException) {
                    $retryVerificationFailure = $retryException;
                }
            } while (hrtime(true) / 1e9 < $retryDeadline);
            if ($cycleFailure !== null || $recoveryFailure !== null) {
                $message = $cycleFailure === null ? '' : 'AIO restart failed: '.$cycleFailure->getMessage().'; ';
                $message .= $recoveryFailure === null ? '' : 'power-on recovery failed: '.$recoveryFailure->getMessage().'; ';
                $message .= $retryVerificationFailure === null ? '' : 'retry verification failed: '.$retryVerificationFailure->getMessage().'; ';
                throw new RuntimeException($message.'recovery verification failed: '.$exception->getMessage(), previous: $cycleFailure ?? $recoveryFailure);
            }
            throw $exception;
        }

        $powerOnForced = false;
        if ($recoveryFailure === null && (! in_array($home->status, [0, 1, 2], true) || ! $powerOn)) {
            $powerOnForced = true;
            try {
                $this->connector->send(new UpdateAioPowerRequest($stationId, $serial, true));
            } catch (Throwable $exception) {
                $recoveryFailure = $exception;
            }
            $deadline = hrtime(true) / 1e9 + $timeoutSeconds;
            $forcedVerificationFailure = null;
            do {
                if ($pollSeconds > 0) {
                    usleep((int) ($pollSeconds * 1_000_000));
                }
                try {
                    $home = (new StationResource($this->connector))->current();
                    if ($home->id !== $stationId) {
                        throw new RuntimeException('Station home does not match the requested station.');
                    }
                    $powerOn = $this->powerOn($stationId, $serial);
                    $forcedVerificationFailure = null;
                } catch (Throwable $exception) {
                    $forcedVerificationFailure = $exception;

                    continue;
                }
                if (in_array($home->status, [0, 1, 2], true) && $powerOn) {
                    $onlineAt = hrtime(true) / 1e9;
                    break;
                }
            } while (hrtime(true) / 1e9 < $deadline);
            if ($forcedVerificationFailure !== null) {
                $message = $cycleFailure === null ? '' : 'AIO restart failed: '.$cycleFailure->getMessage().'; ';
                $message .= $recoveryFailure === null ? '' : 'power-on recovery failed: '.$recoveryFailure->getMessage().'; ';
                throw new RuntimeException($message.'forced recovery verification failed: '.$forcedVerificationFailure->getMessage(), previous: $cycleFailure ?? $recoveryFailure ?? $forcedVerificationFailure);
            }
        }

        try {
            $evdcFinal = (new StationResource($this->connector))->topologyEvdcStatus($stationId);
        } catch (Throwable) {
            $evdcFinal = ['offline' => null, 'error' => 'topology_read_failed'];
        }

        if ($recoveryFailure !== null) {
            $message = 'AIO power-on recovery failed: '.$recoveryFailure->getMessage();
            if ($cycleFailure !== null) {
                $message = 'AIO restart failed: '.$cycleFailure->getMessage().'; '.$message;
            }

            throw new RuntimeException($message, previous: $cycleFailure ?? $recoveryFailure);
        }
        if ($cycleFailure !== null) {
            throw $cycleFailure;
        }

        return [
            'sn_code' => $serial,
            'power_on_before' => $beforePower,
            'home_status_before' => $beforeHome->status,
            'home_status_desc_before' => $beforeHome->statusDesc,
            'powered_off' => $poweredOff,
            'powered_on' => in_array($home->status, [0, 1, 2], true) && $powerOn,
            'home_status_after' => $home->status,
            'home_status_desc_after' => $home->statusDesc,
            'power_on_after' => $powerOn,
            'power_on_forced' => $powerOnForced,
            'evdc_wait_aborted' => $evdcWaitAborted,
            'evdc_offline_seen' => $evdcOfflineSeen,
            'evdc_topology_after_off' => $evdcAfterOff,
            'evdc_topology_before' => $evdcBefore,
            'evdc_topology_after' => $evdcFinal,
            'off_dwell_s' => $offDwellSeconds,
            'poll_s' => $pollSeconds,
            'wait_evdc_offline' => $waitEvdcOffline,
            'evdc_offline_wait_s' => $evdcOfflineWaitSeconds,
            't_offline_s' => $offlineAt === null ? null : round($offlineAt - $offCommandAt, 2),
            't_evdc_offline_after_plant_off_s' => $evdcOfflineAt === null ? null : round($evdcOfflineAt - $offlineAt, 2),
            't_online_after_on_s' => $onlineAt === null ? null : round($onlineAt - $onCommandAt, 2),
            'full_cycle_s' => $onlineAt === null ? null : round($onlineAt - $offCommandAt, 2),
            'elapsed_s' => round(hrtime(true) / 1e9 - $startedAt, 2),
        ];
    }

    public function setPower(int $stationId, bool $powerOn, ?string $serial = null, float $timeoutSeconds = 120, float $pollSeconds = 2): bool
    {
        $serial = $this->serial($stationId, $serial);
        $stations = new StationResource($this->connector);
        $home = $stations->current();
        if ($home->id !== $stationId) {
            throw new RuntimeException('Station home does not match the requested station.');
        }

        if (in_array($home->status, $powerOn ? [0, 1, 2] : [3, 5, 6], true)) {
            return true;
        }
        if (! $powerOn && ! in_array($home->status, [0, 1, 2], true)) {
            throw new RuntimeException('Cannot toggle off a station that is not known to be running.');
        }

        $this->connector->send(new UpdateAioPowerRequest($stationId, $serial, $powerOn));
        $deadline = hrtime(true) / 1e9 + $timeoutSeconds;
        do {
            if ($pollSeconds > 0) {
                usleep((int) ($pollSeconds * 1_000_000));
            }
            $home = $stations->current();
            if ($home->id !== $stationId) {
                throw new RuntimeException('Station home does not match the requested station.');
            }
            if (in_array($home->status, $powerOn ? [0, 1, 2] : [3, 5, 6], true)) {
                return true;
            }
        } while (hrtime(true) / 1e9 < $deadline);

        return false;
    }

    /** @return array<string, mixed> */
    public function togglePower(int $stationId, ?string $serial = null): array
    {
        $serial = $this->serial($stationId, $serial);

        /** @var array<string, mixed> */
        return $this->connector->send(new UpdateAioPowerRequest($stationId, $serial))->json();
    }

    private function serial(int $stationId, ?string $serial): string
    {
        return $serial ?: ((new StationResource($this->connector))->aioSerials($stationId)[0] ?? throw new RuntimeException('No AIO serial numbers found for this station.'));
    }
}

<?php

function app_parse_time_input(array $source, string $fieldPrefix, string $mode = '24h'): ?string
{
    if ($mode === 'ampm') {
        $hour = trim((string) ($source[$fieldPrefix . '_hour_12'] ?? ''));
        $minute = trim((string) ($source[$fieldPrefix . '_minute_12'] ?? ''));
        $meridiem = strtoupper(trim((string) ($source[$fieldPrefix . '_meridiem'] ?? 'AM')));
        if (!preg_match('/^\d{2}$/', $hour) || !preg_match('/^\d{2}$/', $minute)) {
            return null;
        }

        $hourInt = (int) $hour;
        $minuteInt = (int) $minute;
        if ($hourInt < 1 || $hourInt > 12 || $minuteInt < 0 || $minuteInt > 59) {
            return null;
        }

        if (!in_array($meridiem, ['AM', 'PM'], true)) {
            return null;
        }

        if ($meridiem === 'AM') {
            $hourInt = $hourInt === 12 ? 0 : $hourInt;
        } else {
            $hourInt = $hourInt === 12 ? 12 : $hourInt + 12;
        }

        return sprintf('%02d:%02d', $hourInt, $minuteInt);
    }

    $hour = trim((string) ($source[$fieldPrefix . '_hour'] ?? ''));
    $minute = trim((string) ($source[$fieldPrefix . '_minute'] ?? ''));
    if (!preg_match('/^\d{2}$/', $hour) || !preg_match('/^\d{2}$/', $minute)) {
        return null;
    }

    $hourInt = (int) $hour;
    $minuteInt = (int) $minute;
    if ($hourInt < 0 || $hourInt > 23 || $minuteInt < 0 || $minuteInt > 59) {
        return null;
    }

    return sprintf('%02d:%02d', $hourInt, $minuteInt);
}

function app_time_to_parts(?string $time): array
{
    if (!$time) {
        return ['hour' => '08', 'minute' => '30'];
    }

    [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

    return [
        'hour' => sprintf('%02d', $hour),
        'minute' => sprintf('%02d', $minute),
    ];
}

function app_time_to_ampm_parts(?string $time): array
{
    $parts = app_time_to_parts($time);
    $hour = (int) $parts['hour'];
    $meridiem = $hour >= 12 ? 'PM' : 'AM';
    $displayHour = $hour % 12;
    if ($displayHour === 0) {
        $displayHour = 12;
    }

    return [
        'hour' => sprintf('%02d', $displayHour),
        'minute' => $parts['minute'],
        'meridiem' => $meridiem,
    ];
}

function app_build_time_log_range(string $workDate, string $timeInValue, string $timeOutValue): ?array
{
    if ($workDate === '' || $timeInValue === '' || $timeOutValue === '') {
        return null;
    }

    $fullTimeIn = $workDate . ' ' . $timeInValue . ':00';
    $fullTimeOut = $workDate . ' ' . $timeOutValue . ':00';
    $tsIn = strtotime($fullTimeIn);
    $tsOut = strtotime($fullTimeOut);

    if ($tsIn === false || $tsOut === false) {
        return null;
    }

    $crossDay = false;
    if ($tsOut < $tsIn) {
        $tsOut += 86400;
        $fullTimeOut = date('Y-m-d H:i:s', $tsOut);
        $crossDay = true;
    }

    return [
        'time_in' => date('Y-m-d H:i:s', $tsIn),
        'time_out' => $fullTimeOut,
        'hours' => number_format(($tsOut - $tsIn) / 3600, 2, '.', ''),
        'cross_day' => $crossDay,
    ];
}

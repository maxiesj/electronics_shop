<?php

class ShiftValidator {
    // Centralized configurations for different shift types
   private static $shiftProfiles = [ 
    'regular' => [
        'min_start'    => '07:30',
        'max_start'    => '09:00',
        'min_end'      => '09:00', // Adjusted layout bounds to allow a 1.5 hour checkout window
        'max_end'      => '19:00',
        'target_hours' => 1.5,     // FIX: Change from 9.0 to 1.5
        'grace_minutes'=> 30
    ],
    'night' => [
        'min_start'    => '20:00',
        'max_start'    => '22:00',
        'min_end'      => '21:30', // Adjusted layout bounds to match target parameters
        'max_end'      => '07:00',
        'target_hours' => 1.5,     // FIX: Change from 9.0 to 1.5
        'grace_minutes'=> 30
    ],
    'short_coverage' => [
        'min_start'    => '00:00',
        'max_start'    => '23:59',
        'min_end'      => '00:00',
        'max_end'      => '23:59',
        'target_hours' => 0.75,    // FIX: Match your expected short layout hours threshold
        'grace_minutes'=> 270  
    ]
];


    /**
     * Validates if the user is logging in during the correct window for their specific shift type.
     * Incorporates a third flag to handle immediate Super Admin bypasses.
     * Now returns a 'shift_status' key to prevent empty database rows.
     */
    public static function validateClockIn(string $clockInTimeStr, string $shiftType = 'regular', bool $isAdmin = false): array {
        // 1. Direct short-circuit rule for Super Admin sessions
        if ($isAdmin) {
            return [
                'status'       => true, 
                'shift_status' => 'Admin Override',
                'message'      => "Authorized by Super Admin. Clock-in window validation bypassed."
            ];
        }

        if (!isset(self::$shiftProfiles[$shiftType])) {
            return [
                'status'       => false, 
                'shift_status' => 'Invalid',
                'message'      => "Invalid shift profile designated."
            ];
        }

        $profile   = self::$shiftProfiles[$shiftType];
        $clockIn   = strtotime($clockInTimeStr);
        $timeOfDay = date('H:i', $clockIn);

        // Check if completely outside allowed bounds
        if ($timeOfDay < $profile['min_start'] || $timeOfDay > $profile['max_start']) {
            return [
                'status'       => false,
                'shift_status' => 'Rejected',
                'message'      => "Clock-in rejected. For a $shiftType shift, start must be between {$profile['min_start']} and {$profile['max_start']}."
            ];
        }

        // Determine if they are 'On Time' or 'Late' based on grace_minutes configuration
        // Example: For regular shift, base start is 07:30. Allowed grace up to 08:00 (30 mins).
        $baseStartTime      = strtotime(date('Y-m-d ', $clockIn) . $profile['min_start']);
        $gracePeriodSeconds = $profile['grace_minutes'] * 60;
        
        if ($clockIn > ($baseStartTime + $gracePeriodSeconds)) {
            $calculatedStatus = 'Late';
        } else {
            $calculatedStatus = 'On Time';
        }

        return [
            'status'       => true, 
            'shift_status' => $calculatedStatus,
            'message'      => "Clock-in successful for $shiftType shift."
        ];
    }

    /**
     * Validates total hours worked and log-out time limits against the designated shift profile.
     * Incorporates a fourth flag to handle immediate Super Admin bypasses.
     */
    public static function validateClockOut(string $clockInTimeStr, string $clockOutTimeStr, string $shiftType = 'regular', bool $isAdmin = false): array {
        // 1. Direct short-circuit rule for Super Admin sessions
        if ($isAdmin) {
            return ['status' => true, 'message' => "Authorized by Super Admin. Shift duration constraints bypassed."];
        }

        if (!isset(self::$shiftProfiles[$shiftType])) {
            return ['status' => false, 'message' => "Invalid shift profile designated."];
        }

        $profile  = self::$shiftProfiles[$shiftType];
        $clockIn  = strtotime($clockInTimeStr);
        $clockOut = strtotime($clockOutTimeStr);
        
        // Handle overnight cross-midnight shifts cleanly
        if ($clockOut < $clockIn) {
            $clockOut += 86400; // Adds 24 hours in seconds
        }

        // 2. Calculate duration and validate against target boundaries
        $secondsWorked   = $clockOut - $clockIn;
        $hoursWorked     = $secondsWorked / 3600;
        $graceFraction   = $profile['grace_minutes'] / 60;
        $minAllowedHours = $profile['target_hours'] - $graceFraction;
        $maxAllowedHours = $profile['target_hours'] + $graceFraction;

        if ($hoursWorked < $minAllowedHours || $hoursWorked > $maxAllowedHours) {
            $formattedWorked = round($hoursWorked, 2);
            return [
                'status'  => false,
                'message' => "Logout rejected. This $shiftType shift requires {$profile['target_hours']} hours (with a {$profile['grace_minutes']}-min grace period). You tracked $formattedWorked hours."
            ];
        }

        // 3. Build the logout window from the clock-in date. This allows an
        // overnight window (for example, 21:30-07:00) to cross midnight.
        $shiftDate = date('Y-m-d', $clockIn);
        $minEnd = strtotime($shiftDate . ' ' . $profile['min_end']);
        $maxEnd = strtotime($shiftDate . ' ' . $profile['max_end']);
        if ($maxEnd < $minEnd) {
            $maxEnd += 86400;
        }

        if ($clockOut < $minEnd || $clockOut > $maxEnd) {
            return [
                'status'  => false,
                'message' => "Logout rejected. For a $shiftType shift, you must log out between {$profile['min_end']} and {$profile['max_end']}."
            ];
        }

        return ['status' => true, 'message' => "Clock-out successful. $shiftType shift logged."];
    }
}

/**
 * BRIDGE FUNCTION FOR DASHBOARD JAVASCRIPT COUPLING
 * Maps procedural code expectations smoothly to the static class design pattern.
 */
function is_regular_clockin_time() {
    // Check if the current actual server timestamp passes standard regular rules
    $check = ShiftValidator::validateClockIn(date('Y-m-d H:i:s'), 'regular', false);
    return $check['status'];
}

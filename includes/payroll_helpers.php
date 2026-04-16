<?php

/**
 * Calculate total hours worked for a staff member during a billing period
 * 
 * This function queries staff_attendance records with complete punch_in and punch_out times,
 * calculates work duration as (punch_out - punch_in) for each record, subtracts break durations
 * from attendance_breaks table, and converts total seconds to hours with 4 decimal precision.
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID of the staff member
 * @param string $startDate Start date of billing period (YYYY-MM-DD format)
 * @param string $endDate End date of billing period (YYYY-MM-DD format)
 * @return float Total hours worked with 4 decimal precision
 * 
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 8.2, 8.3, 8.4
 */
function calculate_hours_worked($pdo, $userId, $startDate, $endDate) {
    // Query attendance records with complete punch times
    // Requirement 3.1: Query staff_attendance records where punch_in and punch_out are not NULL
    $stmt = $pdo->prepare("
        SELECT id, punch_in, punch_out 
        FROM staff_attendance 
        WHERE user_id = ? 
          AND date BETWEEN ? AND ? 
          AND punch_in IS NOT NULL 
          AND punch_out IS NOT NULL
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $attendanceRecords = $stmt->fetchAll();
    
    $totalSeconds = 0;
    
    foreach ($attendanceRecords as $record) {
        // Requirement 3.2: Calculate work duration as (punch_out - punch_in)
        $workSeconds = strtotime($record['punch_out']) - strtotime($record['punch_in']);
        
        // Requirement 3.2: Subtract break durations from attendance_breaks table
        // Requirement 8.3: Exclude breaks with NULL break_start or break_end
        $breakStmt = $pdo->prepare("
            SELECT break_start, break_end 
            FROM attendance_breaks 
            WHERE attendance_id = ? 
              AND break_start IS NOT NULL 
              AND break_end IS NOT NULL
        ");
        $breakStmt->execute([$record['id']]);
        $breaks = $breakStmt->fetchAll();
        
        // Requirement 3.6: Handle records with no breaks correctly
        foreach ($breaks as $break) {
            $breakSeconds = strtotime($break['break_end']) - strtotime($break['break_start']);
            $workSeconds -= $breakSeconds;
        }
        
        // Requirement 8.4: Handle negative durations by logging error and treating as 0 hours
        if ($workSeconds < 0) {
            app_log('ERROR', "Negative work duration for attendance#{$record['id']}, user_id={$userId}, date range {$startDate} to {$endDate}");
            $workSeconds = 0;
        }
        
        $totalSeconds += $workSeconds;
    }
    
    // Requirement 3.3: Convert total seconds to hours by dividing by 3600
    // Requirement 3.5: Maintain precision to at least 4 decimal places
    $totalHours = round($totalSeconds / 3600, 4);
    
    return $totalHours;
}

/**
 * Calculate hourly payment with minimum threshold enforcement
 * 
 * This function calculates payment for hourly staff based on total hours worked
 * and hourly rate. If total hours are below the minimum threshold (default 1.0 hour),
 * the function returns 0.00. Otherwise, it calculates basic_salary as (total_hours × hourly_rate)
 * and rounds the result to 2 decimal places.
 * 
 * @param float $total_hours Total hours worked during billing period
 * @param float $hourly_rate Hourly payment rate
 * @param float $minimum_threshold Minimum hours required for payment (default 1.0)
 * @return float Payment amount rounded to 2 decimal places
 * 
 * Requirements: 4.2, 4.3, 4.5
 */
function calculate_hourly_payment($total_hours, $hourly_rate, $minimum_threshold = 1.0) {
    // Requirement 4.3: Return 0.00 if total_hours < minimum_threshold
    if ($total_hours < $minimum_threshold) {
        return 0.00;
    }
    
    // Requirement 4.2: Calculate basic_salary as (total_hours × hourly_rate)
    $basic_salary = $total_hours * $hourly_rate;
    
    // Requirement 4.5: Round result to 2 decimal places
    return round($basic_salary, 2);
}

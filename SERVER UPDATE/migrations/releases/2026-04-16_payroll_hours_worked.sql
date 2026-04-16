-- Migration: Add hours_worked column to payroll table for hourly staff
-- Date: 2026-04-16
-- Purpose: Store hours worked for hourly staff so it can be displayed in payroll list

ALTER TABLE payroll 
ADD COLUMN IF NOT EXISTS hours_worked DECIMAL(10,4) DEFAULT NULL COMMENT 'Hours worked for hourly staff (NULL for fixed salary staff)' 
AFTER basic_salary;

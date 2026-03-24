-- Booking-level discount on reservations
-- Idempotent: safe to run multiple times
-- Apply manually on production DB

ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS booking_discount_type  VARCHAR(10)    NULL        COMMENT 'percent or amount',
    ADD COLUMN IF NOT EXISTS booking_discount_value DECIMAL(10,2)  NOT NULL DEFAULT 0.00 COMMENT 'raw input value';

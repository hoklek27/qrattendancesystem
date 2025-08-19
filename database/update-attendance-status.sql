-- Update attendance table to support new status options
ALTER TABLE attendance MODIFY COLUMN status ENUM('hadir', 'sakit', 'izin', 'alfa') DEFAULT 'hadir';

-- Update existing 'alfa' records to maintain data integrity
UPDATE attendance SET status = 'alfa' WHERE status IS NULL;

-- Add index for better performance on status queries
ALTER TABLE attendance ADD INDEX idx_status (status);
ALTER TABLE attendance ADD INDEX idx_session_status (qr_session_id, status);

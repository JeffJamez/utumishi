-- Migration: Add latitude and longitude columns to cases table
-- Created: 2026-02-19
-- Purpose: Support GPS coordinates for crime hotspot mapping

-- Add latitude column
ALTER TABLE cases 
ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL COMMENT 'GPS Latitude from Google Places' 
AFTER reporter_local_area;

-- Add longitude column  
ALTER TABLE cases 
ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL COMMENT 'GPS Longitude from Google Places'
AFTER latitude;

-- Add indexes for faster geographic queries
CREATE INDEX IF NOT EXISTS idx_cases_coordinates ON cases(latitude, longitude);
CREATE INDEX IF NOT EXISTS idx_cases_incident_location ON cases(incident_location_county, incident_location_constituency);

-- Verify columns were added
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'utumishi' 
AND TABLE_NAME = 'cases' 
AND COLUMN_NAME IN ('latitude', 'longitude');

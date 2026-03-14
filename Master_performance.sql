USE gfotio01;
GO

/* ==========================================================
   1. TRIGGERS (AUTOMATED LOGIC)
   ========================================================== */
   
-- [AUDIT] Automatically records changes to Ride Status
-- This prevents admin/users from secretly changing status without a trace.
CREATE OR ALTER TRIGGER trg_RideStatusAudit
ON Ride
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    IF UPDATE(Status)
    BEGIN
        INSERT INTO Ride_Audit_Log (RideID, OldStatus, NewStatus)
        SELECT i.Ride_id, d.Status, i.Status
        FROM inserted i JOIN deleted d ON i.Ride_id = d.Ride_id;
    END
END;
GO

-- Check if the trigger exists and drop it if so
IF OBJECT_ID('trg_CheckRating', 'TR') IS NOT NULL
    DROP TRIGGER trg_CheckRating;
GO

-- [VALIDATION] Ensures Data Integrity for Reviews
CREATE TRIGGER trg_CheckRating
ON Review
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;
    -- Semantic Constraint: Rating must be 1-5
    IF EXISTS (SELECT 1 FROM inserted WHERE Rating < 1 OR Rating > 5)
    BEGIN
        RAISERROR ('Rating must be between 1 and 5.', 16, 1);
        ROLLBACK TRANSACTION; -- Cancels the insert if invalid
        RETURN;
    END
END;
GO

/* ==========================================================
   2. INDEXES (PERFORMANCE OPTIMIZATION)
   ========================================================== */
   
-- [SPEED] High frequency lookup for Login/Registration
CREATE NONCLUSTERED INDEX IX_User_Email ON [User](Email);

-- [SPEED] Crucial for Driver Dashboard
-- Drivers constantly poll for "My Assigned Rides", so we index by DriverID + Status
CREATE NONCLUSTERED INDEX IX_RideSegment_DriverStatus 
ON RideSegment(DriverID, Status) 
INCLUDE (SequenceOrder, EstimatedCost);

-- [SPEED] Crucial for finding Available Rides in Zones
-- The system queries "Show me requested rides in Zone X" very often.
CREATE NONCLUSTERED INDEX IX_RideSegment_Geofence
ON RideSegment(GeofenceID, Status)
WHERE Status = 'Requested';

-- General Lookup
CREATE NONCLUSTERED INDEX IX_RideSegment_RideID
ON RideSegment(RideID, SequenceOrder);

-- [REPORTING] Speeds up "My Ride History" for passengers
CREATE NONCLUSTERED INDEX IX_Ride_History 
ON Ride(Passenger_UserID, Status, End_time DESC);

-- [FINANCE] Speeds up Admin Payment Logs
CREATE NONCLUSTERED INDEX IX_Payment_Date 
ON Payment(Date DESC);
GO
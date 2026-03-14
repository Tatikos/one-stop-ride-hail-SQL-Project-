USE gfotio01;
GO

/* ==========================================================
   1. USER & DRIVER REGISTRATION
   ========================================================== */
   
-- [SECURITY] SQL INJECTION PREVENTION:
-- By using Stored Procedures with declared parameters (@Firstname, @Lastname, etc.),
-- the database treats input as literal values, not executable code. 
-- This defeats ' OR 1=1 -- style attacks.
CREATE OR ALTER PROCEDURE sp_RegisterUser
    @Firstname NVARCHAR(50), @Lastname NVARCHAR(50), @Email NVARCHAR(100), 
    @PasswordHash NVARCHAR(255), @DOB DATE, @Gender NVARCHAR(20), @Address NVARCHAR(255)
AS
BEGIN
    SET NOCOUNT ON;
    INSERT INTO [User] (Firstname, Lastname, Email, PasswordHash, Dateofbirth, Gender, Address)
    VALUES (@Firstname, @Lastname, @Email, @PasswordHash, @DOB, @Gender, @Address);
    
    -- [LOGIC] Automatically assign 'Passenger' role upon registration
    DECLARE @ID INT = SCOPE_IDENTITY();
    INSERT INTO User_Role VALUES (@ID, (SELECT Role_id FROM Role WHERE Name='Passenger'));
END;
GO

CREATE OR ALTER PROCEDURE sp_Driver_RegisterProfile
    @UserID INT, @IsAutonomous BIT
AS
BEGIN
    SET NOCOUNT ON;
    -- [VALIDATION] Check if profile exists to prevent duplicates
    IF NOT EXISTS (SELECT 1 FROM Driver_Profile WHERE UserID = @UserID)
    BEGIN
        INSERT INTO Driver_Profile (UserID, Status, IsAutonomous, IsAvailable, WalletBalance) 
        VALUES (@UserID, 'Pending', @IsAutonomous, 1, 0.00);
        
        -- [RBAC] Assign Driver Role
        DECLARE @RID INT = (SELECT Role_id FROM Role WHERE Name='Driver');
        IF NOT EXISTS (SELECT 1 FROM User_Role WHERE UserID=@UserID AND Role_id=@RID)
            INSERT INTO User_Role VALUES (@UserID, @RID);
    END
    SELECT DriverID FROM Driver_Profile WHERE UserID = @UserID;
END;
GO

CREATE OR ALTER PROCEDURE sp_RegisterVehicle_Full_Geo
    @DriverID INT, @Plate NVARCHAR(20), @Model NVARCHAR(50), @Type NVARCHAR(50),
    @Seats INT, @Vol DECIMAL(10,2), @Weight DECIMAL(10,2), @ServiceTypeID INT,
    @GeofenceID INT
AS
BEGIN
    SET NOCOUNT ON;
    -- [LOGIC] Vehicle is tied to a specific Home Geofence (Zone)
    INSERT INTO Vehicle (Owner_DriverID, Home_GeofenceID, RegistrationPlate, Model, Type, PassengerSeats, Service_type_id, Status, SafetyStatus)
    VALUES (@DriverID, @GeofenceID, @Plate, @Model, @Type, @Seats, @ServiceTypeID, 'Pending', 'Pending');
    SELECT SCOPE_IDENTITY() AS VehicleID;
END;
GO

CREATE OR ALTER PROCEDURE sp_Upload_Strict_Doc 
    @EntityID INT, @Category NVARCHAR(20), @Type NVARCHAR(50), 
    @DocNum NVARCHAR(50), @Expiry DATE, @Path NVARCHAR(255) 
AS
BEGIN 
    -- [LOGIC] Unified procedure for uploading both Driver and Vehicle docs
    IF @Category='Driver' 
        INSERT INTO Driver_Documents (DriverID, DocumentType, DocumentNumber, ExpiryDate, FilePath) 
        VALUES (@EntityID, @Type, @DocNum, @Expiry, @Path); 
    ELSE 
        INSERT INTO Vehicle_Documents (Vehicle_ID, DocumentType, DocumentNumber, ExpiryDate, FilePath) 
        VALUES (@EntityID, @Type, @DocNum, @Expiry, @Path); 
END;
GO

/* ==========================================================
   2. SEARCH: FIND NEARBY DRIVERS (UPDATED)
   ========================================================== */
CREATE OR ALTER PROCEDURE sp_GetNearbyDrivers
    @Lat DECIMAL(9,6),
    @Lon DECIMAL(9,6),
    @IsAutonomous BIT
AS
BEGIN
    SET NOCOUNT ON;
    
    -- [GEOSPATIAL] 1. Find the Zone based on Lat/Lon bounding box
    DECLARE @ZoneID INT;
    SELECT @ZoneID = GeofenceID FROM Geofence 
    WHERE @Lat <= TopLeft_Lat AND @Lat >= BottomRight_Lat 
      AND @Lon >= TopLeft_Lon AND @Lon <= BottomRight_Lon;

    -- [LOGIC] 2. Return Available Drivers where their VEHICLE is in that Zone
    -- Filtering by Vehicle.Home_GeofenceID ensures drivers operate in their permitted area
    SELECT d.DriverID, u.Firstname, u.Lastname, v.Model, v.RegistrationPlate, 
           st.Name as ServiceType, d.IsAutonomous
    FROM Driver_Profile d
    JOIN [User] u ON d.UserID = u.UserID
    JOIN Vehicle v ON v.Owner_DriverID = d.DriverID
    JOIN Service_Type st ON v.Service_type_id = st.Service_type_id
    WHERE v.Home_GeofenceID = @ZoneID 
      AND d.IsAvailable = 1
      AND d.IsAutonomous = @IsAutonomous
      AND v.Status = 'Active'; -- [SECURITY] Only show approved vehicles
END;
GO

/* ==========================================================
   3. BOOKING LOGIC (Graph Routing)
   ========================================================== */
CREATE OR ALTER PROCEDURE sp_BookRide_Geo
    @UserID INT,
    @ServiceTypeID INT,
    @StartLat DECIMAL(9,6), @StartLon DECIMAL(9,6),
    @EndLat DECIMAL(9,6), @EndLon DECIMAL(9,6),
    @TotalDist DECIMAL(10,2),
    @SpecificDriverID INT = NULL 
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @RideID INT;
    DECLARE @StartZone INT, @EndZone INT;

    -- [GEOSPATIAL] Identify Start and End Zones
    SELECT @StartZone = GeofenceID FROM Geofence 
    WHERE @StartLat <= TopLeft_Lat AND @StartLat >= BottomRight_Lat AND @StartLon >= TopLeft_Lon AND @StartLon <= BottomRight_Lon;

    SELECT @EndZone = GeofenceID FROM Geofence 
    WHERE @EndLat <= TopLeft_Lat AND @EndLat >= BottomRight_Lat AND @EndLon >= TopLeft_Lon AND @EndLon <= BottomRight_Lon;

    -- Fallbacks
    IF @StartZone IS NULL SET @StartZone = 1; 
    IF @EndZone IS NULL SET @EndZone = 1;

    -- Create Main Ride Record
    INSERT INTO Ride (Passenger_UserID, Service_type_id, Start_Lat, Start_Lon, End_Lat, End_Lon, EstimatedDistance_km, Status, Requested_time)
    VALUES (@UserID, @ServiceTypeID, @StartLat, @StartLon, @EndLat, @EndLon, @TotalDist, 'Requested', GETDATE());
    SET @RideID = SCOPE_IDENTITY();

    -- [ALGORITHM] Recursive CTE (Common Table Expression) to find path through zones
    -- This calculates the multi-hop route (e.g., Nicosia -> Larnaca -> Limassol)
    ;WITH TripPath AS (
        SELECT FromZoneID AS CurrentZone, CAST(FromZoneID AS VARCHAR(MAX)) AS PathStr, 1 AS LegOrder
        FROM ZoneConnection WHERE FromZoneID = @StartZone
        UNION ALL
        SELECT zc.ToZoneID, CAST(tp.PathStr + ',' + CAST(zc.ToZoneID AS VARCHAR(MAX)) AS VARCHAR(MAX)), tp.LegOrder + 1
        FROM ZoneConnection zc INNER JOIN TripPath tp ON zc.FromZoneID = tp.CurrentZone
        WHERE tp.PathStr NOT LIKE '%' + CAST(zc.ToZoneID AS VARCHAR(MAX)) + '%' AND tp.CurrentZone != @EndZone
    )
    -- Insert distinct segments for each zone crossing
    INSERT INTO RideSegment (RideID, SequenceOrder, GeofenceID, Status, EstimatedCost)
    SELECT 
        @RideID,
        ROW_NUMBER() OVER(ORDER BY CHARINDEX(',' + value + ',', ',' + TopPath.PathStr + ',')), 
        value, 
        'Requested', 
        @TotalDist / (SELECT COUNT(*) FROM STRING_SPLIT(TopPath.PathStr, ',')) -- Split cost evenly per leg
    FROM (SELECT TOP 1 PathStr FROM TripPath WHERE CurrentZone = @EndZone ORDER BY LegOrder ASC) AS TopPath
    CROSS APPLY STRING_SPLIT(TopPath.PathStr, ',');

    -- [FALLBACK] If start and end are in the same zone
    IF NOT EXISTS (SELECT 1 FROM RideSegment WHERE RideID = @RideID)
    BEGIN
        INSERT INTO RideSegment (RideID, SequenceOrder, GeofenceID, Status, EstimatedCost)
        VALUES (@RideID, 1, @StartZone, 'Requested', @TotalDist);
    END

    -- [COORDINATES] Update Start/End points of segments to match Transfer points (Bridges)
    UPDATE RideSegment SET Start_Lat = @StartLat, Start_Lon = @StartLon 
    WHERE RideID = @RideID AND SequenceOrder = 1;

    UPDATE RideSegment SET End_Lat = @EndLat, End_Lon = @EndLon 
    WHERE RideID = @RideID AND SequenceOrder = (SELECT MAX(SequenceOrder) FROM RideSegment WHERE RideID=@RideID);

    -- Stitch middle segments together using ZoneConnection Transfer points
    UPDATE rs1
    SET rs1.End_Lat = zc.Transfer_Lat, rs1.End_Lon = zc.Transfer_Lon
    FROM RideSegment rs1
    JOIN RideSegment rs2 ON rs1.RideID = rs2.RideID AND rs2.SequenceOrder = rs1.SequenceOrder + 1
    JOIN ZoneConnection zc ON rs1.GeofenceID = zc.FromZoneID AND rs2.GeofenceID = zc.ToZoneID
    WHERE rs1.RideID = @RideID;

    UPDATE rs2
    SET rs2.Start_Lat = zc.Transfer_Lat, rs2.Start_Lon = zc.Transfer_Lon
    FROM RideSegment rs2
    JOIN RideSegment rs1 ON rs2.RideID = rs1.RideID AND rs1.SequenceOrder = rs2.SequenceOrder - 1
    JOIN ZoneConnection zc ON rs1.GeofenceID = zc.FromZoneID AND rs2.GeofenceID = zc.ToZoneID
    WHERE rs2.RideID = @RideID;

    IF @SpecificDriverID IS NOT NULL
    BEGIN
        UPDATE RideSegment 
        SET DriverID = @SpecificDriverID
        WHERE RideID = @RideID AND SequenceOrder = 1;
    END

    SELECT @RideID AS RideID;
END;
GO

/* ==========================================================
   4. DRIVER OPERATIONS (UPDATED)
   ========================================================== */
CREATE OR ALTER PROCEDURE sp_Driver_GetAvailableRides
    @UserID INT
AS
BEGIN
    DECLARE @MyZone INT;
    DECLARE @MyDriverID INT;
    
    -- [LOGIC] Get Zone from the Driver's Active VEHICLE
    SELECT TOP 1 @MyZone = v.Home_GeofenceID, @MyDriverID = d.DriverID 
    FROM Driver_Profile d
    JOIN Vehicle v ON d.DriverID = v.Owner_DriverID
    WHERE d.UserID = @UserID AND v.Status = 'Active'; 
    
    -- [LOGIC] Driver only sees rides in their Zone that are 'Requested'
    -- Also enforces sequence: Can't pick up Leg 2 until Leg 1 is completed.
    SELECT rs.SegmentID, rs.EstimatedCost, rs.SequenceOrder, u.Firstname AS PassengerName, g.Name AS ZoneName,
           rs.Start_Lat, rs.Start_Lon, r.Ride_id
    FROM RideSegment rs
    JOIN Ride r ON rs.RideID = r.Ride_id
    JOIN [User] u ON r.Passenger_UserID = u.UserID
    JOIN Geofence g ON rs.GeofenceID = g.GeofenceID
    WHERE rs.GeofenceID = @MyZone 
      AND rs.Status = 'Requested'
      AND (rs.DriverID IS NULL OR rs.DriverID = @MyDriverID)
      AND (rs.SequenceOrder = 1 OR EXISTS (
          SELECT 1 FROM RideSegment prev 
          WHERE prev.RideID = rs.RideID 
          AND prev.SequenceOrder = rs.SequenceOrder - 1 
          AND prev.Status = 'Completed'
      ))
    ORDER BY rs.RideID DESC;
END;
GO

CREATE OR ALTER PROCEDURE sp_Driver_AcceptRide
    @SegmentID INT, @UserID INT
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @DID INT = (SELECT DriverID FROM Driver_Profile WHERE UserID = @UserID);
    DECLARE @VID INT = (SELECT TOP 1 Vehicle_ID FROM Vehicle WHERE Owner_DriverID = @DID AND Status='Active');
    
    -- [CONCURRENCY] This assumes first to hit the DB gets the update
    UPDATE RideSegment SET DriverID = @DID, VehicleID = @VID, Status = 'Accepted' WHERE SegmentID = @SegmentID;
    UPDATE Ride SET Status = 'Accepted' WHERE Ride_id = (SELECT RideID FROM RideSegment WHERE SegmentID=@SegmentID);
END;
GO

CREATE OR ALTER PROCEDURE sp_ConfirmPickup_Passenger
    @RideID INT
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @SegID INT = (SELECT TOP 1 SegmentID FROM RideSegment WHERE RideID=@RideID AND Status != 'Completed' ORDER BY SequenceOrder ASC);
    
    -- [HANDSHAKE] Both Passenger and Driver must click "Start"
    UPDATE RideSegment SET Passenger_Started = 1 WHERE SegmentID = @SegID;
    
    -- Check if both parties agreed
    IF (SELECT CAST(Passenger_Started AS INT) + CAST(Driver_Started AS INT) FROM RideSegment WHERE SegmentID = @SegID) = 2
    BEGIN
        UPDATE RideSegment SET Status = 'InProgress' WHERE SegmentID = @SegID;
        UPDATE Ride SET Status = 'InProgress', Start_time = GETDATE() WHERE Ride_id = @RideID;
    END
END;
GO

CREATE OR ALTER PROCEDURE sp_ConfirmPickup_Driver
    @SegmentID INT
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE RideSegment SET Driver_Started = 1 WHERE SegmentID = @SegmentID;
    
    -- [HANDSHAKE] Check if both parties agreed
    IF (SELECT CAST(Passenger_Started AS INT) + CAST(Driver_Started AS INT) FROM RideSegment WHERE SegmentID = @SegmentID) = 2
    BEGIN
        UPDATE RideSegment SET Status = 'InProgress' WHERE SegmentID = @SegmentID;
        UPDATE Ride SET Status = 'InProgress' WHERE Ride_id = (SELECT RideID FROM RideSegment WHERE SegmentID=@SegmentID);
    END
END;
GO

CREATE OR ALTER PROCEDURE sp_Driver_CompleteRide
    @SegmentID INT
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE RideSegment SET Status = 'Completed' WHERE SegmentID = @SegmentID;
    DECLARE @RideID INT = (SELECT RideID FROM RideSegment WHERE SegmentID = @SegmentID);
    
    -- [FINANCIAL] Calculate Platform Fee (10%) and Record Payment
    DECLARE @Cost DECIMAL(10,2) = (SELECT EstimatedCost FROM RideSegment WHERE SegmentID = @SegmentID);
    INSERT INTO Payment (Ride_ID, Amount, PlatformFee, PaymentMethod) 
    VALUES (@RideID, @Cost, @Cost * 0.10, 'Credit Card');
    
    -- [LOGIC] If all segments are done, close the main Ride ticket
    IF NOT EXISTS (SELECT 1 FROM RideSegment WHERE RideID = @RideID AND Status != 'Completed')
    BEGIN
        UPDATE Ride SET Status = 'Completed', End_time = GETDATE(), Final_Cost = (SELECT SUM(EstimatedCost) FROM RideSegment WHERE RideID=@RideID)
        WHERE Ride_id = @RideID;
    END
END;
GO

/* ==========================================================
   5. ADMIN & UTILS
   ========================================================== */
CREATE OR ALTER PROCEDURE sp_Admin_VerifyDriver @DriverID INT, @AdminID INT AS
BEGIN UPDATE Driver_Profile SET Status='Active' WHERE DriverID=@DriverID; END;
GO

CREATE OR ALTER PROCEDURE sp_Admin_VerifyVehicle @VehicleID INT, @AdminID INT AS
BEGIN UPDATE Vehicle SET Status='Active', SafetyStatus='Passed' WHERE Vehicle_ID=@VehicleID; END;
GO

CREATE OR ALTER PROCEDURE sp_Admin_GetDocuments
    @EntityID INT, 
    @Type NVARCHAR(20) 
AS
BEGIN
    SET NOCOUNT ON;
    IF @Type = 'Driver'
        SELECT 'Personal' AS Category, DocumentType, FilePath, UploadDate, IsVerified 
        FROM Driver_Documents WHERE DriverID = @EntityID;
    ELSE IF @Type = 'Vehicle'
        SELECT 'Vehicle' AS Category, DocumentType, FilePath, UploadDate, IsVerified 
        FROM Vehicle_Documents WHERE Vehicle_ID = @EntityID;
END;
GO

CREATE OR ALTER PROCEDURE sp_Chat_SendMessage @RideID INT, @SenderID INT, @Text NVARCHAR(MAX) AS
BEGIN INSERT INTO Message (RideID, SenderUserID, MessageText) VALUES (@RideID, @SenderID, @Text); END;
GO

CREATE OR ALTER PROCEDURE sp_Chat_GetMessages @RideID INT AS
BEGIN SELECT m.MessageText, m.SentAt, u.Firstname FROM Message m JOIN [User] u ON m.SenderUserID=u.UserID WHERE m.RideID=@RideID ORDER BY m.SentAt ASC; END;
GO

CREATE OR ALTER PROCEDURE sp_Driver_ToggleAvailability @UserID INT, @IsAvailable BIT AS
BEGIN UPDATE Driver_Profile SET IsAvailable = @IsAvailable WHERE UserID = @UserID; END;
GO

CREATE OR ALTER PROCEDURE sp_Report_CostAnalysis AS
BEGIN SELECT st.Name AS ServiceType, AVG(r.Final_Cost) AS AvgCost FROM Ride r JOIN Service_Type st ON r.Service_type_id=st.Service_type_id WHERE r.Status='Completed' GROUP BY st.Name; END;
GO

CREATE OR ALTER PROCEDURE sp_Report_HighActivity AS
BEGIN SELECT DATEPART(HOUR, Requested_time) AS HourOfDay, COUNT(*) AS TotalRequests FROM Ride WITH (NOLOCK) GROUP BY DATEPART(HOUR, Requested_time) ORDER BY TotalRequests DESC; END;
GO

CREATE OR ALTER PROCEDURE sp_Admin_GetPaymentLogs
AS
BEGIN
    SET NOCOUNT ON;
    -- [REPORTING] Complex join to show who paid, who drove, and platform fees
    SELECT TOP 200 
        p.Date AS Timestamp, 
        p.PaymentMethod, 
        p.PaymentStatus, 
        p.Amount AS TotalAmount, 
        p.PlatformFee, 
        (p.Amount - p.PlatformFee) AS DriverEarnings, 
        pass.Firstname + ' ' + pass.Lastname AS PayerName, 
        ISNULL(drv.DriverName, 'Unassigned') AS PayeeName
    FROM Payment p WITH (NOLOCK) 
    JOIN Ride r ON p.Ride_ID = r.Ride_id 
    JOIN [User] pass ON r.Passenger_UserID = pass.UserID 
    OUTER APPLY (
        SELECT TOP 1 u.Firstname + ' ' + u.Lastname AS DriverName
        FROM RideSegment rs 
        JOIN Driver_Profile dp ON rs.DriverID = dp.DriverID
        JOIN [User] u ON dp.UserID = u.UserID
        WHERE rs.RideID = r.Ride_id AND rs.DriverID IS NOT NULL
    ) drv
    ORDER BY p.Date DESC;
END;
GO

CREATE OR ALTER PROCEDURE sp_Admin_GetRideLogs AS
BEGIN SELECT TOP 100 ral.RideID, ral.OldStatus, ral.NewStatus, ral.ChangedBy, ral.ChangeDate FROM Ride_Audit_Log ral WITH (NOLOCK) ORDER BY ral.ChangeDate DESC; END;
GO

CREATE OR ALTER PROCEDURE sp_GetUserPayments @UserID INT AS
BEGIN SELECT p.Amount, p.Date, p.PaymentStatus, st.Name as ServiceType, r.Ride_id, r.Start_Lat, r.End_Lat FROM Payment p WITH (NOLOCK) JOIN Ride r ON p.Ride_ID=r.Ride_id JOIN Service_Type st ON r.Service_type_id=st.Service_type_id WHERE r.Passenger_UserID=@UserID ORDER BY p.Date DESC; END;
GO

CREATE OR ALTER PROCEDURE sp_GDPR_ForgetMe @UserID INT AS
BEGIN 
    SET NOCOUNT ON; 
    -- [ACID] Transaction ensures either ALL data is anonymized OR nothing changes if error occurs
    BEGIN TRANSACTION; 
        INSERT INTO GDPR_Log (UserID, Action) VALUES (@UserID, 'Deletion Request'); 
        -- [ANONYMIZATION] Replaces PII with generic data
        UPDATE [User] SET Firstname='Anon', Lastname='User', Email=CONCAT('del_',@UserID,'@anon'), PasswordHash='DEL', IsDeleted=1 WHERE UserID=@UserID; 
        UPDATE Driver_Profile SET Status='Suspended' WHERE UserID=@UserID; 
    COMMIT TRANSACTION; 
END;
GO

CREATE OR ALTER PROCEDURE sp_SubmitReview @RideID INT, @ReviewerID INT, @RevieweeID INT, @Rating INT, @Comment NVARCHAR(MAX) AS
BEGIN INSERT INTO Review (Ride_id, Reviewer_UserID, Reviewee_UserID, Rating, Comment) VALUES (@RideID, @ReviewerID, @RevieweeID, @Rating, @Comment); END;
GO

CREATE OR ALTER PROCEDURE sp_Report_Flexible
    @StartDate DATE = NULL,
    @ServiceTypeID INT = NULL,
    @GroupBy VARCHAR(20) = 'None'
AS
BEGIN
    SET NOCOUNT ON;
    -- [DYNAMIC REPORTING] Allows Admin to filter and group revenue data
    SELECT 
        CASE 
            WHEN @GroupBy = 'Day' THEN CONVERT(NVARCHAR(20), r.Requested_time, 23) 
            WHEN @GroupBy = 'Service' THEN st.Name
            WHEN @GroupBy = 'Driver' THEN u.Lastname
            ELSE 'Total'
        END AS GroupKey,
        COUNT(*) as TotalRides,
        SUM(r.Final_Cost) as TotalRevenue
    FROM Ride r
    JOIN Service_Type st ON r.Service_type_id = st.Service_type_id
    JOIN RideSegment rs ON r.Ride_id = rs.RideID
    JOIN Driver_Profile dp ON rs.DriverID = dp.DriverID
    JOIN [User] u ON dp.UserID = u.UserID
    WHERE (@StartDate IS NULL OR r.Requested_time >= @StartDate)
      AND (@ServiceTypeID IS NULL OR r.Service_type_id = @ServiceTypeID)
      AND rs.SequenceOrder = 1 
    GROUP BY 
        CASE 
            WHEN @GroupBy = 'Day' THEN CONVERT(NVARCHAR(20), r.Requested_time, 23)
            WHEN @GroupBy = 'Service' THEN st.Name
            WHEN @GroupBy = 'Driver' THEN u.Lastname
            ELSE 'Total'
        END
END
GO

/* ==========================================================
   NEW: OPERATOR & ADMIN FEATURES
   ========================================================== */

-- 1. Promote a User to Operator (Admin Only)
CREATE OR ALTER PROCEDURE sp_Admin_PromoteToOperator
    @TargetEmail NVARCHAR(100)
AS
BEGIN
    DECLARE @UID INT = (SELECT UserID FROM [User] WHERE Email = @TargetEmail);
    DECLARE @RID INT = (SELECT Role_id FROM Role WHERE Name = 'Operator');
    
    IF @UID IS NOT NULL AND @RID IS NOT NULL
    BEGIN
        IF NOT EXISTS (SELECT 1 FROM User_Role WHERE UserID = @UID AND Role_id = @RID)
        BEGIN
            INSERT INTO User_Role (UserID, Role_id) VALUES (@UID, @RID);
        END
    END
END;
GO

-- 2. Operator Add Service Type
CREATE OR ALTER PROCEDURE sp_Operator_AddServiceType
    @Name NVARCHAR(100),
    @MinFare DECIMAL(10,2),
    @Rate DECIMAL(10,2),
    @Desc NVARCHAR(255)
AS
BEGIN
    INSERT INTO Service_Type (Name, MinimumFare, PerKilometerRate, Description)
    VALUES (@Name, @MinFare, @Rate, @Desc);
END;
GO

-- 3. Detailed Driver Logs (Earnings & History)
CREATE OR ALTER PROCEDURE sp_Driver_GetEarningsLog
    @UserID INT
AS
BEGIN
    -- Finds the DriverID for this User, then fetches completed segments
    DECLARE @DID INT = (SELECT DriverID FROM Driver_Profile WHERE UserID = @UserID);

    SELECT 
        r.Ride_id,
        r.End_time,
        st.Name as ServiceName,
        rs.EstimatedCost as RideRevenue, -- The amount for this specific segment
        (rs.EstimatedCost * 0.90) as DriverNetEarnings, -- Assuming 10% platform fee
        v.RegistrationPlate,
        v.Model
    FROM RideSegment rs
    JOIN Ride r ON rs.RideID = r.Ride_id
    JOIN Vehicle v ON rs.VehicleID = v.Vehicle_ID
    JOIN Service_Type st ON v.Service_type_id = st.Service_type_id
    WHERE rs.DriverID = @DID 
      AND rs.Status = 'Completed'
    ORDER BY r.End_time DESC;
END;
GO

-- 4. Get Dashboard Stats for Driver
CREATE OR ALTER PROCEDURE sp_Driver_GetStats
    @UserID INT
AS
BEGIN
    DECLARE @DID INT = (SELECT DriverID FROM Driver_Profile WHERE UserID = @UserID);
    
    SELECT 
        COUNT(*) as TotalTrips,
        ISNULL(SUM(EstimatedCost), 0) as TotalGross,
        ISNULL(SUM(EstimatedCost * 0.90), 0) as TotalNet
    FROM RideSegment 
    WHERE DriverID = @DID AND Status = 'Completed';
END;
GO
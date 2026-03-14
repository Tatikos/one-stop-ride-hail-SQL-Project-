USE gfotio01;
GO

/* ==========================================================
   PHASE 1: CLEANUP
   [MAINTENANCE] Drops existing objects to ensure a clean slate 
   for the schema creation.
   ========================================================== */
DECLARE @sql NVARCHAR(MAX) = N'';
SELECT @sql += N'ALTER TABLE ' + QUOTENAME(OBJECT_SCHEMA_NAME(parent_object_id))
    + N'.' + QUOTENAME(OBJECT_NAME(parent_object_id)) 
    + N' DROP CONSTRAINT ' + QUOTENAME(name) + N'; '
FROM sys.foreign_keys;
EXEC sp_executesql @sql;

-- Drop Tables in Order to handle dependencies
IF OBJECT_ID('ZoneConnection', 'U') IS NOT NULL DROP TABLE ZoneConnection;
IF OBJECT_ID('RideSegment', 'U') IS NOT NULL DROP TABLE RideSegment;
IF OBJECT_ID('Message', 'U') IS NOT NULL DROP TABLE Message;
IF OBJECT_ID('Review', 'U') IS NOT NULL DROP TABLE Review;
IF OBJECT_ID('Payment', 'U') IS NOT NULL DROP TABLE Payment;
IF OBJECT_ID('Ride_Audit_Log', 'U') IS NOT NULL DROP TABLE Ride_Audit_Log;
IF OBJECT_ID('Ride', 'U') IS NOT NULL DROP TABLE Ride;
IF OBJECT_ID('Vehicle_Documents', 'U') IS NOT NULL DROP TABLE Vehicle_Documents;
IF OBJECT_ID('Vehicle', 'U') IS NOT NULL DROP TABLE Vehicle; 
IF OBJECT_ID('Driver_Documents', 'U') IS NOT NULL DROP TABLE Driver_Documents;
IF OBJECT_ID('Driver_Profile', 'U') IS NOT NULL DROP TABLE Driver_Profile; 
IF OBJECT_ID('User_Role', 'U') IS NOT NULL DROP TABLE User_Role;
IF OBJECT_ID('GDPR_Log', 'U') IS NOT NULL DROP TABLE GDPR_Log;
IF OBJECT_ID('User', 'U') IS NOT NULL DROP TABLE [User];
IF OBJECT_ID('Service_Type', 'U') IS NOT NULL DROP TABLE Service_Type;
IF OBJECT_ID('Role', 'U') IS NOT NULL DROP TABLE Role;
IF OBJECT_ID('Geofence', 'U') IS NOT NULL DROP TABLE Geofence;
GO

/* ==========================================================
   PHASE 2: TABLES
   ========================================================== */

-- [RBAC] Role Based Access Control Definitions
CREATE TABLE Role (
    Role_id INT IDENTITY(1,1) PRIMARY KEY,
    Name NVARCHAR(50) NOT NULL UNIQUE
);

-- [PRICING] Defines base rates for different ride types
CREATE TABLE Service_Type (
    Service_type_id INT IDENTITY(1,1) PRIMARY KEY,
    Name NVARCHAR(100) NOT NULL UNIQUE, 
    MinimumFare DECIMAL(10, 2) DEFAULT 5.00,
    PerKilometerRate DECIMAL(10, 2) NOT NULL,
    Description NVARCHAR(255)
);

-- [GEOSPATIAL] Defines rectangular zones for the graph routing logic
CREATE TABLE Geofence (
    GeofenceID INT IDENTITY(1,1) PRIMARY KEY,
    Name NVARCHAR(100),
    TopLeft_Lat DECIMAL(9,6), TopLeft_Lon DECIMAL(9,6),
    BottomRight_Lat DECIMAL(9,6), BottomRight_Lon DECIMAL(9,6)
);

-- [GRAPH LOGIC] Defines how zones connect to each other (Edges in the graph)
CREATE TABLE ZoneConnection (
    FromZoneID INT,
    ToZoneID INT,
    Transfer_Lat DECIMAL(9,6), 
    Transfer_Lon DECIMAL(9,6),
    PRIMARY KEY (FromZoneID, ToZoneID),
    FOREIGN KEY (FromZoneID) REFERENCES Geofence(GeofenceID),
    FOREIGN KEY (ToZoneID) REFERENCES Geofence(GeofenceID)
);

CREATE TABLE [User] (
    UserID INT IDENTITY(1,1) PRIMARY KEY,
    Firstname NVARCHAR(50) NOT NULL,
    Lastname NVARCHAR(50) NOT NULL,
    Email NVARCHAR(100) NOT NULL UNIQUE, -- [DATA INTEGRITY] No duplicate emails
    PasswordHash NVARCHAR(255) NOT NULL, -- [SECURITY] Store HASH, never plain text
    Dateofbirth DATE NOT NULL,
    Gender NVARCHAR(20),
    Address NVARCHAR(255),
    IsDeleted BIT DEFAULT 0, -- [PRIVACY] Soft Delete flag (don't actually delete rows immediately)
    CreatedAt DATETIME DEFAULT GETDATE(),
    LastModified DATETIME DEFAULT GETDATE()
);

-- [RBAC] Many-to-Many relationship between Users and Roles
CREATE TABLE User_Role (
    UserID INT NOT NULL,
    Role_id INT NOT NULL,
    PRIMARY KEY (UserID, Role_id),
    FOREIGN KEY (UserID) REFERENCES [User](UserID) ON DELETE CASCADE,
    FOREIGN KEY (Role_id) REFERENCES Role(Role_id)
);

CREATE TABLE Driver_Profile (
    DriverID INT IDENTITY(1,1) PRIMARY KEY,
    UserID INT NOT NULL UNIQUE,
    Status NVARCHAR(20) DEFAULT 'Pending', -- Requires Admin Approval
    IsAutonomous BIT DEFAULT 0,
    IsAvailable BIT DEFAULT 1,
    WalletBalance DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (UserID) REFERENCES [User](UserID)
);

CREATE TABLE Driver_Documents (
    Doc_Id INT IDENTITY(1,1) PRIMARY KEY,
    DriverID INT NOT NULL,
    DocumentType NVARCHAR(50) NOT NULL,
    DocumentNumber NVARCHAR(50),
    ExpiryDate DATE,
    FilePath NVARCHAR(255) NOT NULL,
    UploadDate DATETIME DEFAULT GETDATE(),
    IsVerified BIT DEFAULT 0,
    FOREIGN KEY (DriverID) REFERENCES Driver_Profile(DriverID)
);

CREATE TABLE Vehicle (
    Vehicle_ID INT IDENTITY(1,1) PRIMARY KEY,
    Owner_DriverID INT NOT NULL,
    Home_GeofenceID INT NOT NULL, -- [LOGIC] Vehicle belongs to a specific zone
    RegistrationPlate NVARCHAR(20) NOT NULL UNIQUE,
    Model NVARCHAR(50) NOT NULL,
    Type NVARCHAR(50),
    PassengerSeats INT DEFAULT 4,
    Service_type_id INT NOT NULL,
    SafetyStatus NVARCHAR(20) DEFAULT 'Pending',
    Status NVARCHAR(20) DEFAULT 'Pending',
    FOREIGN KEY (Owner_DriverID) REFERENCES Driver_Profile(DriverID),
    FOREIGN KEY (Service_type_id) REFERENCES Service_Type(Service_type_id),
    FOREIGN KEY (Home_GeofenceID) REFERENCES Geofence(GeofenceID)
);

CREATE TABLE Vehicle_Documents (
    Doc_Id INT IDENTITY(1,1) PRIMARY KEY,
    Vehicle_ID INT NOT NULL,
    DocumentType NVARCHAR(50) NOT NULL,
    DocumentNumber NVARCHAR(50),
    ExpiryDate DATE,
    FilePath NVARCHAR(255) NOT NULL,
    UploadDate DATETIME DEFAULT GETDATE(),
    IsVerified BIT DEFAULT 0,
    FOREIGN KEY (Vehicle_ID) REFERENCES Vehicle(Vehicle_ID)
);

-- [CORE] The master record for a booked trip
CREATE TABLE Ride (
    Ride_id INT IDENTITY(1,1) PRIMARY KEY,
    Passenger_UserID INT NOT NULL,
    Service_type_id INT NOT NULL,
    Start_Lat DECIMAL(9,6) NOT NULL, Start_Lon DECIMAL(9,6) NOT NULL,
    End_Lat DECIMAL(9,6) NOT NULL, End_Lon DECIMAL(9,6) NOT NULL,
    Requested_time DATETIME DEFAULT GETDATE(),
    Start_time DATETIME NULL,
    End_time DATETIME NULL,
    Status NVARCHAR(20) DEFAULT 'Requested',
    EstimatedDistance_km DECIMAL(10,2),
    Final_Cost DECIMAL(10,2),
    FOREIGN KEY (Passenger_UserID) REFERENCES [User](UserID),
    FOREIGN KEY (Service_type_id) REFERENCES Service_Type(Service_type_id)
);

-- [MULTI-HOP] Breaks a Ride into segments if it crosses multiple zones
CREATE TABLE RideSegment (
    SegmentID INT IDENTITY(1,1) PRIMARY KEY,
    RideID INT NOT NULL,
    SequenceOrder INT NOT NULL, -- 1st leg, 2nd leg, etc.
    GeofenceID INT NOT NULL,    
    DriverID INT NULL,          -- Assigned per segment
    VehicleID INT NULL,
    Status NVARCHAR(20) DEFAULT 'Requested',
    Passenger_Started BIT DEFAULT 0,
    Driver_Started BIT DEFAULT 0,
    EstimatedCost DECIMAL(10,2),
    
    Start_Lat DECIMAL(9,6), Start_Lon DECIMAL(9,6),
    End_Lat DECIMAL(9,6), End_Lon DECIMAL(9,6),

    FOREIGN KEY (RideID) REFERENCES Ride(Ride_id) ON DELETE CASCADE,
    FOREIGN KEY (GeofenceID) REFERENCES Geofence(GeofenceID),
    FOREIGN KEY (DriverID) REFERENCES Driver_Profile(DriverID),
    FOREIGN KEY (VehicleID) REFERENCES Vehicle(Vehicle_ID)
);

CREATE TABLE Payment (
    Payment_id INT IDENTITY(1,1) PRIMARY KEY,
    Ride_ID INT NOT NULL,
    Amount DECIMAL(10,2) NOT NULL,
    PlatformFee DECIMAL(10,2) NOT NULL, -- [BUSINESS] Revenue generation
    PaymentMethod NVARCHAR(50) DEFAULT 'Credit Card',
    PaymentStatus NVARCHAR(50) DEFAULT 'Paid',
    Date DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (Ride_ID) REFERENCES Ride(Ride_id)
);

CREATE TABLE Review (
    Review_id INT IDENTITY(1,1) PRIMARY KEY,
    Ride_id INT NOT NULL,
    Reviewer_UserID INT NOT NULL,
    Reviewee_UserID INT NOT NULL,
    Rating INT NOT NULL CHECK (Rating BETWEEN 1 AND 5), -- [CONSTRAINT] Enforces valid ratings
    Comment NVARCHAR(MAX),
    Timestamp DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (Ride_id) REFERENCES Ride(Ride_id)
);

CREATE TABLE Message (
    MsgID INT IDENTITY(1,1) PRIMARY KEY,
    RideID INT NOT NULL,
    SenderUserID INT NOT NULL,
    MessageText NVARCHAR(MAX),
    SentAt DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (RideID) REFERENCES Ride(Ride_id)
);

-- [COMPLIANCE] Logs GDPR deletion requests
CREATE TABLE GDPR_Log (
    LogID INT IDENTITY(1,1) PRIMARY KEY,
    UserID INT NOT NULL,
    Action NVARCHAR(100), 
    Timestamp DATETIME DEFAULT GETDATE()
);

-- [AUDIT] Tracks changes to Ride status for debugging and security
CREATE TABLE Ride_Audit_Log (
    AuditID INT IDENTITY(1,1) PRIMARY KEY,
    RideID INT NOT NULL,
    OldStatus NVARCHAR(20),
    NewStatus NVARCHAR(20),
    ChangedBy NVARCHAR(100) DEFAULT SYSTEM_USER,
    ChangeDate DATETIME DEFAULT GETDATE()
);
GO
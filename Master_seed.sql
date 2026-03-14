USE gfotio01;
GO

/* ==========================================================
   BASIC SETUP
   ========================================================== */
INSERT INTO Role (Name) VALUES ('Admin'), ('Driver'), ('Passenger');

INSERT INTO Service_Type (Name, MinimumFare, PerKilometerRate, Description) VALUES 
('Simple Route', 5.00, 0.90, 'Standard transport'),
('Luxury Route', 12.00, 2.50, 'High-end vehicles'),
('Light Cargo', 15.00, 1.80, 'Small van'),
('Heavy Cargo', 40.00, 3.50, 'Large truck');

/* ==========================================================
   GEOSPATIAL GRAPH SETUP
   ========================================================== */

-- 1. INSERT ZONES
-- These are the "Nodes" in our graph.
INSERT INTO Geofence (Name, TopLeft_Lat, TopLeft_Lon, BottomRight_Lat, BottomRight_Lon) VALUES 
('Nicosia', 35.30, 33.20, 35.10, 33.45), -- Zone ID 1
('Larnaca', 35.00, 33.50, 34.80, 33.70), -- Zone ID 2
('Limassol', 34.75, 32.90, 34.60, 33.15); -- Zone ID 3

-- 2. INSERT CONNECTIONS WITH BRIDGE COORDINATES
-- These are the "Edges". 
-- The 'Transfer_Lat/Lon' is the physical location where the handover happens.

-- Nicosia <-> Larnaca (Alambra/Dali Exit area)
INSERT INTO ZoneConnection (FromZoneID, ToZoneID, Transfer_Lat, Transfer_Lon) 
VALUES (1, 2, 34.985000, 33.405000);

INSERT INTO ZoneConnection (FromZoneID, ToZoneID, Transfer_Lat, Transfer_Lon) 
VALUES (2, 1, 34.985000, 33.405000);

-- Larnaca <-> Limassol (Choirokoitia area)
INSERT INTO ZoneConnection (FromZoneID, ToZoneID, Transfer_Lat, Transfer_Lon) 
VALUES (2, 3, 34.795000, 33.335000);

INSERT INTO ZoneConnection (FromZoneID, ToZoneID, Transfer_Lat, Transfer_Lon) 
VALUES (3, 2, 34.795000, 33.335000);

/* ==========================================================
   ACCOUNTS
   ========================================================== */

-- Admin User
-- [NOTE] In production, generate a real Bcrypt hash. This is a placeholder.
INSERT INTO [User] (Firstname, Lastname, Email, PasswordHash, Dateofbirth, Gender, Address)
VALUES ('System', 'Admin', 'admin@osrh.com', '$2y$10$DummyHash', '1980-01-01', 'N/A', 'HQ');
INSERT INTO User_Role (UserID, Role_id) SELECT SCOPE_IDENTITY(), (SELECT Role_id FROM Role WHERE Name='Admin');
GO

-- 1. Add Operator Role
IF NOT EXISTS (SELECT 1 FROM Role WHERE Name = 'Operator')
BEGIN
    INSERT INTO Role (Name) VALUES ('Operator');
END

-- 2. (Optional) Make sure we have some initial data if table was empty
GO
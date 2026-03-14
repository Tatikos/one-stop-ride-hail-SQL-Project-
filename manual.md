OSRH System Technical Documentation

Student Name: [Your Name Here]

Student ID: [Your ID Here]

Date: November 2025

1. Architectural Overview

The Open Source Ride Hailing (OSRH) system is built on a robust 3-tier architecture designed for scalability, security, and data integrity.

1.1 Presentation Layer

Technologies: PHP (Vanilla), HTML5, Bootstrap 5, JavaScript (ES6).

Function: Provides responsive user interfaces for three distinct roles:

Passengers: (index.php) Real-time map interaction, route visualization, and booking.

Drivers: (driver_dashboard.php) Job feed, interactive map for pickup/drop-off, and income tracking.

Administrators: (admin.php) Financial oversight, document verification, and system logs.

1.2 Application Logic Layer

Approach: Hybrid "Thin Client, Fat Database".

Function: PHP handles session management, API endpoints (status_api.php, chat_api.php), and request routing. However, all core business logic (bookings, financial calculations, complex routing) is offloaded to the database layer to ensure consistency and performance.

1.3 Data Layer

Database: Microsoft SQL Server.

Key Features: Advanced T-SQL implementation including Stored Procedures, Triggers, Views, and Graph-based routing tables.

2. Database Design & Normalization

The database schema has been normalized to 3NF (Third Normal Form) to eliminate redundancy and ensure data integrity.

2.1 Graph-Based Routing (Future Proofing)

Instead of a simple "Point A to Point B" link, the system implements a graph-based routing engine.

Nodes: Represented by the Geofence table.

Edges: Represented by the ZoneConnection table.

Trips: A single Ride is broken down into multiple entries in the RideSegment table.

Benefit: This allows for multi-leg trips (e.g., Nicosia $\to$ Transfer Point $\to$ Limassol) and enables the system to scale to complex logistics scenarios without changing the database schema.

2.2 Role-Based Access Control (RBAC)

The system uses a flexible RBAC model:

User table stores identity.

Role table stores permission sets.

User_Role (Many-to-Many) allows a single entity to act as both a Passenger and a Driver simultaneously.

3. Implementation Details

3.1 Stored Procedures (Data Management)

To ensure security against SQL Injection and maintain logical consistency, PHP never executes raw INSERT or UPDATE commands on critical tables.

sp_BookRide_Geo: Utilizing a Recursive Common Table Expression (CTE), this procedure calculates the path between zones and automatically generates the necessary RideSegment records.

sp_ConfirmPickup_Passenger: Implements a "Dual Confirmation" handshake. A ride status only transitions to InProgress when both the driver and passenger have independently confirmed the pickup via API.

sp_Report_Flexible: A dynamic SQL procedure that accepts optional parameters for Date Range, Service Type, and Grouping Criteria (Day, Driver, Service), satisfying complex reporting requirements.

3.2 Semantic Constraints (Triggers)

We utilize triggers to enforce business logic that simple CHECK constraints cannot handle (Pre-commit validation).

trg_CheckRating: Fires AFTER INSERT on the Review table. It inspects the INSERTED pseudo-table and, if the rating falls outside the 1-5 range, raises an error and rolls back the transaction. This ensures invalid data is never committed.

3.3 Transactions & GDPR Compliance

The system supports the "Right to be Forgotten" via the sp_GDPR_ForgetMe procedure.

Mechanism: Uses explicit transaction management (BEGIN TRANSACTION ... COMMIT).

Logic: It permanently anonymizes user data (Firstname $\to$ 'Anon') and suspends any associated driver profiles in a single atomic operation, preventing orphan records.

4. Advanced Features

4.1 Real-Time Simulation (Long Polling)

The system simulates a WebSocket-like experience using optimized short-polling.

The status_api.php endpoint queries the RideSegment table to check the status of the current active leg.

The frontend polls this endpoint every 2 seconds, allowing the UI to update automatically (e.g., "Driver Arrived" -> "En Route") without requiring a page reload.

4.2 Automated Data Seeding

A custom Python script (data_generator_full.py) utilizing the Faker library was developed to populate the database for testing and demonstration.

Capabilities: Generates 200+ users, creates drivers/vehicles, and simulates 10,000+ historical rides.

Optimization: The script temporarily disables SQL constraints (NOCHECK) to maximize insertion speed and re-enables them (CHECK) upon completion.

5. Installation & Setup

Database Deployment:

Execute Master_schema.sql (Structure).

Execute Master_logic.sql (Procedures & Triggers).

Execute Master_seed.sql (Initial Data).

Web Server:

Deploy PHP files to an Apache or IIS server (e.g., XAMPP htdocs).

Ensure the sqlsrv PHP extension is enabled in php.ini.

Configuration:

Edit db_connect.php with your local SQL Server credentials.

Update index.php with a valid Google Maps JavaScript API Key.
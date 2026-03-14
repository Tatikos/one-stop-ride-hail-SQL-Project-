import pyodbc
from faker import Faker
import random
from datetime import datetime, timedelta
import time

# ==========================================
# ⚠️ CONFIGURATION
# ==========================================
SERVER   = 'mssql.cs.ucy.ac.cy'
DATABASE = 'gfotio01'
USERNAME = 'gfotio01'
PASSWORD = 'RU38sqLZ'
# ==========================================

print("🚀 Initializing OSRH Full Data Generator...")

conn_str = (
    f'DRIVER={{ODBC Driver 17 for SQL Server}};'
    f'SERVER={SERVER};DATABASE={DATABASE};'
    f'UID={USERNAME};PWD={PASSWORD};'
    f'TrustServerCertificate=yes;'
)

try:
    conn = pyodbc.connect(conn_str)
    cursor = conn.cursor()
except Exception as e:
    print(f"❌ Connection Failed: {e}")
    exit()

fake = Faker()

# ---------------------------------------------------------
# PHASE 0: DISABLE CONSTRAINTS (Bonus Point for this!)
# ---------------------------------------------------------
print("⚡ Disabling Constraints for performance (Bonus Point)...")
tables = ['Ride', 'RideSegment', 'Payment', 'Review', 'Vehicle', 'Driver_Profile', 'User_Role']
for t in tables:
    try:
        cursor.execute(f"ALTER TABLE {t} NOCHECK CONSTRAINT ALL")
    except:
        pass 
conn.commit()

# ---------------------------------------------------------
# PHASE 1: GENERATE USERS (PASSENGERS)
# ---------------------------------------------------------
NUM_USERS = 200 
print(f"👤 Generating {NUM_USERS} Users...")

new_user_ids = []
for i in range(NUM_USERS):
    fname = fake.first_name()
    lname = fake.last_name()
    email = f"{fname}.{lname}.{random.randint(1000,9999)}@example.com"
    
    cursor.execute("""
        INSERT INTO [User] (Firstname, Lastname, Email, PasswordHash, Dateofbirth, Address)
        VALUES (?, ?, ?, 'hash_placeholder', '1995-05-20', 'Nicosia, CY')
    """, fname, lname, email)
    
    if i % 50 == 0: conn.commit()

conn.commit()
print("✅ Users Created.")

cursor.execute("SELECT UserID FROM [User]")
all_users = [row[0] for row in cursor.fetchall()]

# ---------------------------------------------------------
# PHASE 2: GENERATE DRIVERS & VEHICLES (UPDATED SCHEMA)
# ---------------------------------------------------------
NUM_DRIVERS = 20 
print(f"🚖 Promoting {NUM_DRIVERS} Users to Drivers & Assigning Vehicles...")

driver_candidates = random.sample(all_users, NUM_DRIVERS)
active_drivers = [] 

cursor.execute("SELECT GeofenceID FROM Geofence")
zones = [row[0] for row in cursor.fetchall()]
if not zones: zones = [1]

for uid in driver_candidates:
    # 1. Create Driver Profile (No Zone here anymore)
    cursor.execute("""
        INSERT INTO Driver_Profile (UserID, Status, IsAutonomous, IsAvailable, WalletBalance)
        VALUES (?, 'Active', 0, 1, 0.00)
    """, uid)
    
    did = cursor.execute("SELECT @@IDENTITY").fetchval()
    
    # 2. Create Vehicle (Zone goes HERE now)
    plate = f"K{fake.random_uppercase_letter()}{fake.random_uppercase_letter()}-{random.randint(100,999)}"
    model = random.choice(['Toyota Prius', 'Honda Civic', 'Ford Transit', 'Tesla Model 3'])
    service_type = random.randint(1, 3)
    vehicle_zone = random.choice(zones) # Assign vehicle to a zone
    
    cursor.execute("""
        INSERT INTO Vehicle (Owner_DriverID, Home_GeofenceID, RegistrationPlate, Model, Type, Service_type_id, Status)
        VALUES (?, ?, ?, ?, 'Sedan', ?, 'Active')
    """, did, vehicle_zone, plate, model, service_type)
    
    vid = cursor.execute("SELECT @@IDENTITY").fetchval()
    
    active_drivers.append({'did': did, 'vid': vid})

conn.commit()
print("✅ Drivers & Vehicles Ready.")

# ---------------------------------------------------------
# PHASE 3: GENERATE RIDE HISTORY
# ---------------------------------------------------------
TARGET_RIDES = 10000
BATCH_SIZE = 500

print(f"📜 Generating {TARGET_RIDES} Historical Rides...")
start_timer = time.time()

for i in range(TARGET_RIDES):
    passenger_id = random.choice(all_users)
    driver_data = random.choice(active_drivers) 
    zone_id = random.choice(zones)
    service = random.randint(1, 3)
    
    t1 = fake.date_time_between(start_date='-1y', end_date='now')
    t2 = t1 + timedelta(minutes=random.randint(10, 50))
    dist = round(random.uniform(2.0, 30.0), 2)
    cost = round(5.0 + (dist * 1.5), 2)
    
    try:
        cursor.execute("""
            INSERT INTO Ride (Passenger_UserID, Service_type_id, Start_Lat, Start_Lon, End_Lat, End_Lon, 
                              Requested_time, Start_time, End_time, Status, EstimatedDistance_km, Final_Cost)
            VALUES (?, ?, 35.1, 33.3, 35.2, 33.4, ?, ?, ?, 'Completed', ?, ?)
        """, passenger_id, service, t1, t1, t2, dist, cost)
        
        rid = cursor.execute("SELECT @@IDENTITY").fetchval()
        
        cursor.execute("""
            INSERT INTO RideSegment (RideID, SequenceOrder, GeofenceID, DriverID, VehicleID, 
                                     Status, Passenger_Started, Driver_Started, EstimatedCost)
            VALUES (?, 1, ?, ?, ?, 'Completed', 1, 1, ?)
        """, rid, zone_id, driver_data['did'], driver_data['vid'], cost)
        
        cursor.execute("""
            INSERT INTO Payment (Ride_ID, Amount, PlatformFee, PaymentMethod, PaymentStatus, Date)
            VALUES (?, ?, ?, 'Credit Card', 'Paid', ?)
        """, rid, cost, cost*0.15, t2)
        
        if i % BATCH_SIZE == 0:
            conn.commit()
            print(f"    ... {i} rides generated")
            time.sleep(0.5) 

    except Exception as e:
        print(f"Skipping row {i}: {e}")
        conn.rollback()

conn.commit()
print(f"✅ History Generated in {round(time.time() - start_timer, 2)}s")

print("🔒 Re-enabling Constraints...")
for t in tables:
    try:
        cursor.execute(f"ALTER TABLE {t} WITH CHECK CHECK CONSTRAINT ALL")
    except:
        pass
conn.commit()

print("🎉 DATABASE FULLY POPULATED!")
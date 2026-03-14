import random
from faker import Faker
from datetime import datetime, timedelta

# Ρυθμίσεις
NUM_USERS = 1000
NUM_DRIVERS = 50
NUM_RIDES = 5000
DELIMITER = "|" 

fake = Faker()

print("🚀 Generating .dat files for Import/Stress Testing...")

# 1. USERS.DAT
# Format: Firstname|Lastname|Email|PasswordHash|DOB|Gender|Address
users = []
print(f"Generating {NUM_USERS} Users...")
with open("users.dat", "w", encoding="utf-8") as f:
    for i in range(NUM_USERS):
        fname = fake.first_name()
        lname = fake.last_name()
        email = f"{fname}.{lname}.{i}@example.com"
        pwd = "hashed_secret_password"
        dob = fake.date_of_birth(minimum_age=18, maximum_age=90).strftime('%Y-%m-%d')
        gender = random.choice(['Male', 'Female', 'Non-Binary'])
        addr = fake.address().replace("\n", ", ")
        
        line = f"{fname}{DELIMITER}{lname}{DELIMITER}{email}{DELIMITER}{pwd}{DELIMITER}{dob}{DELIMITER}{gender}{DELIMITER}{addr}\n"
        f.write(line)
        users.append(i + 1) # Keep ID tracking

# 2. DRIVERS.DAT
# Format: UserID|Status|IsAutonomous|WalletBalance
print(f"Generating {NUM_DRIVERS} Drivers...")
drivers = []
with open("drivers.dat", "w", encoding="utf-8") as f:
    # Promote random users to drivers
    driver_user_ids = random.sample(users, NUM_DRIVERS)
    for uid in driver_user_ids:
        status = 'Active'
        is_auto = 0
        wallet = round(random.uniform(0, 500), 2)
        
        line = f"{uid}{DELIMITER}{status}{DELIMITER}{is_auto}{DELIMITER}{wallet}\n"
        f.write(line)
        drivers.append(uid) # Using UserID as DriverID proxy for simple generation

# 3. RIDES.DAT
# Format: PassengerID|ServiceType|StartLat|StartLon|EndLat|EndLon|Cost|Status|Date
print(f"Generating {NUM_RIDES} Rides...")
with open("rides.dat", "w", encoding="utf-8") as f:
    for i in range(NUM_RIDES):
        pid = random.choice(users)
        svc = random.randint(1, 4)
        
        # Coordinates around Cyprus
        slat = round(random.uniform(34.6, 35.3), 6)
        slon = round(random.uniform(32.9, 34.0), 6)
        elat = round(random.uniform(34.6, 35.3), 6)
        elon = round(random.uniform(32.9, 34.0), 6)
        
        cost = round(random.uniform(5.0, 50.0), 2)
        status = 'Completed'
        date = fake.date_time_between(start_date='-1y', end_date='now').strftime('%Y-%m-%d %H:%M:%S')
        
        line = f"{pid}{DELIMITER}{svc}{DELIMITER}{slat}{DELIMITER}{slon}{DELIMITER}{elat}{DELIMITER}{elon}{DELIMITER}{cost}{DELIMITER}{status}{DELIMITER}{date}\n"
        f.write(line)

print("✅ Files created: users.dat, drivers.dat, rides.dat")
print(f"ℹ️  Use Bulk Insert with Delimiter '{DELIMITER}' to import.")
# One-Stop Ride-Hail (OSRH)

A full-stack ride-hailing platform built with **PHP** and **Microsoft SQL Server**, featuring real-time ride tracking, in-ride messaging, dynamic fare calculation, and a comprehensive administrative reporting suite.

## Features

### Role-Based Access Control

| Role | Capabilities |
|------|-------------|
| **Passenger** | Book rides, track drivers in real-time, message drivers, view ride history |
| **Driver** | Manage ride requests, update trip status, track earnings |
| **Admin** | Access system-wide analytics, manage users and driver accounts |
| **Operator** | Scoped admin access with reduced permissions |

### Core Functionality

- **Real-Time Ride Tracking** — Live visual interface for monitoring trip progress from dispatch to completion.
- **In-Ride Messaging** — Bidirectional chat between passengers and drivers for the duration of a trip.
- **Dynamic Pricing Engine** — Automated fare calculation based on distance traveled and selected service tier.
- **Database-Driven Business Logic** — Stored procedures and triggers enforce data integrity and encapsulate core workflows.
- **Test Data Generation** — Bundled Python scripts for seeding the database with realistic, high-volume test data.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, JavaScript, Bootstrap 5 |
| Backend | PHP 8.x |
| Database | Microsoft SQL Server (T-SQL) |
| Scripting | Python 3 |

## Database Architecture

The schema is organized into four layered SQL scripts:

- **`Master_schema.sql`** — Table definitions and constraints
- **`Master_logic.sql`** — Stored procedures and triggers
- **`Master_seed.sql`** — Initial reference and seed data
- **`Master_performance.sql`** — Index definitions and query optimization

## Getting Started

### Prerequisites

- PHP 8.x with the `sqlsrv` extension
- Microsoft SQL Server 2019+
- Python 3.x (optional, for data generation)

### Setup

1. Execute the SQL scripts against your server in order: `schema` → `logic` → `seed` → `performance`.
2. Configure your database connection in `php/db_connect.php`.
3. Serve the `php/` directory via your preferred web server (Apache, Nginx, or PHP's built-in server).
4. Optionally, run `scripts/data_generator.py` to populate the database with test data.

## License

This project is released for educational and demonstration purposes.
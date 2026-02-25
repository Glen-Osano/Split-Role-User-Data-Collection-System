<div align="center">

# 🗄️ VaultForm
### Split-Role User Data Collection System
*A PHP + MySQL project demonstrating separated admin and user interfaces with SQL JOINs and Virtual Views*

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?style=flat-square&logo=mysql&logoColor=white)
![XAMPP](https://img.shields.io/badge/XAMPP-Compatible-FB7A24?style=flat-square&logo=apache&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)

</div>

---

## 📖 What is VaultForm?

**VaultForm** is a lightweight PHP web application that demonstrates a clean separation between what an **administrator** enters into a system and what an **end user** fills in themselves — without duplicating records.

The core concept:
- The **Admin** creates a user account (Name + Email) via a private dashboard
- The **User** visits a separate portal, looks up their account by email, and submits their own Date of Birth
- A **SQL VIEW** (`user_report`) joins both tables together so reports always show the complete, unified record

This project is ideal for learning:
- Role-based interface separation in PHP
- SQL `LEFT JOIN` with virtual views
- Safe MySQL inserts vs updates (preventing duplicate rows)
- Clean UI/UX design for different audiences

---

## 🗂️ Project Structure

```
userdb/
├── db.php          # Database connection, table creation, VIEW definition
├── admin.php       # Admin dashboard — add/delete users, view all records
├── user.php        # User portal — email lookup + DOB submission
├── report.php      # Admin reports — full data with filters and stats
└── README.md       # This file
```

---

## 🗃️ Database Architecture

```
┌─────────────────┐          ┌──────────────────────┐
│     users       │          │    user_inputs       │
│─────────────────│          │──────────────────────│
│ id   (PK, AUTO) │◄────────►│ user_id (PK, FK)     │
│ name            │  1 : 1   │ dob                  │
│ email           │          │ updated_at           │
│ created_at      │          └──────────────────────┘
└─────────────────┘
         │                            │
         └────────────┬───────────────┘
                      ▼
           ┌─────────────────────┐
           │    user_report      │  ← SQL VIEW (LEFT JOIN)
           │─────────────────────│
           │ id, name, email     │  ← from users (admin fills)
           │ dob, age, status    │  ← from user_inputs (user fills)
           │ created_at          │
           └─────────────────────┘
```

### SQL VIEW Definition

```sql
CREATE VIEW user_report AS
SELECT
    u.id,
    u.name,
    u.email,
    ui.dob,
    CASE WHEN ui.dob IS NOT NULL
         THEN TIMESTAMPDIFF(YEAR, ui.dob, CURDATE())
         ELSE NULL END AS age,
    CASE WHEN ui.dob IS NOT NULL THEN 'Complete' ELSE 'Pending' END AS status,
    u.created_at,
    ui.updated_at AS dob_updated_at
FROM users u
LEFT JOIN user_inputs ui ON u.id = ui.user_id;
```

> **Why LEFT JOIN?** So users who haven't submitted their DOB yet still appear in reports (as NULL), instead of being hidden by an INNER JOIN.

---

## ⚙️ Installation & Setup (XAMPP)

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) installed
- Apache and MySQL running in the XAMPP Control Panel

### Steps

**1. Clone or download this repository**
```bash
git clone https://github.com/yourusername/vaultform.git
```

**2. Move the project into XAMPP's web root**
```
C:\xampp\htdocs\userdb\
```

**3. Start XAMPP services**
- Open XAMPP Control Panel
- Click **Start** next to **Apache**
- Click **Start** next to **MySQL**

**4. Open the app in your browser**

The database and all tables are created automatically on first load.

```
http://localhost/userdb/admin.php   ← Admin Dashboard
http://localhost/userdb/user.php    ← User Portal
http://localhost/userdb/report.php  ← Reports
```

> No manual SQL needed. `db.php` handles database creation, table setup, and VIEW definition automatically.

---

## 🖥️ Interfaces

### 👨‍💼 Admin Dashboard (`admin.php`)
- Add new users (Name + Email only — DOB intentionally left blank)
- View all users with DOB completion status
- Delete users
- Navigate to Reports

### 👤 User Portal (`user.php`)
- User searches for their account by email
- Views their profile (Name, Email, DOB, Age, Status)
- Submits or updates their Date of Birth
- Only touches `user_inputs` — never creates duplicate records

### 📊 Reports (`report.php`)
- Full report querying the `user_report` SQL VIEW
- DOB completion progress bar
- Filter by: All / DOB Complete / DOB Pending
- Search by name or email
- Shows the live SQL query being executed

---

## 🔑 Who Fills What?

| Field | Filled By | Interface | Table |
|---|---|---|---|
| Name | Administrator | `admin.php` | `users` |
| Email | Administrator | `admin.php` | `users` |
| Date of Birth | End User | `user.php` | `user_inputs` |
| Age | Auto-calculated | VIEW | `user_report` |
| Status | Auto-calculated | VIEW | `user_report` |

---

## 🐛 Common Issues

| Problem | Fix |
|---|---|
| Blank page / connection error | Make sure MySQL is running in XAMPP |
| Duplicate records | Ensure you are using the fixed `user.php` — the user portal only updates `user_inputs`, never inserts into `users` |
| DOB not saving | Check that the `user_inputs` row exists for the user (admin must add the user first) |
| VIEW not found | Visit `admin.php` once to trigger `db.php` which recreates the VIEW |

---

## 🚀 Future Improvements

- [ ] Add authentication (admin login, user token-based links)
- [ ] Email unique constraint to prevent duplicate accounts
- [ ] Export reports to CSV / Excel
- [ ] Pagination for large datasets
- [ ] Add more user-input fields (phone, address, etc.)
- [ ] REST API endpoint for mobile clients

---

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

---

<div align="center">
  Built with PHP, MySQL & XAMPP &nbsp;·&nbsp; Designed for learning role-based data collection
</div>

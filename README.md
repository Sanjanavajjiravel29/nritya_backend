# NrityaHub Backend API & Database

NrityaHub is a dance academy management system with role-based access for:
- **Administrator**: Manages batches, creates user accounts (instructors, students, staff), reviews finances/salary payouts, views overall academy metrics.
- **Instructor**: Views assigned batches and student rosters, marks student attendance, creates student accounts, reviews homework submissions with evaluation checklists & feedback.
- **Student**: Views class schedule, submits practice recordings/homework, tracks attendance history, views instructor evaluation feedback & checklists, tracks tuition fees & invoices.

---

## 🔑 Default Test Accounts

All accounts below have password: **`Password123!`**

| Role | Name | Username | Email | ID |
|---|---|---|---|---|
| **Admin** | Dr. Rukmini Devi | `rukmini.admin` | `rukmini.admin@nritahub.com` | `10` |
| **Instructor** | Meera Iyer | `meera.iyer` | `meera.iyer@nritahub.com` | `11` |
| **Instructor** | Siddharth Menon | `siddharth.menon` | `siddharth.menon@nritahub.com` | `12` |
| **Instructor** | Lakshmi Narayanan | `lakshmi.narayanan` | `lakshmi.narayanan@nritahub.com` | `4` |
| **Student** | Ananya Rao | `ananya.rao` | `ananya.rao@nritahub.com` | `20` |
| **Student** | Arjun Varma | `arjun.varma` | `arjun.varma@nritahub.com` | `21` |
| **Student** | Diya Menon | `diya.menon` | `diya.menon@nritahub.com` | `22` |
| **Student** | Kavya Shah | `kavya.shah` | `kavya.shah@nritahub.com` | `23` |
| **Student** | Rohan Patel | `rohan.patel` | `rohan.patel@nritahub.com` | `24` |
| **Student** | Aditi Kulkarni | `aditi.kulkarni` | `aditi.kulkarni@nritahub.com` | `25` |

---

## 📁 Directory Structure & Endpoints

```
nrityahub_backend/
├── config/
│   ├── config.php                      # Database connection
│   └── helpers.php                     # CORS, unified request parsing, response formatting
├── auth/
│   ├── login.php                       # Role-based login (Admin, Instructor, Student)
│   ├── signup.php                      # Public student registration
│   ├── forgotpasswd.php                # Email check
│   └── resetpasswd.php                 # Password update
├── users/
│   ├── create_user.php                 # Admin creates any role; Instructor creates students
│   ├── list_users.php                  # Filter by ?role=student / ?role=instructor / ?role=admin
│   ├── get_user.php                    # Fetch user with batch, attendance, and submission counts
│   ├── update_user.php                 # Edit user info, salary, role, status
│   └── delete_user.php                 # Delete or deactivate account
├── batches/
│   ├── list_batches.php                # List batches with enrollment count & schedule
│   ├── create_batch.php                # Admin creates batch
│   ├── get_batch.php                   # Get single batch with full student roster
│   ├── update_batch.php                # Update batch capacity, studio, days, tuition
│   ├── delete_batch.php                # Remove batch
│   ├── assign_student.php              # Enroll student in batch
│   └── remove_student.php              # Remove student from batch
├── attendance/
│   ├── mark_attendance.php             # Instructor marks attendance (bulk array or single)
│   ├── get_batch_attendance.php        # Get attendance sheet for a date
│   └── get_student_attendance.php      # Student attendance summary & percentage history
├── homework/
│   ├── create_homework.php             # Instructor creates homework task
│   ├── list_homework.php               # List assignments for a batch or student
│   ├── submit_homework.php             # Student submits practice recording & notes
│   ├── list_submissions.php            # Instructor lists submissions to review
│   ├── get_submission.php              # Get submission, video, checklist, instructor review
│   └── review_submission.php           # Instructor publishes evaluation checklist & feedback
├── fees/
│   ├── list_fees.php                   # List student fees & staff salary payouts
│   ├── create_fee.php                  # Admin creates student fee or staff payout record
│   └── update_fee_status.php           # Update payment status (Paid, Pending, Overdue)
├── admin/
│   └── dashboard_stats.php             # Admin overview numbers, fees progress, notices
├── announcements/
│   ├── list_announcements.php          # List academy announcements
│   └── create_announcement.php         # Admin publishes recital/workshop notice
├── profile/
│   ├── fetch_profile.php               # Fetch user profile
│   └── edit_profile.php                # Edit profile bio, photo, details
├── uploads/
│   ├── profiles/                       # User profile images
│   ├── submissions/                    # Student homework recordings
│   └── references/                     # Instructor demo videos & notes
└── postman/
    ├── collections/
    │   └── NrityaHub_API_Collection.json   # Ready-to-import Postman Collection
    └── environments/
        └── NrityaHub_Local_Environment.json # Ready-to-import Postman Environment
```

---

## 📮 Postman Collection

Import the following file directly into Postman:
`c:\xampp\htdocs\nrityahub_backend\postman\collections\NrityaHub_API_Collection.json`

Base URL: `http://localhost/nrityahub_backend`

# 🃏 Purble Pairs

A browser-based memory card matching game built with PHP, MySQL, and Tailwind CSS. Players flip cards to find matching pairs across two game modes — Classic and Endless — with a live leaderboard and a role-restricted admin panel.

> **Team Project** — ITEC106 Web System and Technologies 2 · Cavite State University
> My role: concept origination, feature definition, Figma co-design, and beta testing.

---

## 🔗 Live Demo

[purble-pairs.gt.tc](https://purble-pairs.gt.tc)

---

## 🎮 Game Modes

**Classic Mode** — Choose a difficulty (Beginner, Intermediate, Advanced, or Asian) and match all card pairs before the countdown timer runs out. Rankings are based on fewest moves, with completion time as a tiebreaker. Only each player's best score per difficulty is stored.

**Endless Mode** — No difficulty selection needed. The session starts at Beginner and automatically progresses through 7 consecutive rounds up to Asian difficulty. Players accumulate points throughout the session and can opt out early. Final score and highest difficulty reached are recorded on the Endless leaderboard.

---

## ⚙️ Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP (native, no framework) |
| Database | MySQL |
| Frontend | HTML, Tailwind CSS (CDN), Vanilla JavaScript |
| Auth | PHP Sessions + custom columnar transposition cipher |
| AJAX | Fetch API → `action.php` |

---

## 📁 Project Structure

```
purble-pairs/
├── admin/              # Role-restricted admin panel
│   ├── auth_guard.php  # Redirects non-admins
│   ├── index.php       # Dashboard
│   ├── users.php       # User management
│   ├── leaderboard.php # Classic leaderboard management
│   ├── endless.php     # Endless leaderboard management
│   └── layout.php      # Shared admin UI
├── includes/
│   ├── db.php          # Database connection (reads from .env)
│   ├── encrypt.php     # Custom encryption library
│   └── cards.php       # Card set definitions
├── assets/
│   └── tailwind.js     # Tailwind CSS (local)
├── auth.php            # Login & signup (PRG pattern)
├── index.php           # Game mode selection
├── game.php            # Classic Mode
├── endless.php         # Endless Mode
├── action.php          # AJAX handler for all game actions
├── leaderboard.php     # Public leaderboard
├── logout.php          # Session destroy
├── setup.sql           # Database schema + seed
└── .env                # Environment variables (not committed)
```

---

## 🗄️ Database Schema

```sql
users               -- Registered player accounts (username, encrypted password, is_admin)
leaderboard         -- Classic Mode results (user_id, difficulty, moves, time)
endless_leaderboard -- Endless Mode results (user_id, score, reached_difficulty)
```

Foreign keys on `user_id` cascade on delete to keep leaderboard data consistent.

---

## 🚀 Local Setup

**Requirements:** PHP 8+, MySQL / MariaDB, XAMPP or similar local server

1. Clone the repository:
```bash
git clone https://github.com/Ash-8448/Purble-Pairs.git
```

2. Import the database schema:
   - Open phpMyAdmin at `localhost/phpmyadmin`
   - Run the contents of `setup.sql`

3. Create a `.env` file in the project root:
```
ENCRYPTION_KEY=PURBLEAC
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=purble_pairs
```

4. Place the project folder inside your web server's root (e.g. `htdocs/` for XAMPP) and open `localhost/Purble-Pairs-main` in your browser.

5. Log in using the seeded admin account or register a new player account.

> **Note:** Change the admin password after first login.

---

## 🔐 Security Notes

- Passwords are encrypted using a custom columnar transposition + Caesar cipher, with the key loaded from `.env`
- The `.env` file is excluded from version control via `.gitignore`
- Admin pages are protected by `auth_guard.php` on every request
- All database queries use prepared statements (MySQLi)
- Admins can view users and manage leaderboards but cannot edit or delete player accounts

---

## 👥 Team

| Name | Role |
|---|---|
| Lee Johnrich H. Ramirez | Lead Developer |
| Xyzine G. Austria | UI/UX Designer, Documentation & Beta Tester |
| Jane Ashley R. Candelaria | Concept & Feature Definition, Figma Co-Designer, Beta Tester |

---

## 📄 License

This project was developed as an academic requirement for ITEC106 — Web System and Technologies 2 at Cavite State University. Not licensed for commercial use.

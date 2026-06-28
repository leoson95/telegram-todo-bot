<div align="center">

# 📝 TodoBot - Task and Shopping Manager Bot
A clean, powerful, and personal Telegram bot to manage tasks, shopping lists, and scheduled reminders.

[![PHP Version](https://img.shields.io/badge/php-%5E8.0-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Railway](https://img.shields.io/badge/Railway-Deploy-purple.svg)](https://railway.app/)

[🇮🇷 مستندات فارسی](README.md)

</div>

---

## ✨ Features

- 📌 **Clean and Static UI**: Operates via inline messages and edits the main message, keeping the chat history uncluttered.
- 📂 **Dynamic Categories**: Comes with 6 default categories with the ability to dynamically add or delete your own custom categories.
- ⏰ **Scheduled Reminders**: Set specific times and repetition intervals (every day, every other day, weekly).
- ✅ **Smart Buttons**: Includes `Done` and `Seen` buttons for reminder messages sent via Cron Job.
- 🔄 **Auto-delete Temporary Messages**: Automatically cleans up temporary prompts and menus after an action is completed.
- 🔒 **Webhook Security**: Supports Telegram's Webhook Secret Token to prevent spoofed/fake requests.
- ⚡️ **Modular Architecture**: Clean, separated code files for configuration, functions, and main logic.

---

## 🔧 Technology Stack

- **PHP 8+** utilizing Webhooks for fast message reception.
- **SQLite**: Lightweight, fast, and requires no separate database server setup. The schema is automatically created on the first run.
- **No Heavy Frameworks**: Pure vanilla PHP optimized to run quickly on servers with limited resources.

---

## 🚀 Deployment and Setup

### 1. Local Server Deployment
1. Clone the repository:
   ```bash
   git clone https://github.com/leoson95/telegram-todo-bot.git
   cd telegram-todo-bot
   ```
2. Create the environment configuration file:
   ```bash
   cp .env.example .env
   ```
   *Then set the values for `BOT_TOKEN`, `DB_PATH`, and `WEBHOOK_SECRET`.*
3. Set your Telegram Bot Webhook to a valid domain pointing to `index.php` and make sure to pass the `secret_token` parameter.
4. To enable reminders, set up a Cron Job for `cron.php` to run every 1 minute:
   ```bash
   * * * * * php /path/to/telegram-todo-bot/cron.php
   ```

### 2. Railway Deployment (Recommended)

This project is optimized to easily run on the Free Tier of [Railway](https://railway.app/).

1. Fork the repository.
2. Create a new GitHub project in Railway.
3. In the Variables tab, set the `BOT_TOKEN` and define a secure random string for `WEBHOOK_SECRET`. The `DB_PATH` defaults to `/app/data/todo.db`.
4. Copy the public URL provided by Railway and set it as your Telegram Bot Webhook (remember to use the `secret_token` parameter).
5. Add the URL to `cron.php` in a free cron service like **[cron-job.org](https://cron-job.org)** to be triggered every minute.

---

## 🔄 Project Structure

```
telegram-todo-bot/
├── index.php          # Entry point, receives Webhook requests and main logic
├── cron.php           # Scheduled reminder engine (runs every minute)
├── config.php         # Environment variables and secure DB connection
├── functions.php      # Telegram API functions and display logic
├── .env.example       # Example environment variables
└── README.md
```

---

## 🚀 How to Use

1. Send `/start` to the bot in Telegram.
2. Send any text message to register it as a new task.
3. Choose a category from the inline menu (you can also manage categories from the main menu).
4. If you need a reminder, specify the time and repetition interval using the provided buttons.

---

## 📝 Roadmap

- [x] Dynamic and customizable categories
- [ ] Weekly reports and statistics
- [ ] Integration with AI tools for smart task suggestions

---

**Made with ❤️ for simple, effective personal management**

# CompWatcher

**CompWatcher** is a Laravel-based web application that automates raid attendance and class composition analysis for our World of Warcraft Classic guild.  
It integrates with the Warcraft Logs, Blizzard, and Discord APIs to collect data, perform analysis, and post insights directly to Discord.

## Features

- 🔗 OAuth2 login via Discord
- 📊 Automated raid attendance tracking
- 🧙 Class composition breakdowns
- 🧠 Trend analysis across raid weeks
- 🧵 Redis caching for fast API response parsing
- 🤖 Discord bot that posts updates to designated channels
- 🧪 TDD approach with full unit and integration test coverage

## Technologies

- Laravel 11 + Sail (Docker-based dev environment)
- React + Tailwind (officer dashboard)
- MySQL (with DigitalOcean-hosted production DB)
- Redis (for caching API responses)
- Discord API (bot and OAuth)
- Warcraft Logs + Blizzard APIs
# Web Scanner — Web Security & Performance Monitoring Platform

A comprehensive web application for scanning, analysing, and continuously monitoring websites for security vulnerabilities, malicious content, and performance issues.

---

## Table of Contents

1. [Project Description](#project-description)
2. [Features](#features)
3. [Tech Stack](#tech-stack)
4. [Project Architecture](#project-architecture)
5. [Installation](#installation)
6. [Usage](#usage)
7. [Environment Variables](#environment-variables)
8. [API Endpoints](#api-endpoints)
9. [Future Improvements](#future-improvements)
10. [Author](#author)

---

## Project Description

Web Scanner addresses the growing need for accessible, automated website security auditing. It allows security analysts, developers, and administrators to:

- **Detect security vulnerabilities** such as backdoors, remote code execution (RCE) paths, SQL injection, and XSS patterns embedded in a target website.
- **Identify malicious or illegal content** including gambling-related material and hidden elements used for cloaking.
- **Monitor websites continuously** for uptime, SSL certificate validity, open ports, and HTTP security headers.
- **Track performance metrics** over time and generate PDF reports for audits.
- **Receive real-time alerts** through Telegram when critical threats are detected.

The platform is built on Laravel 11 and combines server-side scanning services with a modern React + Tailwind CSS front-end, backed by Pusher WebSockets for live updates.

---

## Features

### Security Scanning
- **Backdoor Detection** — Identifies RCE, SQL injection, XSS, obfuscated code, and exposed credentials using a weighted risk-scoring system.
- **Gambling / Illegal Content Detection** — Detects betting keywords, e-wallet payment patterns (Dana, OVO, GoPay), hidden DOM elements, and suspicious TLDs.
- **Enhanced Threat Analysis** — Analyses JavaScript patterns, redirect chains, network requests, and registration form fields for suspicious behaviour.

### Website Monitoring
- **Uptime / Downtime Tracking** — Continuously polls registered websites and records availability history.
- **SSL Certificate Validation** — Checks certificate validity and expiry dates.
- **Security Headers Inspection** — Evaluates HTTP response headers against best-practice recommendations.
- **Open Port Scanning** — Detects open ports on monitored hosts.

### Performance Monitoring
- **Response Time Tracking** — Records historical HTTP response times.
- **SEO & Performance Metrics** — Captures page-level performance indicators.
- **Dashboard Visualisations** — Interactive charts powered by Chart.js.

### Reporting & Alerts
- **PDF Report Generation** — Download scan history and security reports as PDF documents.
- **Telegram Notifications** — Instant alerts for critical vulnerabilities via a Telegram bot.
- **Email Notifications** — Critical vulnerability alerts delivered by mail.
- **Real-time Updates** — Scan results and website status pushed live via Pusher WebSockets.

### Administration & Security
- **Login Attempt Logging** — Records all authentication attempts with IP addresses.
- **IP Blocking** — Block malicious IPs directly from the security dashboard.
- **Rate Limiting** — Scanning endpoints are throttled (10 requests per minute) to prevent abuse.
- **Laravel Telescope** — Built-in debugging and request profiling in development.

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Language** | PHP 8.2+ |
| **Framework** | Laravel 11 |
| **Front-end** | React 19, Tailwind CSS 4, Alpine.js 3 |
| **Build Tool** | Vite 5 |
| **Reactive UI** | Livewire 3.5 |
| **Data Visualisation** | Chart.js 4 |
| **UI Components** | shadcn/ui |
| **Real-time** | Pusher JS + Laravel Echo |
| **HTTP Client** | Guzzle HTTP |
| **HTML Parsing** | Symfony DOM Crawler |
| **Web Crawling** | Spatie Crawler |
| **SSL Checking** | Spatie SSL Certificate |
| **PDF Generation** | Barryvdh DomPDF, React PDF Renderer |
| **AI Recommendations** | OpenAI PHP Client |
| **Notifications** | Telegram Bot API, Laravel Mail, Slack |
| **Caching** | Redis (Predis) |
| **App Server** | Laravel Octane + RoadRunner |
| **Error Tracking** | Sentry |
| **Testing** | PHPUnit |
| **Code Style** | Laravel Pint |

---

## Project Architecture

```
Web_Scanning/
├── app/
│   ├── Console/               # Artisan console commands
│   ├── Events/                # Broadcastable events (ScanCompleted, WebsiteUpdated)
│   ├── Exceptions/            # Custom exception handlers
│   ├── Http/
│   │   ├── Controllers/       # Request controllers (scanner, monitor, performance, security)
│   │   └── Middleware/        # Auth, enhanced security, scan validation
│   ├── Jobs/                  # Queued background jobs
│   ├── Mail/                  # Mailable classes (CriticalVulnerabilityAlert)
│   ├── Models/                # Eloquent models (Website, ScanResult, ScanHistory, PerformanceMetric, …)
│   ├── Notifications/         # Notification classes
│   ├── Providers/             # Service providers
│   ├── Rules/                 # Custom validation rules
│   └── Services/              # Core business logic
│       ├── BackdoorDetectionService.php
│       ├── GamblingDetectionService.php
│       ├── EnhancedDetectionService.php
│       ├── AIRecommendationService.php
│       └── …
├── bootstrap/                 # Framework bootstrapping
├── config/                    # Application configuration files
├── database/
│   ├── factories/             # Model factories for testing
│   ├── migrations/            # Database schema migrations
│   └── seeders/               # Database seeders
├── public/                    # Web root (index.php, compiled assets)
├── resources/
│   ├── css/                   # Tailwind CSS source
│   ├── js/                    # React components and Alpine.js scripts
│   └── views/                 # Blade templates
├── routes/
│   └── web.php                # All application routes
├── storage/                   # Logs, cache, uploaded files
├── tests/                     # PHPUnit feature and unit tests
├── .rr.yaml                   # RoadRunner application server config
├── composer.json              # PHP dependencies
├── package.json               # Node.js dependencies
├── vite.config.js             # Vite build configuration
└── phpunit.xml                # PHPUnit configuration
```

---

## Installation

### Prerequisites

- PHP 8.2 or higher
- Composer 2
- Node.js 20+ and npm 10+
- A database supported by Laravel (SQLite for local development, MySQL/PostgreSQL for production)
- Redis (optional — required for caching and queues in production)

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/yosuaaa22/Web_Scanning.git
cd Web_Scanning

# 2. Install PHP dependencies
composer install

# 3. Install Node.js dependencies
npm install

# 4. Copy the environment file and configure it
cp .env.example .env

# 5. Generate the application key
php artisan key:generate

# 6. Run database migrations
php artisan migrate

# 7. (Optional) Seed the database with sample data
php artisan db:seed

# 8. Build front-end assets
npm run build

# 9. Start the development server
php artisan serve
```

The application will be available at `http://localhost:8000`.

#### Using RoadRunner (optional, for production-like performance)

```bash
# Start the application with RoadRunner
php artisan octane:start --server=roadrunner
```

---

## Usage

1. **Register / Log in** — Navigate to `/login` and authenticate with your credentials.
2. **Run a scan** — From the dashboard, enter a target URL and click **Scan**. Results are displayed in real time.
3. **Review results** — Detailed backdoor and gambling detection results are available on their respective detail pages.
4. **Monitor websites** — Add websites to the monitoring list via `/monitor`. The application periodically checks uptime, SSL, headers, and open ports.
5. **View performance data** — The performance dashboard at `/performance` shows historical response times and SEO metrics.
6. **Download reports** — Export scan history or security reports as PDF files from `/scanner/history`.
7. **Manage security** — View login attempts and block suspicious IPs from `/security/login-attempts`.
8. **Configure Telegram alerts** — Set `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHAT_ID` in `.env`, then visit `/test-telegram` to verify the integration.

---

## Environment Variables

Create a `.env` file based on `.env.example` and populate the following variables:

### Application

| Variable | Description | Example |
|---|---|---|
| `APP_NAME` | Application display name | `Web Scanner` |
| `APP_ENV` | Environment | `local` / `production` |
| `APP_DEBUG` | Enable debug mode | `true` / `false` |
| `APP_KEY` | Encryption key (generated by `key:generate`) | |
| `APP_URL` | Full application URL | `http://localhost` |
| `APP_TIMEZONE` | Server timezone | `UTC` |

### Database

| Variable | Description | Example |
|---|---|---|
| `DB_CONNECTION` | Database driver | `sqlite` / `mysql` / `pgsql` |
| `DB_HOST` | Database host | `127.0.0.1` |
| `DB_PORT` | Database port | `3306` |
| `DB_DATABASE` | Database name or path | `database.sqlite` |
| `DB_USERNAME` | Database username | |
| `DB_PASSWORD` | Database password | |

### Telegram

| Variable | Description |
|---|---|
| `TELEGRAM_BOT_TOKEN` | Authentication token for your Telegram bot |
| `TELEGRAM_CHAT_ID` | Chat ID where notifications are sent |

### OpenAI (AI Recommendations)

| Variable | Description |
|---|---|
| `OPENAI_API_KEY` | OpenAI API key |
| `OPENAI_ORGANIZATION` | OpenAI organisation ID |
| `OPENAI_REQUEST_TIMEOUT` | Request timeout in seconds (default: `30`) |

### Pusher (Real-time)

| Variable | Description |
|---|---|
| `PUSHER_APP_ID` | Pusher application ID |
| `PUSHER_APP_KEY` | Pusher application key |
| `PUSHER_APP_SECRET` | Pusher application secret |
| `PUSHER_APP_CLUSTER` | Pusher cluster region (e.g. `ap1`) |
| `VITE_PUSHER_APP_KEY` | Pusher key exposed to the front-end build |
| `VITE_PUSHER_APP_CLUSTER` | Pusher cluster exposed to the front-end build |

### Mail

| Variable | Description |
|---|---|
| `MAIL_MAILER` | Mail driver (`smtp`, `sendmail`, `log`) |
| `MAIL_HOST` | SMTP host |
| `MAIL_PORT` | SMTP port |
| `MAIL_USERNAME` | SMTP username |
| `MAIL_PASSWORD` | SMTP password |
| `MAIL_FROM_ADDRESS` | Sender address |

### Slack (optional)

| Variable | Description |
|---|---|
| `SLACK_BOT_USER_OAUTH_TOKEN` | Slack bot OAuth token |
| `SLACK_BOT_USER_DEFAULT_CHANNEL` | Default Slack channel |

### Cache & Queue

| Variable | Description |
|---|---|
| `CACHE_STORE` | Cache driver (`database`, `redis`, `file`) |
| `QUEUE_CONNECTION` | Queue driver (`sync`, `database`, `redis`) |
| `SESSION_DRIVER` | Session driver |

---

## API Endpoints

All routes are web routes protected by the `auth` middleware unless noted.

### Authentication

| Method | Path | Description |
|---|---|---|
| `GET` | `/login` | Show login form |
| `POST` | `/login` | Process login |
| `POST` | `/logout` | Log out |

### Security Scanner

| Method | Path | Description |
|---|---|---|
| `GET` | `/` | Dashboard |
| `POST` | `/scanner/scan` | Run a security scan (throttled: 10/min) |
| `GET` | `/scanner/result` | View scan results |
| `GET` | `/scanner/history` | View scan history |
| `GET` | `/scanner/history/download` | Download scan history as PDF |
| `GET` | `/scanner/backdoor-details` | Backdoor detection detail view |
| `GET` | `/scanner/gambling-details` | Gambling content detection detail view |

### Performance Monitoring

| Method | Path | Description |
|---|---|---|
| `GET` | `/performance/` | Performance overview |
| `GET` | `/performance/dashboard` | Detailed performance dashboard |
| `GET` | `/performance/api` | Performance metrics data (JSON) |

### Website Monitoring

| Method | Path | Description |
|---|---|---|
| `GET` | `/monitor/` | Monitoring dashboard |
| `POST` | `/monitor/websites` | Add a website to monitor |
| `GET` | `/monitor/websites/{website}/status` | Get cached website status |
| `POST` | `/monitor/websites/{website}/check-status` | Trigger an immediate status check |
| `GET` | `/monitor/websites/{website}/ssl-certificate` | SSL certificate details |
| `GET` | `/monitor/websites/{website}/security-headers` | Security headers analysis |
| `GET` | `/monitor/websites/{website}/open-ports` | Open ports information |
| `GET` | `/monitor/real-time-status` | Real-time status stream |

### Website Management (CRUD)

| Method | Path | Description |
|---|---|---|
| `GET` | `/websites/` | List all websites |
| `GET` | `/websites/create` | Create website form |
| `POST` | `/websites/` | Store a new website |
| `GET` | `/websites/{website}` | Website detail |
| `GET` | `/websites/{website}/edit` | Edit website form |
| `PUT` | `/websites/{website}` | Update a website |
| `DELETE` | `/websites/{website}` | Delete a website |
| `POST` | `/websites/{website}/scan` | Scan a specific website |

### Security Management

| Method | Path | Description |
|---|---|---|
| `GET` | `/security/login-attempts` | View login attempt logs |
| `POST` | `/security/block-ip` | Block an IP address |
| `GET` | `/security/report` | Generate a security report |

### Telegram Integration

| Method | Path | Description |
|---|---|---|
| `GET` | `/test-telegram` | Send a test Telegram notification |
| `POST` | `/telegram/webhook` | Telegram bot webhook handler |

---

## Future Improvements

- **Scheduled Automatic Scanning** — Periodically re-scan registered websites and alert on newly detected threats.
- **User Roles & Permissions** — Introduce role-based access control (admin, analyst, viewer) using Laravel Policies or Spatie Permissions.
- **Multi-user Support** — Allow multiple user accounts to manage independent website lists and receive separate notifications.
- **WHOIS & DNS Lookup** — Enrich scan results with domain registration and DNS record analysis.
- **CVE / NVD Integration** — Cross-reference detected technologies against the National Vulnerability Database.
- **REST API with Sanctum** — Expose a documented, token-authenticated REST API so third-party tools can trigger scans programmatically.
- **Export to JSON / CSV** — Additional export formats alongside the existing PDF option.
- **Dark Mode** — Add a dark theme toggle to the UI.
- **Two-Factor Authentication** — Strengthen login security with TOTP-based 2FA.
- **Dockerisation** — Provide a `docker-compose.yml` for fully containerised local development and deployment.

---

## Author

**yosuaaa22**
GitHub: [https://github.com/yosuaaa22](https://github.com/yosuaaa22)

Contributions, bug reports, and feature requests are welcome — please open an issue or submit a pull request on GitHub.

---

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

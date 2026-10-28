# BehiveAds LeadGen - WordPress Theme

A WordPress lead-generation theme for **BehiveAds** (behiveads.com) with landing pages.

## Folder Structure

```
habitat-leadgen/          ← repo root
├── Dockerfile            ← Docker build file
├── docker-compose.yml    ← Local development
├── setup.sh              ← WordPress auto-setup script
├── entrypoint.sh         ← Docker container entrypoint
├── create-pages.sh       ← WordPress pages creation script
├── wp-config.php         ← WordPress configuration (env-based)
├── .env.example          ← Environment variables template
├── .env.local            ← Local env (not committed)
├── .env.development      ← Dev env (not committed)
├── .env.production       ← Prod env (not committed)
├── .gitignore
├── README.md
└── theme/                ← WordPress theme files
    ├── style.css
    ├── functions.php
    ├── index.php
    ├── page-pest-control.php
    ├── page-pest-control-no-form.php
    ├── page-auto-insurance.php
    ├── page-health-insurance.php
    ├── page-medicare.php
    ├── page-home-services.php
    ├── page-final-expense.php
    ├── page-final-expense-2.php
    ├── page-water-services.php
    ├── page-roofing.php
    ├── page-plumbing.php
    ├── inc/
    │   ├── pages.php     ← Page content/copy
    │   └── render.php    ← Page renderer
    └── assets/
        ├── css/
        └── img/
```

## Landing Pages

| Page | URL |
|---|---|
| Pest Control | https://www.behiveads.com/pest-control/ |
| Pest Control (No Form) | https://www.behiveads.com/pest-control-info/ |
| Auto Insurance | https://www.behiveads.com/auto-insurance/ |
| Health Insurance | https://www.behiveads.com/health-insurance/ |
| Medicare | https://www.behiveads.com/medicare/ |
| Home Services | https://www.behiveads.com/home-services/ |
| Final Expense | https://www.behiveads.com/final-expense/ |
| Final Expense 2 | https://www.behiveads.com/final-expense-2/ |
| Water Services | https://www.behiveads.com/water-services/ |
| Roofing | https://www.behiveads.com/roofing/ |
| Plumbing | https://www.behiveads.com/plumbing/ |

## Run with Docker (Local)
```bash
docker compose up --build
```
Open: http://localhost:8080

## Deploy
Render.com auto-deploys on every push to `main` branch.

## Admin
- URL: https://www.behiveads.com/wp-admin/
- Username: `admin`
- Password: `BehiveAds@2024!`

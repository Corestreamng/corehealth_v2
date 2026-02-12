# CoreHealth Patient Mobile App

A Flutter-based mobile application for patients using the CoreHealth Hospital Management System.

## Features
- Dynamic branding from your hospital's CoreHealth instance
- Onboarding slideshow on first launch
- Connect to any CoreHealth instance via base URL
- Patient authentication with hospital number + phone
- View appointments & consultation history
- View lab & imaging results
- View prescriptions & medication history
- HMO status & coverage info

## Getting Started

1. **Install Flutter** (3.2+): https://docs.flutter.dev/get-started/install
2. **Install dependencies:**
   ```bash
   cd mobile_apps/patient_app
   flutter pub get
   ```
3. **Run the app:**
   ```bash
   flutter run
   ```

## Architecture
```
lib/
├── main.dart               # App entry point
├── app.dart                # MaterialApp with dynamic theming
├── core/
│   ├── api/                # API client, interceptors
│   ├── config/             # Server config, constants
│   ├── theme/              # Dynamic theme from hos_color
│   └── storage/            # Secure local storage
├── features/
│   ├── splash/             # Splash screen
│   ├── onboarding/         # Onboarding slideshow
│   ├── server_setup/       # Base URL / IP entry
│   ├── auth/               # Login screen (hospital number + phone)
│   └── home/               # Dashboard after login
└── shared/
    └── widgets/            # Reusable widgets
```

## API Endpoints Used
| Endpoint | Method | Auth | Description |
|---|---|---|---|
| `/api/mobile/instance-info` | GET | None | Branding, logo, colors |
| `/api/mobile/patient/login` | POST | None | Hospital number + phone login |
| `/api/mobile/logout` | POST | Sanctum | Revoke token |

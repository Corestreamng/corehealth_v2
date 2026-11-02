@php
    $site = appsettings();
    $isBranded = (bool) ($showBrandedHomepage ?? (appsettings('show_branded_homepage') ?? true));
    $siteName = $site->site_name ?? env('APP_NAME', 'CoreHealth');
    $siteLogo = $site->logo ?? null;
    $hosColor = $site->hos_color ?? '#123B5D';
    $sitePhone = $site->contact_phones ?? '+234 (0) 816 087 6560';
    $siteEmail = $site->contact_emails ?? 'hello@corestream.ng';
    $siteAddress = $site->contact_address ?? 'Jos, Plateau State, Nigeria';
    $siteDescription = $site->description ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  @if($isBranded)
    <title>{{ $siteName }} — CoreHealth AI Healthcare Ecosystem</title>
  @else
    <title>CoreHealth — AI‑Powered Healthcare Management Ecosystem</title>
  @endif
  <meta name="description" content="{{ $isBranded ? ($siteName . ' healthcare operations, digital records, and clinical management powered by CoreHealth.') : 'CoreHealth unifies electronic medical records, hospital operations, AI clinical documentation, and telemedicine.' }}" />

  <!-- Google Fonts: Inter & Manrope for typography -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Manrope:wght@500;600;700;800&display=swap"
    rel="stylesheet"
  />

  <!-- CoreHealth Welcome Stylesheet -->
  <link rel="stylesheet" href="{{ asset('css/welcome.css') }}?v={{ config('app.version', '2.6') }}" />

  @if($isBranded && !empty($hosColor))
  <style>
    :root {
      --hospital-primary: {{ $hosColor }};
    }
  </style>
  @endif
</head>
<body>
  <!-- ========== HEADER ========== -->
  <header class="header" role="banner">
    <div class="container header-inner">
      @if($isBranded)
        <a href="{{ url('/') }}" class="brand-logo-combo" aria-label="{{ $siteName }}">
          @if($siteLogo)
            <img src="data:image/jpeg;base64,{{ $siteLogo }}" alt="{{ $siteName }}" class="hospital-brand-img" />
          @else
            <span class="logo-symbol" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
              </svg>
            </span>
          @endif
          <div class="brand-text-block">
            <span class="hospital-brand-title">{{ $siteName }}</span>
            <span class="hospital-brand-sub">Powered by Core<span>Health</span></span>
          </div>
        </a>
      @else
        <a href="{{ url('/') }}" class="logo" aria-label="CoreHealth by Corestream Networks and Robotics Limited">
          <span class="logo-symbol" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
            </svg>
          </span>
          Core<span>Health</span>
        </a>
      @endif

      <nav class="nav-desktop" aria-label="Main navigation">
        <a href="#features" class="nav-link">Features</a>
        <a href="#solutions" class="nav-link">Solutions</a>
        <a href="#integrations" class="nav-link">Integrations</a>
        <a href="#impact" class="nav-link">Impact</a>
        <a href="#about" class="nav-link">About</a>
      </nav>

      <div class="header-actions">
        @if($isBranded)
          <a href="#contact" class="btn btn-secondary btn-sm">Contact</a>
          @auth
            <a href="{{ url('/home') }}" class="btn btn-primary btn-sm">Operations Cockpit</a>
          @else
            <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Staff Portal</a>
          @endauth
        @else
          <a href="https://corestream.ng/public/contact" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Contact</a>
          <a href="https://corestream.ng/public/contact" class="btn btn-teal btn-sm" target="_blank" rel="noopener">Request a Demo</a>
          @auth
            <a href="{{ url('/home') }}" class="btn btn-primary btn-sm">Dashboard</a>
          @else
            <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Sign In</a>
          @endauth
        @endif

        <label for="mobile-menu-toggle" class="mobile-menu-btn" aria-label="Toggle navigation menu">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="3" y1="6" x2="21" y2="6" />
            <line x1="3" y1="12" x2="21" y2="12" />
            <line x1="3" y1="18" x2="21" y2="18" />
          </svg>
        </label>
      </div>
    </div>

    <!-- Mobile menu (checkbox hack) -->
    <input type="checkbox" id="mobile-menu-toggle" class="sr-only" />
    <nav class="mobile-menu" aria-label="Mobile navigation">
      <a href="#features" class="nav-link">Features</a>
      <a href="#solutions" class="nav-link">Solutions</a>
      <a href="#integrations" class="nav-link">Integrations</a>
      <a href="#impact" class="nav-link">Impact</a>
      <a href="#about" class="nav-link">About</a>
      @if($isBranded)
        <a href="#contact" class="btn btn-secondary">Contact Facility</a>
        @auth
          <a href="{{ url('/home') }}" class="btn btn-primary">Operations Cockpit</a>
        @else
          <a href="{{ route('login') }}" class="btn btn-primary">Staff Portal Sign In</a>
        @endauth
      @else
        <a href="https://corestream.ng/public/contact" class="btn btn-secondary" target="_blank" rel="noopener">Contact Corestream</a>
        <a href="https://corestream.ng/public/contact" class="btn btn-teal" target="_blank" rel="noopener">Request a Demo</a>
        @auth
          <a href="{{ url('/home') }}" class="btn btn-primary">Dashboard</a>
        @else
          <a href="{{ route('login') }}" class="btn btn-primary">Sign In</a>
        @endauth
      @endif
    </nav>
  </header>

  <main>
    <!-- ========== HERO ========== -->
    <section class="hero" aria-labelledby="hero-title">
      <div class="container hero-grid">
        <div class="hero-content">
          @if($isBranded)
            <span class="hero-badge">
              <span class="dot" aria-hidden="true"></span>
              {{ $siteName }} · Smart Health Ecosystem
            </span>
            <h1 id="hero-title" class="hero-title">
              Excellence in Care.<br />
              Empowered by <span class="highlight">Intelligence.</span>
            </h1>
            <p class="hero-description">
              Welcome to {{ $siteName }}. Unifying electronic health records, rapid outpatient triage, laboratory diagnostics, pharmacy dispensing, and 24/7 telehealth into one seamless patient-first care ecosystem.
            </p>
            <div class="hero-actions">
              @auth
                <a href="{{ url('/home') }}" class="btn btn-primary">Enter Operations Cockpit</a>
              @else
                <a href="{{ route('login') }}" class="btn btn-primary">Staff Portal Sign In</a>
              @endauth
              <a href="#solutions" class="btn btn-secondary">Clinical Departments</a>
            </div>
            <div class="hero-stats" aria-label="Platform highlights">
              <div class="hero-stat-item">
                <span class="hero-stat-value">24/7</span>
                <span class="hero-stat-label">Emergency & Care</span>
              </div>
              <div class="hero-stat-item">
                <span class="hero-stat-value">EHR</span>
                <span class="hero-stat-label">Longitudinal Records</span>
              </div>
              <div class="hero-stat-item">
                <span class="hero-stat-value">WeCare</span>
                <span class="hero-stat-label">Telehealth Integrated</span>
              </div>
            </div>
          @else
            <span class="hero-badge">
              <span class="dot" aria-hidden="true"></span>
              AI-Powered Healthcare Management
            </span>
            <h1 id="hero-title" class="hero-title">
              Digitize. Automate.<br />
              Deliver <span class="highlight">Better Care.</span>
            </h1>
            <p class="hero-description">
              CoreHealth unifies electronic medical records, hospital operations, AI-assisted
              clinical documentation, and telemedicine into one intelligent platform — built for
              hospitals, clinics, and health systems across Africa and beyond.
            </p>
            <div class="hero-actions">
              <a href="#demo" class="btn btn-primary">Request a Demo</a>
              <a href="#features" class="btn btn-secondary">Explore CoreHealth</a>
            </div>
            <div class="hero-stats" aria-label="Platform highlights">
              <div class="hero-stat-item">
                <span class="hero-stat-value">DHIS2</span>
                <span class="hero-stat-label">Ready Integration</span>
              </div>
              <div class="hero-stat-item">
                <span class="hero-stat-value">NHMIS</span>
                <span class="hero-stat-label">Compliant Reporting</span>
              </div>
              <div class="hero-stat-item">
                <span class="hero-stat-value">24/7</span>
                <span class="hero-stat-label">Telemedicine Access</span>
              </div>
            </div>
          @endif
        </div>

        <!-- Dashboard Mockup (Synthetic Data) -->
        <div class="hero-visual" aria-hidden="true">
          <div class="dashboard-mockup">
            <div class="dashboard-header">
              <span class="dashboard-dot red"></span>
              <span class="dashboard-dot yellow"></span>
              <span class="dashboard-dot green"></span>
              <span style="margin-left: auto; font-size: 0.625rem; font-weight: 600; color: var(--gray-500);">
                {{ $isBranded ? ($siteName . ' · Operations Telemetry') : 'CoreHealth · Hospital Dashboard' }}
              </span>
            </div>
            <div class="dashboard-body">
              <!-- Stats row -->
              <div class="dash-stats-row">
                <div class="dash-stat-card">
                  <div class="dash-stat-label">Total Patients</div>
                  <div class="dash-stat-value">1,284</div>
                  <div class="dash-stat-change">↑ 12% this month</div>
                </div>
                <div class="dash-stat-card">
                  <div class="dash-stat-label">Appointments</div>
                  <div class="dash-stat-value">342</div>
                  <div class="dash-stat-change">↑ 8% this week</div>
                </div>
                <div class="dash-stat-card">
                  <div class="dash-stat-label">AI Summaries</div>
                  <div class="dash-stat-value">96</div>
                  <div class="dash-stat-change neutral">Generated today</div>
                </div>
              </div>

              <!-- Main row: chart + patient list -->
              <div class="dash-main-row">
                <div class="dash-chart-card">
                  <div class="dash-card-title">
                    Patient Activity
                    <span class="badge">Weekly</span>
                  </div>
                  <div class="chart-bars">
                    <div class="chart-bar" style="height: 40%"></div>
                    <div class="chart-bar active" style="height: 65%"></div>
                    <div class="chart-bar cyan" style="height: 52%"></div>
                    <div class="chart-bar active" style="height: 78%"></div>
                    <div class="chart-bar" style="height: 45%"></div>
                    <div class="chart-bar active" style="height: 88%"></div>
                    <div class="chart-bar cyan" style="height: 62%"></div>
                  </div>
                  <div class="chart-labels">
                    <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                  </div>
                </div>

                <div class="dash-chart-card">
                  <div class="dash-card-title">Recent Patients</div>
                  <div class="patient-list">
                    <div class="patient-item">
                      <div class="patient-avatar">AA</div>
                      <div class="patient-info">
                        <div class="patient-name">Amina A.</div>
                        <div class="patient-meta">Cardiology · 09:30</div>
                      </div>
                      <span class="patient-status active">Active</span>
                    </div>
                    <div class="patient-item">
                      <div class="patient-avatar">JO</div>
                      <div class="patient-info">
                        <div class="patient-name">James O.</div>
                        <div class="patient-meta">General · 10:15</div>
                      </div>
                      <span class="patient-status pending">Pending</span>
                    </div>
                    <div class="patient-item">
                      <div class="patient-avatar">FB</div>
                      <div class="patient-info">
                        <div class="patient-name">Fatima B.</div>
                        <div class="patient-meta">Pediatrics · 11:00</div>
                      </div>
                      <span class="patient-status active">Active</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- AI summary -->
              <div class="dash-ai-card">
                <div class="dash-ai-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2a10 10 0 1 0 10 10" />
                    <path d="M12 6v6l4 2" />
                  </svg>
                </div>
                <div class="dash-ai-content">
                  <div class="dash-ai-title">AI Clinical Summary · Ready</div>
                  <div class="dash-ai-text">
                    Patient presents with <span class="highlight-ai">stable vitals</span>,
                    mild hypertension. Recommended follow-up in 2 weeks.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ========== QUICK LINKS ========== -->
    <nav class="quick-links" aria-label="Quick links">
      <div class="quick-links-inner">
        <a href="#about" class="quick-link">Overview</a>
        <a href="#features" class="quick-link">Features</a>
        <a href="#solutions" class="quick-link">Solutions</a>
        <a href="#integrations" class="quick-link">Integrations</a>
        <a href="#about" class="quick-link">About Us</a>
        <a href="#impact" class="quick-link">Impact</a>
        <a href="#contact" class="quick-link">Contact</a>
      </div>
    </nav>

    <!-- ========== TRUSTED SECTION ========== -->
    <section class="section trusted-section" id="about" aria-labelledby="trusted-title">
      <div class="container trusted-grid">
        <div class="trusted-content">
          <span class="section-label">{{ $isBranded ? 'Accredited Healthcare Operations' : 'Trusted Healthcare Technology' }}</span>
          <h3 id="trusted-title">
            {{ $isBranded ? 'Proven healthcare digitization & clinical excellence' : 'Proven healthcare digitization experience across Nigeria' }}
          </h3>
          <p>
            @if($isBranded)
              At {{ $siteName }}, our clinical workflows are powered by CoreHealth to eliminate paper bottlenecks, protect electronic medical records, and guarantee complete NHMIS and DHIS2 reporting alignment.
            @else
              CoreHealth is deployed in Plateau State and built to integrate with national health
              information systems. We help institutions move from paper-based processes to secure,
              interoperable digital workflows — without disrupting care delivery.
            @endif
          </p>
          <ul class="trusted-list">
            <li>
              <span class="check-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
              </span>
              <span>DHIS2 integration for national health reporting</span>
            </li>
            <li>
              <span class="check-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
              </span>
              <span>NHMIS-compliant data collection and analytics</span>
            </li>
            <li>
              <span class="check-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
              </span>
              <span>Role-based access control and comprehensive audit trails</span>
            </li>
            <li>
              <span class="check-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
              </span>
              <span>Scalable architecture for hospitals and multi-facility networks</span>
            </li>
          </ul>
        </div>

        <div class="trusted-visual">
          <div class="trust-card">
            <div class="trust-card-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg>
            </div>
            <div class="trust-card-text">
              <h4>{{ $isBranded ? 'Clinical Care Excellence' : 'Deployed in Plateau State' }}</h4>
              <p>{{ $isBranded ? 'Modern healthcare facility providing compassionate, evidence-based clinical care.' : 'Supporting healthcare facilities with real-world digital health infrastructure.' }}</p>
            </div>
          </div>
          <div class="trust-card">
            <div class="trust-card-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <line x1="2" y1="12" x2="22" y2="12" />
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
              </svg>
            </div>
            <div class="trust-card-text">
              <h4>DHIS2 & NHMIS Ready</h4>
              <p>Seamless integration with national and regional health information systems.</p>
            </div>
          </div>
          <div class="trust-card">
            <div class="trust-card-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                <line x1="8" y1="21" x2="16" y2="21" />
                <line x1="12" y1="17" x2="12" y2="21" />
              </svg>
            </div>
            <div class="trust-card-text">
              <h4>Built for Scale</h4>
              <p>From single clinics to multi-facility health networks and government agencies.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ========== CORE FEATURES ========== -->
    <section class="section features-section" id="features" aria-labelledby="features-title">
      <div class="container">
        <div class="section-header">
          <span class="section-label">Core Features</span>
          <h2 id="features-title" class="section-title">Everything your facility needs, in one platform</h2>
          <p class="section-subtitle">
            From electronic medical records to AI-powered documentation, CoreHealth covers the
            entire clinical and operational workflow.
          </p>
        </div>

        <div class="features-grid">
          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
              </svg>
            </div>
            <h3>Electronic Medical Records</h3>
            <p>Comprehensive patient records with clinical history, diagnoses, medications, and lab results — accessible securely across departments.</p>
          </article>

          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
              </svg>
            </div>
            <h3>Hospital Information Management</h3>
            <p>Manage admissions, transfers, discharges, bed occupancy, and operational workflows from a unified command centre.</p>
          </article>

          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 2a10 10 0 1 0 10 10" />
                <path d="M12 6v6l4 2" />
              </svg>
            </div>
            <h3>AI Clinical Documentation</h3>
            <p>Voice-to-text dictation and AI-generated clinical summaries reduce administrative burden and free clinicians to focus on patients.</p>
          </article>

          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
              </svg>
            </div>
            <h3>Pharmacy & Inventory</h3>
            <p>Track medication stock, manage prescriptions, automate reordering, and prevent stockouts with real-time inventory visibility.</p>
          </article>

          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18" />
              </svg>
            </div>
            <h3>Laboratory Management</h3>
            <p>Order tests, track specimens, record results, and deliver reports digitally — with full audit trails and integration to EMR.</p>
          </article>

          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="1" x2="12" y2="23" />
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
              </svg>
            </div>
            <h3>Billing & Revenue Cycle</h3>
            <p>Automate invoicing, insurance claims, payment tracking, and financial reporting to improve revenue integrity.</p>
          </article>

          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 12v-2a5 5 0 0 0-5-5H8a5 5 0 0 0-5 5v2" />
                <circle cx="12" cy="16" r="5" />
                <line x1="12" y1="11" x2="12" y2="16" />
              </svg>
            </div>
            <h3>Reporting & Analytics</h3>
            <p>Real-time dashboards and custom reports for clinical, operational, and financial decision-making at every level.</p>
          </article>

          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
              </svg>
            </div>
            <h3>Role-Based Access Control</h3>
            <p>Granular permissions ensure that every user — from doctors to administrators — sees only what they need, securely.</p>
          </article>

          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
              </svg>
            </div>
            <h3>Telemedicine Integration</h3>
            <p>Built-in telemedicine through WeCare247 connects patients and providers for virtual consultations and follow-ups.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- ========== AI SECTION ========== -->
    <section class="section ai-section" aria-labelledby="ai-title">
      <div class="container ai-grid">
        <div class="ai-content">
          <span class="section-label">AI-Powered Healthcare</span>
          <h2 id="ai-title">Intelligent tools that support clinical excellence</h2>
          <p>
            CoreHealth embeds AI into the clinical workflow — not as a replacement for
            professional judgment, but as a powerful assistant that saves time and reduces
            administrative burden.
          </p>

          <ul class="ai-feature-list">
            <li class="ai-feature-item">
              <div class="ai-feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
                  <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                  <line x1="12" y1="19" x2="12" y2="23" />
                  <line x1="8" y1="23" x2="16" y2="23" />
                </svg>
              </div>
              <div class="ai-feature-text">
                <h4>Voice-to-Text Medical Dictation</h4>
                <p>Clinicians dictate notes naturally; CoreHealth transcribes and structures them into the patient record.</p>
              </div>
            </li>
            <li class="ai-feature-item">
              <div class="ai-feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                  <polyline points="14 2 14 8 20 8" />
                  <line x1="16" y1="13" x2="8" y2="13" />
                  <line x1="16" y1="17" x2="8" y2="17" />
                </svg>
              </div>
              <div class="ai-feature-text">
                <h4>AI-Generated Clinical Summaries</h4>
                <p>Automatically generate concise, accurate summaries from consultations, lab results, and clinical notes.</p>
              </div>
            </li>
            <li class="ai-feature-item">
              <div class="ai-feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                </svg>
              </div>
              <div class="ai-feature-text">
                <h4>Intelligent Workflows</h4>
                <p>Smart task routing, automated alerts, and predictive scheduling keep departments running efficiently.</p>
              </div>
            </li>
            <li class="ai-feature-item">
              <div class="ai-feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                </svg>
              </div>
              <div class="ai-feature-text">
                <h4>Integrated Telemedicine via WeCare247</h4>
                <p>Conduct secure video consultations, share records, and follow up with patients remotely — all within CoreHealth.</p>
              </div>
            </li>
          </ul>
        </div>

        <!-- AI Mockup / WeCare247 preview -->
        <div class="ai-visual" aria-hidden="true">
          <div class="ai-mockup">
            <div class="ai-mockup-header">
              <h4>WeCare247 · Telemedicine Consultation</h4>
              <p>AI-assisted documentation in progress</p>
            </div>
            <div class="ai-mockup-body">
              <div class="ai-transcript">
                <div class="ai-transcript-label">Voice Transcript</div>
                <div class="ai-transcript-text">
                  "Patient reports persistent headache for three days, mild fever, no neck stiffness.
                  Vitals stable. Recommend malaria test and hydration."
                </div>
              </div>
              <div class="ai-summary">
                <div class="ai-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2a10 10 0 1 0 10 10" />
                    <path d="M12 6v6l4 2" />
                  </svg>
                  AI Clinical Summary
                </div>
                <div class="ai-summary-text">
                  <strong>Assessment:</strong> Suspected uncomplicated malaria.
                  <strong>Plan:</strong> Order RDT, prescribe antimalarial, hydration advice.
                  <strong>Follow-up:</strong> 48 hours or sooner if symptoms worsen.
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ========== SOLUTIONS BY USER ========== -->
    <section class="section solutions-section" id="solutions" aria-labelledby="solutions-title">
      <div class="container">
        <div class="section-header">
          <span class="section-label">Solutions by User</span>
          <h2 id="solutions-title" class="section-title">Tailored for every healthcare role</h2>
          <p class="section-subtitle">
            Whether you're a hospital administrator, clinician, pharmacist, or government agency,
            CoreHealth adapts to your workflow.
          </p>
        </div>

        <div class="solutions-grid">
          <a href="#about" class="solution-card">
            <div class="solution-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
              </svg>
            </div>
            <h4>Hospitals</h4>
            <p>Full-scale hospital information management, from admission to discharge.</p>
            <span class="arrow">Learn more →</span>
          </a>

          <a href="#about" class="solution-card">
            <div class="solution-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
              </svg>
            </div>
            <h4>Clinics</h4>
            <p>Lightweight, affordable digital tools for outpatient and primary care.</p>
            <span class="arrow">Learn more →</span>
          </a>

          <a href="#about" class="solution-card">
            <div class="solution-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
            </div>
            <h4>Healthcare Administrators</h4>
            <p>Analytics, reporting, and operational oversight in one dashboard.</p>
            <span class="arrow">Learn more →</span>
          </a>

          <a href="#about" class="solution-card">
            <div class="solution-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 2a10 10 0 1 0 10 10" />
                <path d="M12 6v6l4 2" />
              </svg>
            </div>
            <h4>Doctors</h4>
            <p>AI-assisted notes, quick access to patient history, and e-prescribing.</p>
            <span class="arrow">Learn more →</span>
          </a>

          <a href="#about" class="solution-card">
            <div class="solution-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
              </svg>
            </div>
            <h4>Pharmacists</h4>
            <p>Inventory control, prescription management, and dispensing workflows.</p>
            <span class="arrow">Learn more →</span>
          </a>

          <a href="#about" class="solution-card">
            <div class="solution-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18" />
              </svg>
            </div>
            <h4>Laboratories</h4>
            <p>Test ordering, result management, and seamless EMR integration.</p>
            <span class="arrow">Learn more →</span>
          </a>

          <a href="#about" class="solution-card">
            <div class="solution-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <line x1="2" y1="12" x2="22" y2="12" />
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
              </svg>
            </div>
            <h4>Government Health Agencies</h4>
            <p>DHIS2 integration, NHMIS reporting, and population health analytics.</p>
            <span class="arrow">Learn more →</span>
          </a>

          <a href="#about" class="solution-card">
            <div class="solution-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
              </svg>
            </div>
            <h4>Health Networks</h4>
            <p>Multi-facility coordination, shared records, and centralised reporting.</p>
            <span class="arrow">Learn more →</span>
          </a>
        </div>
      </div>
    </section>

    <!-- ========== INTEGRATIONS ========== -->
    <section class="section integrations-section" id="integrations" aria-labelledby="integrations-title">
      <div class="container integrations-grid">
        <div class="integrations-content">
          <span class="section-label">Integrations & Interoperability</span>
          <h2 id="integrations-title">Connect with the systems you already use</h2>
          <p>
            CoreHealth is built on open standards and APIs, making it easy to integrate with
            national health information systems, laboratory equipment, and existing hospital
            software.
          </p>
          <div class="integration-badges">
            <span class="integration-badge">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <line x1="2" y1="12" x2="22" y2="12" />
              </svg>
              DHIS2
            </span>
            <span class="integration-badge">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg>
              NHMIS
            </span>
            <span class="integration-badge">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
              </svg>
              REST APIs
            </span>
            <span class="integration-badge">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
              </svg>
              HL7 / FHIR
            </span>
          </div>
        </div>

        <div class="integrations-visual">
          <div class="integration-card">
            <div class="integration-card-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <line x1="2" y1="12" x2="22" y2="12" />
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
              </svg>
            </div>
            <div class="integration-card-text">
              <h4>DHIS2</h4>
              <p>Automated data submission to national health information systems.</p>
            </div>
          </div>
          <div class="integration-card">
            <div class="integration-card-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg>
            </div>
            <div class="integration-card-text">
              <h4>NHMIS</h4>
              <p>Compliant data collection and reporting for Nigerian health facilities.</p>
            </div>
          </div>
          <div class="integration-card">
            <div class="integration-card-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                <line x1="8" y1="21" x2="16" y2="21" />
                <line x1="12" y1="17" x2="12" y2="21" />
              </svg>
            </div>
            <div class="integration-card-text">
              <h4>Existing Hospital Systems</h4>
              <p>Connect with legacy software, lab equipment, and third-party tools via APIs.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ========== IMPACT & TRUST ========== -->
    <section class="section impact-section" id="impact" aria-labelledby="impact-title">
      <div class="container">
        <div class="section-header">
          <span class="section-label">Impact & Trust</span>
          <h2 id="impact-title" class="section-title">Building confidence through real deployments</h2>
          <p class="section-subtitle">
            CoreHealth is live in Plateau State and continues to expand, supporting healthcare
            institutions with reliable, secure technology.
          </p>
        </div>

        <div class="impact-stats">
          <div class="impact-stat">
            <div class="impact-stat-value">1</div>
            <div class="impact-stat-label">State Deployment</div>
          </div>
          <div class="impact-stat">
            <div class="impact-stat-value">DHIS2</div>
            <div class="impact-stat-label">Integration Ready</div>
          </div>
          <div class="impact-stat">
            <div class="impact-stat-value">NHMIS</div>
            <div class="impact-stat-label">Compliant</div>
          </div>
          <div class="impact-stat">
            <div class="impact-stat-value">100%</div>
            <div class="impact-stat-label">Commitment to Security</div>
          </div>
        </div>

        <div class="testimonials-grid">
          <div class="testimonial-card">
            <p class="testimonial-quote">
              "CoreHealth has streamlined our patient record management and made reporting to
              DHIS2 significantly easier. The AI summaries save our doctors valuable time."
            </p>
            <div class="testimonial-author">
              <div class="testimonial-avatar" aria-hidden="true">HA</div>
              <div class="testimonial-author-info">
                <h5>Hospital Administrator</h5>
                <p>Plateau State, Nigeria</p>
              </div>
            </div>
          </div>

          <div class="testimonial-card">
            <p class="testimonial-quote">
              "The platform is intuitive and the support team is responsive. We were able to
              digitize our outpatient department within weeks."
            </p>
            <div class="testimonial-author">
              <div class="testimonial-avatar" aria-hidden="true">CM</div>
              <div class="testimonial-author-info">
                <h5>Clinical Manager</h5>
                <p>Private Clinic, Jos</p>
              </div>
            </div>
          </div>

          <div class="testimonial-card">
            <p class="testimonial-quote">
              "Integration with NHMIS was a key requirement for us, and CoreHealth delivered.
              Data quality and reporting have improved noticeably."
            </p>
            <div class="testimonial-author">
              <div class="testimonial-avatar" aria-hidden="true">GH</div>
              <div class="testimonial-author-info">
                <h5>Government Health Officer</h5>
                <p>Plateau State Ministry of Health</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ========== FINAL CTA ========== -->
    <section class="section final-cta" id="demo" aria-labelledby="cta-title">
      <div class="container final-cta-inner">
        @if($isBranded)
          <h2 id="cta-title">Excellence in Healthcare at {{ $siteName }}</h2>
          <p>
            Connecting patients, clinicians, laboratories, and pharmacy into a synchronized healthcare ecosystem.
          </p>
          <div class="final-cta-actions">
            @auth
              <a href="{{ url('/home') }}" class="btn btn-primary">Enter Operations Cockpit</a>
            @else
              <a href="{{ route('login') }}" class="btn btn-primary">Staff Portal Sign In</a>
            @endauth
            <a href="#contact" class="btn btn-teal">Contact Facility</a>
            <a href="https://corestream.ng/public/contact" class="btn btn-secondary" target="_blank" rel="noopener">About CoreHealth</a>
          </div>
        @else
          <h2 id="cta-title">Ready to modernize your healthcare operations?</h2>
          <p>
            Join healthcare institutions across Africa using CoreHealth to digitize, automate,
            and deliver better care.
          </p>
          <div class="final-cta-actions">
            <a href="https://corestream.ng/public/contact" class="btn btn-primary" target="_blank" rel="noopener">Book a Demo</a>
            <a href="#contact" class="btn btn-teal">Talk to Our Team</a>
            <a href="https://corestream.ng/public/contact" class="btn btn-secondary" target="_blank" rel="noopener">Become a Partner</a>
          </div>
        @endif
      </div>
    </section>
  </main>

  <!-- ========== FOOTER ========== -->
  <footer class="footer" id="contact" role="contentinfo">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          @if($isBranded)
            <a href="{{ url('/') }}" class="logo brand-logo-combo" aria-label="{{ $siteName }}" style="color:#ffffff;">
              @if($siteLogo)
                <img src="data:image/jpeg;base64,{{ $siteLogo }}" alt="{{ $siteName }}" class="hospital-brand-img" style="filter: brightness(0) invert(1);" />
              @else
                <span class="logo-symbol" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                  </svg>
                </span>
              @endif
              <div class="brand-text-block">
                <span class="hospital-brand-title" style="color:#ffffff;">{{ $siteName }}</span>
                <span class="hospital-brand-sub" style="color:rgba(255,255,255,0.7);">Powered by Core<span style="color:var(--cyan);">Health</span></span>
              </div>
            </a>
            <p style="margin-top: 1rem;">
              {{ $siteDescription ?: ($siteName . ' provides world-class clinical care, diagnostic excellence, and 24/7 patient services powered by the CoreHealth intelligent ecosystem.') }}
            </p>
          @else
            <a href="{{ url('/') }}" class="logo" aria-label="CoreHealth by Corestream Networks and Robotics Limited">
              <span class="logo-symbol" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                </svg>
              </span>
              Core<span>Health</span>
            </a>
            <p>
              AI-powered healthcare management ecosystem by Corestream Networks and Robotics
              Limited. Digitizing hospitals, clinics, and health systems across Africa.
            </p>
          @endif

          <div class="footer-social">
            <a href="https://linkedin.com" target="_blank" rel="noopener" aria-label="LinkedIn">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
              </svg>
            </a>
            <a href="https://x.com" target="_blank" rel="noopener" aria-label="X (Twitter)">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
              </svg>
            </a>
            <a href="https://facebook.com" target="_blank" rel="noopener" aria-label="Facebook">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
              </svg>
            </a>
          </div>
        </div>

        <div class="footer-column">
          <h4>Platform</h4>
          <ul>
            <li><a href="#features">Features</a></li>
            <li><a href="#solutions">Solutions</a></li>
            <li><a href="#integrations">Integrations</a></li>
            <li><a href="#impact">Impact</a></li>
            <li><a href="#about">About Us</a></li>
          </ul>
        </div>

        <div class="footer-column">
          <h4>Resources</h4>
          <ul>
            <li><a href="{{ route('login') }}">Staff Portal</a></li>
            <li><a href="#features">EMR & EHR</a></li>
            <li><a href="#features">Laboratory & Diagnostics</a></li>
            <li><a href="#features">Pharmacy & Dispensary</a></li>
            <li><a href="https://corestream.ng/public/contact" target="_blank" rel="noopener">Support</a></li>
          </ul>
        </div>

        <div class="footer-column">
          <h4>{{ $isBranded ? 'Contact Facility' : 'Contact Corestream' }}</h4>
          <div class="footer-contact-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
              <circle cx="12" cy="10" r="3" />
            </svg>
            <span>{{ $isBranded ? $siteAddress : 'Jos, Plateau State, Nigeria' }}</span>
          </div>
          <div class="footer-contact-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
              <polyline points="22,6 12,13 2,6" />
            </svg>
            <span>{{ $isBranded ? $siteEmail : 'hello@corestream.ng' }}</span>
          </div>
          <div class="footer-contact-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
            </svg>
            <span>{{ $isBranded ? $sitePhone : '+234 (0) 816 087 6560' }}</span>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; {{ date('Y') }} {{ $isBranded ? $siteName : 'Corestream Networks and Robotics Limited' }}. All rights reserved.</p>
        <div class="footer-bottom-links">
          <a href="#">Privacy Policy</a>
          <a href="#">Terms of Service</a>
          <a href="#">Security</a>
        </div>
      </div>
    </div>
  </footer>
</body>
</html>

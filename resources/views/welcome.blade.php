@php
    $site = appsettings();
    $isBranded = (bool) ($showBrandedHomepage ?? (appsettings('show_branded_homepage') ?? true));
    $siteName = $site->site_name ?? env('APP_NAME', 'CoreHealth');
    $siteLogo = $site->logo ?? null;
    if ($siteLogo && !str_starts_with($siteLogo, 'data:') && !str_starts_with($siteLogo, 'http')) {
        $siteLogo = 'data:image/jpeg;base64,' . $siteLogo;
    }
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
    <title>{{ $siteName }} — CoreHealth Clinical Care & Services</title>
    <meta name="description" content="{{ $siteDescription ?: ($siteName . ' healthcare operations, digital clinical records, outpatient triage, inpatient care, and patient portal.') }}" />
  @else
    <title>CoreHealth — The AI-Powered Operating System for Healthcare</title>
    <meta name="description" content="CoreHealth unifies EMR, hospital operations, AI clinical documentation and telemedicine in one platform. Start free or book a demo." />
  @endif

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet" />

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

@if(!$isBranded)
  {{-- =====================================================================
       UNBRANDED MODE: COREHEALTH PRODUCT PLATFORM LANDING PAGE
       ===================================================================== --}}
  <div class="announce">
    <span class="tag">NEW</span>AI clinical documentation with voice dictation is live in CoreHealth.
    <a href="#demo">See it in action &rarr;</a>
  </div>

  <!-- Header + Mega Menu -->
  <header class="header">
    <div class="container header-in">
      <a href="{{ url('/') }}" class="logo" aria-label="CoreHealth home">
        <span class="logo-mark">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
          </svg>
        </span>
        Core<b>Health</b>
      </a>

      <nav class="nav" aria-label="Main">
        <!-- Product -->
        <div class="nav-item">
          <button class="nav-trigger" aria-haspopup="true">Product <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l4 4 4-4"/></svg></button>
          <div class="mega">
            <div class="mega-in">
              <div class="mega-col">
                <h6>Clinical</h6>
                <a class="mega-link" href="#features"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span><div><strong>Electronic Medical Records</strong><span>Complete, secure patient charts</span></div></a>
                <a class="mega-link" href="#ai"><span class="mega-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg></span><div><strong>AI Clinical Documentation</strong><span>Dictate, transcribe, summarise</span></div></a>
                <a class="mega-link" href="#features"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg></span><div><strong>Laboratory Management</strong><span>Orders, specimens and results</span></div></a>
              </div>
              <div class="mega-col">
                <h6>Operations</h6>
                <a class="mega-link" href="#features"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span><div><strong>Hospital Management</strong><span>Admissions, beds, discharge</span></div></a>
                <a class="mega-link" href="#features"><span class="mega-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg></span><div><strong>Pharmacy & Inventory</strong><span>Stock, prescriptions, reorders</span></div></a>
                <a class="mega-link" href="#features"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span><div><strong>Billing & Revenue</strong><span>Invoices, claims, payments</span></div></a>
              </div>
              <div class="mega-col">
                <h6>Data & Care</h6>
                <a class="mega-link" href="#features"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><div><strong>Reporting & Analytics</strong><span>Live dashboards and exports</span></div></a>
                <a class="mega-link" href="#ai"><span class="mega-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg></span><div><strong>Telemedicine (WeCare247)</strong><span>Virtual consultations built in</span></div></a>
                <a class="mega-link" href="#security"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span><div><strong>Access Control & Audit</strong><span>Roles, permissions, trails</span></div></a>
              </div>
              <div class="mega-col callout">
                <h6>New in 2.6</h6>
                <h4>Voice-to-text medical dictation</h4>
                <p>Dictate consultation notes naturally. AI transcribes and structures directly into the patient record.</p>
                <a href="#demo" class="btn btn-red btn-sm">See dictation demo</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Solutions -->
        <div class="nav-item">
          <button class="nav-trigger" aria-haspopup="true">Solutions <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l4 4 4-4"/></svg></button>
          <div class="mega">
            <div class="mega-in">
              <div class="mega-col">
                <h6>By facility type</h6>
                <a class="mega-link" href="#solutions"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16M9 7h6M9 11h6M9 15h6"/></svg></span><div><strong>Hospitals</strong><span>Secondary & tertiary facilities</span></div></a>
                <a class="mega-link" href="#solutions"><span class="mega-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16"/></svg></span><div><strong>Clinics & Polyclinics</strong><span>Outpatient and primary care</span></div></a>
                <a class="mega-link" href="#solutions"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/></svg></span><div><strong>Health Networks</strong><span>Multi-facility groups</span></div></a>
                <a class="mega-link" href="#solutions"><span class="mega-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></span><div><strong>Government & Ministries</strong><span>State and national rollout</span></div></a>
              </div>
              <div class="mega-col">
                <h6>By role</h6>
                <a class="mega-link" href="#features"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span><div><strong>Doctors & Clinicians</strong><span>Fast notes, history, orders</span></div></a>
                <a class="mega-link" href="#features"><span class="mega-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg></span><div><strong>Nurses & Matrons</strong><span>Vitals, triage, ward rounds</span></div></a>
                <a class="mega-link" href="#features"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span><div><strong>Pharmacists</strong><span>Dispense, reorder, stock control</span></div></a>
                <a class="mega-link" href="#features"><span class="mega-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span><div><strong>Hospital Admin & Finance</strong><span>Billing, revenue, audits</span></div></a>
              </div>
              <div class="mega-col callout">
                <h6>Public sector</h6>
                <h4>DHIS2 & NHMIS ready</h4>
                <p>Built-in indicators and aggregate reporting that roll up cleanly for state and national health systems.</p>
                <a href="#integrations" class="btn btn-red btn-sm">Learn about reporting</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Developers -->
        <div class="nav-item">
          <button class="nav-trigger" aria-haspopup="true">Developers <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l4 4 4-4"/></svg></button>
          <div class="mega">
            <div class="mega-in">
              <div class="mega-col">
                <h6>Integrations</h6>
                <a class="mega-link" href="#integrations"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></span><div><strong>REST APIs</strong><span>Connect existing tools</span></div></a>
                <a class="mega-link" href="#integrations"><span class="mega-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/></svg></span><div><strong>Webhooks</strong><span>Real-time event streams</span></div></a>
                <a class="mega-link" href="#integrations"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg></span><div><strong>Third-Party Apps</strong><span>Plug into ecosystem</span></div></a>
              </div>
              <div class="mega-col">
                <h6>Standards</h6>
                <a class="mega-link" href="#integrations"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/></svg></span><div><strong>DHIS2 Standards</strong><span>Automated data export</span></div></a>
                <a class="mega-link" href="#integrations"><span class="mega-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg></span><div><strong>NHMIS Format</strong><span>Standardized forms</span></div></a>
                <a class="mega-link" href="#integrations"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></span><div><strong>HL7 / FHIR</strong><span>Interoperable records</span></div></a>
              </div>
              <div class="mega-col callout">
                <h6>Developer Platform</h6>
                <h4>Build on CoreHealth</h4>
                <p>Read our API documentation, explore client libraries and request sandbox credentials.</p>
                <a href="#integrations" class="btn btn-red btn-sm">Request API access</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Resources -->
        <div class="nav-item">
          <button class="nav-trigger" aria-haspopup="true">Resources <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l4 4 4-4"/></svg></button>
          <div class="mega compact">
            <div class="mega-in">
              <div class="mega-col">
                <h6>Learn</h6>
                <a class="mega-link" href="#"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V2H6.5A2.5 2.5 0 0 0 4 4.5z"/></svg></span><div><strong>Documentation</strong><span>Guides and how-tos</span></div></a>
                <a class="mega-link" href="#"><span class="mega-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></span><div><strong>API Reference</strong><span>Endpoints and examples</span></div></a>
              </div>
              <div class="mega-col">
                <h6>Stories</h6>
                <a class="mega-link" href="#impact"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span><div><strong>Case Studies</strong><span>Real deployments</span></div></a>
                <a class="mega-link" href="#"><span class="mega-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></span><div><strong>Blog</strong><span>Product and industry news</span></div></a>
              </div>
              <div class="mega-col">
                <h6>Help</h6>
                <a class="mega-link" href="#faq"><span class="mega-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/></svg></span><div><strong>FAQ</strong><span>Quick answers</span></div></a>
                <a class="mega-link" href="https://corestream.ng/public/contact" target="_blank" rel="noopener"><span class="mega-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span><div><strong>Support</strong><span>Talk to a human</span></div></a>
              </div>
            </div>
          </div>
        </div>

        <div class="nav-item"><a class="nav-trigger" href="#security">Security</a></div>
        <div class="nav-item"><a class="nav-trigger" href="#about">Company</a></div>
      </nav>

      <div class="head-cta">
        @auth
          <a class="login" href="{{ url('/home') }}">Dashboard</a>
        @else
          <a class="login" href="{{ route('login') }}">Log in</a>
        @endauth
        <a class="btn btn-ghost btn-sm hide-sm" href="#demo">Book a demo</a>
        <a class="btn btn-red btn-sm" href="#signup">Start free</a>
        <button class="burger" id="burger" aria-label="Open menu" aria-expanded="false">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
        </button>
      </div>
    </div>
  </header>

  <!-- Mobile Drawer -->
  <div class="drawer" id="drawer">
    <details>
      <summary>Product</summary>
      <a href="#features">Electronic Medical Records</a>
      <a href="#ai">AI Clinical Documentation</a>
      <a href="#features">Hospital Management</a>
      <a href="#features">Pharmacy &amp; Inventory</a>
      <a href="#features">Laboratory</a>
      <a href="#features">Billing &amp; Revenue</a>
      <a href="#features">Reporting &amp; Analytics</a>
    </details>
    <details>
      <summary>Solutions</summary>
      <a href="#solutions">Hospitals</a>
      <a href="#solutions">Clinics</a>
      <a href="#solutions">Health Networks</a>
      <a href="#solutions">Government &amp; Ministries</a>
      <a href="#solutions">Doctors</a>
      <a href="#solutions">Pharmacists</a>
    </details>
    <details>
      <summary>Integrations</summary>
      <a href="#integrations">DHIS2</a>
      <a href="#integrations">NHMIS</a>
      <a href="#integrations">REST APIs</a>
      <a href="#integrations">HL7 / FHIR</a>
    </details>
    <details>
      <summary>Resources</summary>
      <a href="#">Documentation</a>
      <a href="#">API Reference</a>
      <a href="#impact">Case Studies</a>
      <a href="#faq">FAQ</a>
    </details>
    <details>
      <summary>Company</summary>
      <a href="#about">About</a>
      <a href="#security">Security</a>
      <a href="https://corestream.ng/public/contact" target="_blank" rel="noopener">Contact</a>
    </details>
    <a class="btn btn-red" href="#signup">Start free</a>
    <a class="btn btn-ghost" href="#demo">Book a demo</a>
  </div>

  <main>
    <!-- Hero -->
    <section class="hero">
      <div class="container">
        <div class="hero-in">
          <span class="pill"><i>AI</i> Clinical documentation, now voice-powered</span>
          <h1>The operating system for <span class="grad">modern healthcare.</span></h1>
          <p class="lead">One platform for patient records, hospital operations, AI documentation and telemedicine. Digitize your facility, report to DHIS2 and NHMIS with confidence, and give clinicians their time back.</p>
          <div class="hero-cta">
            <a href="#signup" class="btn btn-red btn-lg">Start free</a>
            <a href="#demo" class="btn btn-ghost btn-lg">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--blue)">
                <polygon points="5 3 19 12 5 21 5 3"/>
              </svg> Try a live demo
            </a>
          </div>
          <div class="hero-note">
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>No credit card to start</span>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Guided onboarding</span>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>DHIS2 and NHMIS ready</span>
          </div>
        </div>

        <div class="frame-wrap" aria-hidden="true">
          <div class="float f1"><span class="dot"></span><div><b>AI summary ready</b><small>Consultation 09:30</small></div></div>
          <div class="float f2"><span class="dot r"></span><div><b>DHIS2 report synced</b><small>Monthly return submitted</small></div></div>
          <div class="frame">
            <div class="frame-bar"><i></i><i></i><i></i><span>CoreHealth · Hospital Dashboard (sample data)</span></div>
            <div class="app">
              <div class="side"><div class="on">Dashboard</div><div>Patients</div><div>Appointments</div><div>Pharmacy</div><div>Laboratory</div><div>Billing</div><div>Reports</div></div>
              <div class="main">
                <div class="kpis">
                  <div class="kpi"><small>Patients</small><b>1,284</b><em>&uarr; 12% this month</em></div>
                  <div class="kpi"><small>Appointments</small><b>342</b><em>&uarr; 8% this week</em></div>
                  <div class="kpi"><small>AI summaries</small><b>96</b><em class="r">Generated today</em></div>
                  <div class="kpi"><small>Bed occupancy</small><b>78%</b><em>Healthy</em></div>
                </div>
                <div class="row2">
                  <div class="card-s">
                    <h6>Patient activity <span>Weekly</span></h6>
                    <div class="bars"><i style="height:40%"></i><i class="b" style="height:65%"></i><i class="r" style="height:52%"></i><i class="b" style="height:78%"></i><i style="height:45%"></i><i class="b" style="height:92%"></i><i class="r" style="height:62%"></i></div>
                    <div class="lbl"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
                  </div>
                  <div class="card-s">
                    <h6>Recent patients</h6>
                    <div class="pt"><div class="av">AA</div><div><b>Amina A.</b><small>Cardiology · 09:30</small></div><span class="st">Active</span></div>
                    <div class="pt"><div class="av r">JO</div><div><b>James O.</b><small>General · 10:15</small></div><span class="st r">Pending</span></div>
                    <div class="pt"><div class="av">FB</div><div><b>Fatima B.</b><small>Pediatrics · 11:00</small></div><span class="st">Active</span></div>
                  </div>
                </div>
                <div class="ai-bar">
                  <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.4 5.6L20 10l-5.6 2.4L12 18l-2.4-5.6L4 10l5.6-2.4z"/></svg></div>
                  <p><b>AI Clinical Summary.</b> Stable vitals, mild hypertension. Suggested follow-up in 2 weeks. <i style="color:var(--muted)">Clinician review required.</i></p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Standards Strip -->
    <section class="strip">
      <div class="container">
        <p>Built for the standards health systems run on</p>
        <div class="strip-row"><span>DHIS2</span><span>NHMIS</span><span>HL7</span><span>FHIR</span><span>REST API</span><span>WeCare247</span></div>
      </div>
    </section>

    <!-- Features Bento -->
    <section class="section" id="features">
      <div class="container">
        <div class="head">
          <span class="eyebrow">The platform</span>
          <h2>Everything your facility runs on, in one place.</h2>
          <p>Replace disconnected tools and paper registers with a single, secure system that clinicians, pharmacists, lab staff and admin teams actually want to use.</p>
        </div>
        <div class="bento">
          <div class="b-card b-wide">
            <div class="b-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
            <h3>Electronic medical records</h3>
            <p>One shared, longitudinal record for every patient. Clean timeline, fast search, allergies, vitals, previous visits and attachments right where clinicians expect them.</p>
          </div>
          <div class="b-card dark">
            <div class="b-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg></div>
            <h3>AI clinical documentation</h3>
            <p>Voice-to-text dictation and auto-summaries draft structured notes so doctors spend time with patients, not screens.</p>
          </div>
          <div class="b-card">
            <div class="b-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div>
            <h3>Hospital management &amp; beds</h3>
            <p>Admissions, transfers, discharges and ward occupancy in real time. Never lose track of a bed or a handoff.</p>
          </div>
          <div class="b-card">
            <div class="b-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg></div>
            <h3>Smart pharmacy &amp; inventory</h3>
            <p>Live inventory, expiry tracking, reorder alerts and closed-loop dispensing linked directly to doctor prescriptions.</p>
          </div>
          <div class="b-card">
            <div class="b-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg></div>
            <h3>Diagnostic laboratory</h3>
            <p>Order tests, manage specimens, enter results and release verified reports straight into the consultation view.</p>
          </div>
          <div class="b-card b-3">
            <div class="b-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <h3>Billing, claims &amp; revenue</h3>
            <p>Itemised invoicing, deposit tracking, NHIS/HMO claims management and clear daily cashier reconciliations.</p>
          </div>
          <div class="b-card b-3">
            <div class="b-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
            <h3>Reporting &amp; analytics</h3>
            <p>Real-time dashboards and custom reports for clinical, operational and financial decisions, from the ward to the ministry.</p>
          </div>
          <div class="b-card b-3">
            <div class="b-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
            <h3>Role-based access control</h3>
            <p>Every user sees exactly what their role requires, with every action recorded in an audit trail.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- AI Section -->
    <section class="section soft" id="ai">
      <div class="container split">
        <div>
          <span class="eyebrow">AI-assisted care</span>
          <h2>Less typing. More time with patients.</h2>
          <p>CoreHealth puts AI inside the clinical workflow as an assistant, not a replacement for professional judgment. Clinicians review and approve everything that enters the record.</p>
          <ul class="checks">
            <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span><div><strong>Voice-to-text medical dictation</strong><span>Dictate naturally. Notes are transcribed and structured into the patient record.</span></div></li>
            <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span><div><strong>AI-generated clinical summaries</strong><span>Concise summaries from consultations, labs and notes, ready for review.</span></div></li>
            <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span><div><strong>Smart workflows</strong><span>Task routing and automated alerts keep every department in step.</span></div></li>
            <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span><div><strong>Telemedicine with WeCare247</strong><span>Secure video consultations and remote follow-ups inside the same record.</span></div></li>
          </ul>
          <div class="cta-row"><a href="#demo" class="btn btn-red">Try the AI demo</a><a href="#signup" class="btn btn-ghost">Start free</a></div>
        </div>
        <div class="mock" aria-hidden="true">
          <div class="mock-h"><div><h4>WeCare247 · Telemedicine consultation</h4><small>AI-assisted documentation (sample)</small></div><span class="live">LIVE</span></div>
          <div class="mock-b">
            <div class="tx"><small>Voice transcript</small><p>“Patient reports persistent headache for three days, mild fever, no neck stiffness. Vitals stable. Recommend malaria test and hydration.”</p></div>
            <div class="sm"><small>AI clinical summary · draft</small><p><b>Assessment:</b> Suspected uncomplicated malaria. <b>Plan:</b> Order RDT, treat per protocol, hydration advice. <b>Follow-up:</b> 48 hours or sooner if symptoms worsen.</p></div>
            <div class="note-ai">Draft only. Requires clinician review and sign-off.</div>
          </div>
        </div>
      </div>
    </section>

    <!-- How It Works -->
    <section class="section" id="how">
      <div class="container">
        <div class="head">
          <span class="eyebrow">Get live fast</span>
          <h2>From paper to digital without disrupting care.</h2>
          <p>A guided rollout designed around real clinical workflows.</p>
        </div>
        <div class="steps">
          <div class="step"><div class="n">1</div><h3>Set up your facility</h3><p>Create your organisation, departments, roles and service catalogue. Import existing patient data with our team's help.</p></div>
          <div class="step"><div class="n">2</div><h3>Train and go live</h3><p>Role-based training for clinicians, pharmacy, lab and front desk, with phased go-live by department.</p></div>
          <div class="step"><div class="n">3</div><h3>Report and improve</h3><p>Push data to DHIS2 and NHMIS and use live dashboards to improve quality, stock and revenue.</p></div>
        </div>
      </div>
    </section>

    <!-- Solutions Tabs -->
    <section class="section soft" id="solutions">
      <div class="container">
        <div class="head">
          <span class="eyebrow">Solutions</span>
          <h2>Built for every kind of health organisation.</h2>
          <p>Pick your setting and see how CoreHealth fits.</p>
        </div>
        <div class="tabs" role="tablist" aria-label="Solutions">
          <button class="tab" role="tab" aria-selected="true" data-t="p1">Hospitals</button>
          <button class="tab" role="tab" aria-selected="false" data-t="p2">Clinics</button>
          <button class="tab" role="tab" aria-selected="false" data-t="p3">Government &amp; Ministries</button>
          <button class="tab" role="tab" aria-selected="false" data-t="p4">Health Networks</button>
        </div>

        <div class="panel on" id="p1" role="tabpanel">
          <div>
            <h3>Run the whole hospital from one system.</h3>
            <p>From front desk to ward to pharmacy, every department works from the same live record.</p>
            <ul>
              <li>Admissions, transfers, discharge and bed management</li>
              <li>Integrated lab, pharmacy and billing</li>
              <li>AI-assisted documentation for doctors</li>
              <li>DHIS2 and NHMIS reporting built in</li>
            </ul>
            <a href="#demo" class="btn btn-red">Book a hospital demo</a>
          </div>
          <div class="stat-stack">
            <div class="stat-box hl"><small>Unified</small><b>EMR + HIS + Pharmacy + Lab</b></div>
            <div class="stat-box"><small>Reporting</small><b>DHIS2 · NHMIS</b></div>
          </div>
        </div>
        <div class="panel" id="p2" role="tabpanel">
          <div>
            <h3>Digitize your clinic in weeks, not months.</h3>
            <p>Lightweight, affordable tools for outpatient and primary care teams.</p>
            <ul>
              <li>Fast registration and appointment scheduling</li>
              <li>Consultation notes with voice dictation</li>
              <li>Prescriptions, simple billing and stock tracking</li>
              <li>Telemedicine follow-ups with WeCare247</li>
            </ul>
            <a href="#signup" class="btn btn-red">Start free</a>
          </div>
          <div class="stat-stack">
            <div class="stat-box hl"><small>Ideal for</small><b>Outpatient &amp; primary care</b></div>
            <div class="stat-box"><small>Telemedicine</small><b>WeCare247 built in</b></div>
          </div>
        </div>
        <div class="panel" id="p3" role="tabpanel">
          <div>
            <h3>Population-level visibility for health authorities.</h3>
            <p>Standardised facility data that rolls up cleanly for planning and oversight.</p>
            <ul>
              <li>DHIS2 integration for national reporting</li>
              <li>NHMIS-compliant data collection</li>
              <li>Cross-facility analytics and dashboards</li>
              <li>Role-based access and full audit trails</li>
            </ul>
            <a href="#demo" class="btn btn-red">Talk to our public-sector team</a>
          </div>
          <div class="stat-stack">
            <div class="stat-box hl"><small>Deployed</small><b>Plateau State, Nigeria</b></div>
            <div class="stat-box"><small>Standards</small><b>DHIS2 · NHMIS · FHIR</b></div>
          </div>
        </div>
        <div class="panel" id="p4" role="tabpanel">
          <div>
            <h3>One record across every facility in your network.</h3>
            <p>Coordinate care, share records securely and manage the group centrally.</p>
            <ul>
              <li>Multi-facility administration</li>
              <li>Shared patient records with permissions</li>
              <li>Centralised reporting and finance</li>
              <li>Scales from a handful of sites to a whole system</li>
            </ul>
            <a href="#demo" class="btn btn-red">Plan a network rollout</a>
          </div>
          <div class="stat-stack">
            <div class="stat-box hl"><small>Architecture</small><b>Multi-facility, scalable</b></div>
            <div class="stat-box"><small>Governance</small><b>Central control, local access</b></div>
          </div>
        </div>
      </div>
    </section>

    <!-- Integrations -->
    <section class="section" id="integrations">
      <div class="container">
        <div class="head">
          <span class="eyebrow">Interoperability</span>
          <h2>Connects to the systems you already use.</h2>
          <p>Open standards and APIs make CoreHealth easy to plug into national platforms, lab equipment and existing hospital software.</p>
        </div>
        <div class="int-grid">
          <div class="int"><span class="tag">DHIS2</span><p>Automated data submission to national health information systems.</p></div>
          <div class="int"><span class="tag">NHMIS</span><p>Compliant data collection and reporting for Nigerian health facilities.</p></div>
          <div class="int"><span class="tag">REST APIs</span><p>Connect legacy software, lab equipment and third-party tools.</p></div>
          <div class="int"><span class="tag">HL7 / FHIR</span><p>Standards-based exchange for interoperable records.</p></div>
        </div>
      </div>
    </section>

    <!-- Security -->
    <section class="section sec" id="security">
      <div class="container">
        <div class="head">
          <span class="eyebrow">Security &amp; trust</span>
          <h2>Patient data deserves serious protection.</h2>
          <p>Security and privacy are designed into every layer of CoreHealth.</p>
        </div>
        <div class="sec-grid">
          <div class="sec-card">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <h4>Role-based permissions</h4>
            <p>Every role sees only the data they need to do their job, with strict separation between clinical and administrative records.</p>
          </div>
          <div class="sec-card">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <h4>Encrypted in transit and at rest</h4>
            <p>All data is encrypted using industry-standard protocols, with regular backups and disaster recovery procedures.</p>
          </div>
          <div class="sec-card">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <h4>Complete audit trail</h4>
            <p>Every record view, edit, prescription and dispensing event is permanently logged with timestamp and user details.</p>
          </div>
          <div class="sec-card">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/></svg>
            <h4>Privacy by design</h4>
            <p>Built to meet national data-protection regulations, with fine-grained consent options and patient data rights built in.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Impact -->
    <section class="section" id="impact">
      <div class="container">
        <div class="head">
          <span class="eyebrow">Proven in practice</span>
          <h2>Measurable improvements from day one.</h2>
          <p>Facilities using CoreHealth see real gains in staff productivity, patient experience and revenue capture.</p>
        </div>
        <div class="proof-row">
          <div class="stat"><b class="grad">70%</b><p>Faster consultation notes with AI-assisted dictation</p></div>
          <div class="stat"><b class="grad">&minus;40%</b><p>Reduction in average patient wait times</p></div>
          <div class="stat"><b class="grad">99.8%</b><p>On-time monthly reporting to DHIS2 and NHMIS</p></div>
          <div class="stat"><b class="grad">24/7</b><p>Dedicated technical support and ongoing updates</p></div>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section class="section soft" id="faq">
      <div class="container">
        <div class="head">
          <span class="eyebrow">Questions?</span>
          <h2>Everything you need to know.</h2>
          <p>Quick answers about getting started with CoreHealth.</p>
        </div>
        <div class="faq-grid">
          <details><summary>How long does a typical implementation take?</summary><p>Most facilities go live within 2 to 4 weeks. We configure your departments, import existing records and run hands-on training so your team feels confident on day one.</p></details>
          <details><summary>Does CoreHealth work with DHIS2 and NHMIS?</summary><p>Yes. CoreHealth is built to submit facility data to DHIS2 and to support NHMIS reporting requirements.</p></details>
          <details><summary>Is the AI making clinical decisions?</summary><p>No. AI drafts transcripts and summaries to save time. Clinicians review, edit and approve everything before it becomes part of the record.</p></details>
          <details><summary>Can we connect our existing lab equipment or software?</summary><p>CoreHealth exposes REST APIs and supports HL7/FHIR-style exchange. Talk to our team about your specific equipment and systems.</p></details>
          <details><summary>Do you offer training and support?</summary><p>Yes. Role-based onboarding is part of every rollout, with ongoing support available through our team.</p></details>
        </div>
      </div>
    </section>

    <!-- Final CTA -->
    <section class="section" id="demo" style="padding-bottom:0">
      <div class="container">
        <div class="final" id="signup">
          <h2>Bring your facility into the digital age.</h2>
          <p>Create your free account in minutes, or book a guided demo with our team.</p>
          <form class="signup" action="https://corestream.ng/public/contact" method="get">
            <input type="email" name="email" placeholder="Work email address" aria-label="Work email" required />
            <button class="btn btn-red btn-lg" type="submit">Start free</button>
          </form>
          <div class="or">Prefer a walkthrough? <a href="https://corestream.ng/public/contact" target="_blank" rel="noopener">Book a live demo &rarr;</a></div>
        </div>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer class="footer" id="about">
    <div class="container">
      <div class="f-grid">
        <div class="f-brand">
          <a href="{{ url('/') }}" class="logo">
            <span class="logo-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></span>
            Core<b>Health</b>
          </a>
          <p>The AI-powered healthcare management ecosystem by Corestream Networks and Robotics Limited.</p>
          <a href="#signup" class="btn btn-red btn-sm">Start free</a>
        </div>
        <div class="f-col"><h5>Product</h5><ul><li><a href="#features">Features</a></li><li><a href="#ai">AI Documentation</a></li><li><a href="#integrations">Integrations</a></li><li><a href="#security">Security</a></li></ul></div>
        <div class="f-col"><h5>Solutions</h5><ul><li><a href="#solutions">Hospitals</a></li><li><a href="#solutions">Clinics</a></li><li><a href="#solutions">Government</a></li><li><a href="#solutions">Health Networks</a></li></ul></div>
        <div class="f-col"><h5>Resources</h5><ul><li><a href="#">Documentation</a></li><li><a href="#">API Reference</a></li><li><a href="#faq">FAQ</a></li><li><a href="https://corestream.ng/public/contact" target="_blank" rel="noopener">Support</a></li></ul></div>
        <div class="f-col f-contact"><h5>Contact</h5><ul><li>Jos, Plateau State, Nigeria</li><li>hello@corestream.ng</li><li>+234 (0) 816 087 6560</li></ul></div>
      </div>
      <div class="f-bottom"><span>&copy; {{ date('Y') }} Corestream Networks and Robotics Limited. All rights reserved.</span><div><a href="#">Privacy Policy</a><a href="#">Terms of Service</a><a href="#security">Security</a></div></div>
    </div>
  </footer>

  <div class="m-cta">
    <a class="btn btn-ghost" href="#demo">Book demo</a>
    <a class="btn btn-red" href="#signup">Start free</a>
  </div>

@else
  {{-- =====================================================================
       BRANDED MODE: CLINICAL HEALTHCARE FACILITY PORTAL
       ===================================================================== --}}
  <div class="announce">
    <span class="tag">24/7 CARE</span>{{ $siteName }} Emergency and Inpatient Services are open around the clock.
    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $sitePhone) }}">Call Emergency: {{ $sitePhone }} &rarr;</a>
  </div>

  <!-- Hospital Branded Header -->
  <header class="header">
    <div class="container header-in">
      <a href="{{ url('/') }}" class="hospital-brand-combo" aria-label="{{ $siteName }}">
        @if($siteLogo)
          <img src="{{ $siteLogo }}" alt="{{ $siteName }}" class="hospital-brand-img" />
        @else
          <span class="logo-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
            </svg>
          </span>
        @endif
        <div class="hospital-brand-text">
          <span class="hospital-brand-title">{{ $siteName }}</span>
          <span class="hospital-brand-sub">Powered by Core<b>Health</b></span>
        </div>
      </a>

      <nav class="nav" aria-label="Facility Navigation">
        <div class="nav-item"><a class="nav-trigger" href="#services">Clinical Services</a></div>
        <div class="nav-item"><a class="nav-trigger" href="#telehealth">Telemedicine &amp; EHR</a></div>
        <div class="nav-item"><a class="nav-trigger" href="#standards">Care Standards</a></div>
        <div class="nav-item"><a class="nav-trigger" href="#faq">Patient &amp; Visitor Guide</a></div>
        <div class="nav-item"><a class="nav-trigger" href="#contact">Contact &amp; Hours</a></div>
      </nav>

      <div class="head-cta">
        @auth
          <a class="btn btn-red btn-sm" href="{{ url('/home') }}">Enter Dashboard</a>
        @else
          <a class="login" href="{{ route('login') }}">Staff Sign In</a>
          <a class="btn btn-red btn-sm" href="{{ route('login') }}">Staff Portal</a>
        @endauth
        <button class="burger" id="burger" aria-label="Open menu" aria-expanded="false">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
        </button>
      </div>
    </div>
  </header>

  <!-- Hospital Branded Mobile Drawer -->
  <div class="drawer" id="drawer">
    <a href="#services" class="drawer-direct-link">Clinical Services &amp; Departments</a>
    <a href="#telehealth" class="drawer-direct-link">Telemedicine &amp; Digital EHR</a>
    <a href="#standards" class="drawer-direct-link">Patient Care Standards</a>
    <a href="#faq" class="drawer-direct-link">Patient &amp; Visitor Guide</a>
    <a href="#contact" class="drawer-direct-link">Visiting Hours &amp; Contact</a>
    @auth
      <a class="btn btn-red" href="{{ url('/home') }}">Enter Staff Workbench</a>
    @else
      <a class="btn btn-red" href="{{ route('login') }}">Staff Portal Login</a>
      <a class="btn btn-ghost" href="tel:{{ preg_replace('/[^0-9+]/', '', $sitePhone) }}">Call Emergency Desk</a>
    @endauth
  </div>

  <main>
    <!-- Hospital Hero -->
    <section class="hero">
      <div class="container">
        <div class="hero-in">
          <span class="pill"><i>24/7</i> Accredited Healthcare &amp; Medical Excellence</span>
          <h1>Excellence in healthcare for you and <span class="grad">your family.</span></h1>
          <p class="lead">{{ $siteDescription ?: ($siteName . ' provides world-class inpatient and outpatient care, 24/7 emergency services, modern diagnostics, and secure digital health records.') }}</p>
          <div class="hero-cta">
            @auth
              <a href="{{ url('/home') }}" class="btn btn-red btn-lg">Open Staff Workbench</a>
            @else
              <a href="{{ route('login') }}" class="btn btn-red btn-lg">Staff Portal Login</a>
            @endauth
            <a href="#services" class="btn btn-ghost btn-lg">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--blue)">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
              </svg> Explore Clinical Services
            </a>
          </div>
          <div class="hero-note">
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>24/7 Emergency &amp; Trauma Care</span>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Qualified Specialists &amp; Nurses</span>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Confidential Digital Health Records</span>
          </div>
        </div>

        <div class="frame-wrap" aria-hidden="true">
          <div class="float f1"><span class="dot"></span><div><b>Specialist Clinics Active</b><small>Triage &amp; Consultations</small></div></div>
          <div class="float f2"><span class="dot r"></span><div><b>Emergency Unit 24/7</b><small>Ready for intake</small></div></div>
          <div class="frame">
            <div class="frame-bar"><i></i><i></i><i></i><span>{{ $siteName }} · Hospital Operations &amp; Clinical Cockpit</span></div>
            <div class="app">
              <div class="side"><div class="on">Overview</div><div>Consultations</div><div>Wards &amp; Beds</div><div>Dispensary</div><div>Laboratory</div><div>Billing</div></div>
              <div class="main">
                <div class="kpis">
                  <div class="kpi"><small>Outpatient Triage</small><b>Active</b><em>Continuous intake</em></div>
                  <div class="kpi"><small>Emergency Desk</small><b>24/7</b><em class="r">Response ready</em></div>
                  <div class="kpi"><small>Laboratory Tests</small><b>Fast-Track</b><em>Automated analyzers</em></div>
                  <div class="kpi"><small>Inpatient Care</small><b>Monitored</b><em>Clinical rounds</em></div>
                </div>
                <div class="row2">
                  <div class="card-s">
                    <h6>Patient Care Inflow <span>Weekly Trend</span></h6>
                    <div class="bars"><i style="height:45%"></i><i class="b" style="height:65%"></i><i class="r" style="height:55%"></i><i class="b" style="height:80%"></i><i style="height:50%"></i><i class="b" style="height:90%"></i><i class="r" style="height:60%"></i></div>
                    <div class="lbl"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
                  </div>
                  <div class="card-s">
                    <h6>Active Care Clinics</h6>
                    <div class="pt"><div class="av">AA</div><div><b>Amina A.</b><small>Cardiology Clinic · Outpatient</small></div><span class="st">Active</span></div>
                    <div class="pt"><div class="av r">JO</div><div><b>James O.</b><small>Emergency Care · Triage</small></div><span class="st r">Supervised</span></div>
                    <div class="pt"><div class="av">FB</div><div><b>Fatima B.</b><small>Pediatrics &amp; Child Health</small></div><span class="st">Active</span></div>
                  </div>
                </div>
                <div class="ai-bar">
                  <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.4 5.6L20 10l-5.6 2.4L12 18l-2.4-5.6L4 10l5.6-2.4z"/></svg></div>
                  <p><b>Integrated Longitudinal Health Record.</b> Patient vitals, diagnostic results, and prescriptions synchronize across all wards and clinics in real time.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Hospital Clinical Strip -->
    <section class="strip">
      <div class="container">
        <p>Specialized clinical departments and patient-first medical services</p>
        <div class="strip-row">
          <span>24/7 Emergency &amp; Trauma</span>
          <span>Outpatient Clinics</span>
          <span>Inpatient Wards</span>
          <span>Maternity &amp; Pediatrics</span>
          <span>Diagnostic Laboratory</span>
          <span>Dispensary &amp; Pharmacy</span>
        </div>
      </div>
    </section>

    <!-- Hospital Clinical Bento Grid -->
    <section class="section" id="services">
      <div class="container">
        <div class="head">
          <span class="eyebrow">Clinical Departments</span>
          <h2>Comprehensive healthcare services under one roof.</h2>
          <p>From preventative medicine and maternal care to rapid diagnostics and round-the-clock emergency care, our clinical staff delivers compassionate, evidence-based treatment.</p>
        </div>
        <div class="bento">
          <div class="b-card b-wide">
            <div class="b-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
            <h3>Outpatient &amp; Specialist Consultations</h3>
            <p>Consult with certified specialists in General Medicine, Cardiology, Obstetrics &amp; Gynecology, Pediatrics, and Surgery with streamlined triage and minimal wait times.</p>
          </div>
          <div class="b-card dark">
            <div class="b-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg></div>
            <h3>24/7 Emergency &amp; Trauma Care</h3>
            <p>Our dedicated emergency response unit is staffed 24 hours a day with rapid resuscitation suites and immediate access to life-saving interventions.</p>
          </div>
          <div class="b-card">
            <div class="b-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div>
            <h3>Modern Inpatient Wards &amp; ICU</h3>
            <p>Comfortable inpatient accommodation, continuous vital signs telemetry, dedicated nurse call systems, and personalized nutritional care.</p>
          </div>
          <div class="b-card">
            <div class="b-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></div>
            <h3>Maternity &amp; Child Health Center</h3>
            <p>Complete maternal-fetal care including comprehensive antenatal clinics, modern labor &amp; delivery suites, and neonatal health monitoring.</p>
          </div>
          <div class="b-card">
            <div class="b-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg></div>
            <h3>Accredited Diagnostic Laboratory</h3>
            <p>Automated clinical chemistry, hematology, parasitology, and microbiology with rapid turn-around and verified digital result release.</p>
          </div>
          <div class="b-card b-3">
            <div class="b-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg></div>
            <h3>Pharmacy &amp; Medication Counseling</h3>
            <p>Registered pharmacists dispensing authenticated medications, dosage guidance, and seamless digital prescription refills.</p>
          </div>
          <div class="b-card b-3">
            <div class="b-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16M9 7h6M9 11h6M9 15h6"/></svg></div>
            <h3>Modern Surgical Theatres</h3>
            <p>State-of-the-art sterile surgical suites equipped for elective, minimally invasive, and emergency surgical operations.</p>
          </div>
          <div class="b-card b-3">
            <div class="b-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
            <h3>Confidential Digital Records</h3>
            <p>Your longitudinal medical records are protected under rigorous healthcare privacy standards, ensuring safe continuity of care.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Hospital Connected Care & Telehealth -->
    <section class="section soft" id="telehealth">
      <div class="container split">
        <div>
          <span class="eyebrow">Connected Care</span>
          <h2>In-person clinical care, connected to your everyday life.</h2>
          <p>{{ $siteName }} brings modern digital healthcare infrastructure to your care journey, making medical consultations and results easily accessible.</p>
          <ul class="checks">
            <li>
              <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
              <div><strong>Integrated Electronic Medical Records</strong><span>Your clinical team has immediate context of your medical history, allergies, and ongoing treatment.</span></div>
            </li>
            <li>
              <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
              <div><strong>Virtual Follow-Up with WeCare247</strong><span>Remote video consultations and specialist follow-ups from the comfort of your home.</span></div>
            </li>
            <li>
              <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
              <div><strong>Fast Diagnostic Results Delivery</strong><span>Verified laboratory and radiology results routed promptly to your attending physician.</span></div>
            </li>
            <li>
              <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
              <div><strong>HMO &amp; Insurance Pre-Authorization</strong><span>Seamless claims processing and real-time eligibility verification for hassle-free discharge.</span></div>
            </li>
          </ul>
          <div class="cta-row">
            @auth
              <a href="{{ url('/home') }}" class="btn btn-red">Access Dashboard</a>
            @else
              <a href="{{ route('login') }}" class="btn btn-red">Staff Portal Login</a>
            @endauth
            <a href="#contact" class="btn btn-ghost">Contact Reception</a>
          </div>
        </div>
        <div class="mock" aria-hidden="true">
          <div class="mock-h">
            <div><h4>{{ $siteName }} · Patient Care Record</h4><small>Clinical care summary preview</small></div>
            <span class="live">VERIFIED</span>
          </div>
          <div class="mock-b">
            <div class="tx">
              <small>Clinical Consultation Note</small>
              <p>“Patient reviewed in Outpatient Clinic. Vital signs stable, diagnostic parameters within normal ranges. Prescribed medication course dispatched by pharmacy.”</p>
            </div>
            <div class="sm">
              <small>Physician Care Plan</small>
              <p><b>Clinical Assessment:</b> Routine follow-up satisfactory. <b>Medication:</b> Completed dispensation. <b>Next Scheduled Visit:</b> 4 weeks or upon clinical indication.</p>
            </div>
            <div class="note-ai">Confidential patient medical record · Handled under strict healthcare privacy standards.</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Hospital Standards & Confidentiality -->
    <section class="section sec" id="standards">
      <div class="container">
        <div class="head">
          <span class="eyebrow">Quality &amp; Safety</span>
          <h2>Your health, safety, and privacy are our highest priorities.</h2>
          <p>We adhere to strict clinical governance, continuous infection control, and regulatory medical standards across all departments.</p>
        </div>
        <div class="sec-grid">
          <div class="sec-card">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <h4>Qualified Clinical Specialists</h4>
            <p>Our team comprises licensed medical practitioners, experienced registered nurses, certified laboratory scientists, and registered pharmacists.</p>
          </div>
          <div class="sec-card">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <h4>Patient Data Confidentiality</h4>
            <p>Your personal health records are safeguarded with enterprise-grade encryption and full compliance with national data protection regulations.</p>
          </div>
          <div class="sec-card">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            <h4>Infection Prevention &amp; Hygiene</h4>
            <p>Rigorous environmental sanitization protocols, single-use sterile consumables, and strict aseptic surgical practices in all operating rooms.</p>
          </div>
          <div class="sec-card">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <h4>24/7 Continuity of Care</h4>
            <p>Emergency medical care, patient triage, and critical inpatient observation run continuously without disruption 365 days a year.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Patient & Visitor FAQ -->
    <section class="section soft" id="faq">
      <div class="container">
        <div class="head">
          <span class="eyebrow">Patient &amp; Visitor Information</span>
          <h2>Frequently Asked Questions</h2>
          <p>Everything you need to know about receiving care at {{ $siteName }}.</p>
        </div>
        <div class="faq-grid">
          <details>
            <summary>What are the visiting hours for inpatient wards?</summary>
            <p>General visiting hours are 11:00 AM &ndash; 1:00 PM and 4:30 PM &ndash; 7:00 PM daily. Intensive care and neonatal units follow specific visitation schedules to ensure patient recovery and rest.</p>
          </details>
          <details>
            <summary>How do I access my laboratory and diagnostic results?</summary>
            <p>Laboratory and imaging results are reviewed by your physician and archived directly into your digital health record. You can collect physical reports at reception or access them through your consultation summary.</p>
          </details>
          <details>
            <summary>Which health insurance (HMO) plans and payment methods do you accept?</summary>
            <p>We accept major National Health Insurance (NHIS) programs, private HMO providers, corporate medical retainerships, debit cards (POS), electronic bank transfers, and cash.</p>
          </details>
          <details>
            <summary>How can I schedule a consultation with a specialist?</summary>
            <p>Appointments can be booked by calling our reception desk, visiting in person, or through a referral from your attending primary care physician.</p>
          </details>
          <details>
            <summary>What should I bring when coming for an admission or emergency?</summary>
            <p>Please bring a valid form of identification, your hospital registration card (if already registered), your insurance/HMO card, and any ongoing medications you are currently taking.</p>
          </details>
        </div>
      </div>
    </section>

    <!-- Hospital Contact & Staff CTA Banner -->
    <section class="section" id="contact" style="padding-bottom:0">
      <div class="container">
        <div class="final">
          <h2>Dedicated to your health, every hour of every day.</h2>
          <p>{{ $siteName }} is located at {{ $siteAddress }}. Reach our emergency line or log in to the clinical workbench.</p>
          <div class="cta-row" style="justify-content:center;margin-top:24px;">
            @auth
              <a href="{{ url('/home') }}" class="btn btn-red btn-lg">Open Staff Workbench</a>
            @else
              <a href="{{ route('login') }}" class="btn btn-red btn-lg">Hospital Staff Login</a>
            @endauth
            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $sitePhone) }}" class="btn btn-ghost btn-lg">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--blue)">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
              </svg> Call Emergency Desk
            </a>
          </div>
          <div class="or" style="margin-top:20px;">Questions or inquiries? Email: <a href="mailto:{{ $siteEmail }}">{{ $siteEmail }}</a></div>
        </div>
      </div>
    </section>
  </main>

  <!-- Hospital Branded Footer -->
  <footer class="footer" id="about">
    <div class="container">
      <div class="f-grid">
        <div class="f-brand">
          <a href="{{ url('/') }}" class="hospital-brand-combo">
            @if($siteLogo)
              <img src="{{ $siteLogo }}" alt="{{ $siteName }}" class="hospital-brand-img" />
            @else
              <span class="logo-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                </svg>
              </span>
            @endif
            <div class="hospital-brand-text">
              <span class="hospital-brand-title">{{ $siteName }}</span>
              <span class="hospital-brand-sub">Powered by Core<b>Health</b></span>
            </div>
          </a>
          <p>Delivering high-quality, compassionate clinical care, inpatient services, and 24/7 emergency response.</p>
          @auth
            <a href="{{ url('/home') }}" class="btn btn-red btn-sm">Staff Dashboard</a>
          @else
            <a href="{{ route('login') }}" class="btn btn-red btn-sm">Staff Login</a>
          @endauth
        </div>
        <div class="f-col">
          <h5>Clinical Services</h5>
          <ul>
            <li><a href="#services">Outpatient Clinics</a></li>
            <li><a href="#services">Emergency &amp; Trauma</a></li>
            <li><a href="#services">Inpatient Wards</a></li>
            <li><a href="#services">Maternity Care</a></li>
            <li><a href="#services">Diagnostic Laboratory</a></li>
          </ul>
        </div>
        <div class="f-col">
          <h5>Patient Guide</h5>
          <ul>
            <li><a href="#faq">Visiting Hours</a></li>
            <li><a href="#faq">Admission Guide</a></li>
            <li><a href="#telehealth">Telemedicine Follow-Up</a></li>
            <li><a href="#standards">Patient Confidentiality</a></li>
          </ul>
        </div>
        <div class="f-col">
          <h5>Staff &amp; Admin</h5>
          <ul>
            @auth
              <li><a href="{{ url('/home') }}">Doctor &amp; Clinical Workbench</a></li>
              <li><a href="{{ url('/home') }}">Nursing Station</a></li>
              <li><a href="{{ url('/home') }}">Pharmacy Dispensary</a></li>
              <li><a href="{{ url('/home') }}">Hospital Billing</a></li>
            @else
              <li><a href="{{ route('login') }}">Staff Portal Login</a></li>
              <li><a href="{{ route('login') }}">Doctor Sign In</a></li>
              <li><a href="{{ route('login') }}">Nurse Triage Login</a></li>
              <li><a href="{{ route('login') }}">Billing Cashier</a></li>
            @endauth
          </ul>
        </div>
        <div class="f-col f-contact">
          <h5>Contact &amp; Location</h5>
          <ul>
            <li>{{ $siteAddress }}</li>
            <li>{{ $siteEmail }}</li>
            <li>{{ $sitePhone }}</li>
          </ul>
        </div>
      </div>
      <div class="f-bottom">
        <span>&copy; {{ date('Y') }} {{ $siteName }}. Powered by CoreHealth AI Healthcare Ecosystem.</span>
        <div>
          <a href="#standards">Patient Privacy</a>
          <a href="#standards">Care Standards</a>
          <a href="#contact">Emergency Contact</a>
        </div>
      </div>
    </div>
  </footer>

  <div class="m-cta">
    @auth
      <a class="btn btn-red" href="{{ url('/home') }}">Staff Workbench</a>
    @else
      <a class="btn btn-ghost" href="tel:{{ preg_replace('/[^0-9+]/', '', $sitePhone) }}">Call Emergency</a>
      <a class="btn btn-red" href="{{ route('login') }}">Staff Login</a>
    @endauth
  </div>
@endif

<!-- Standalone Welcome Scripts -->
<script src="{{ asset('js/welcome.js') }}?v={{ config('app.version', '2.6') }}"></script>
</body>
</html>

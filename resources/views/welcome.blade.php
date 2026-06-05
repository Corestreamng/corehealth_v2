<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ appsettings()->site_name ?? env('APP_NAME', 'Hospital') }} — CoreHealth EMR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --cs-green: #006B3F;
            --cs-green-light: #00895a;
            --cs-green-dark: #004d2e;
            --cs-accent: #e8f5ee;
            --hos-color: {{ appsettings()->hos_color ?? '#2563eb' }};
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --bg-light: #f8fafc;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; color: var(--text-dark); overflow-x: hidden; }
        /* NAV */
        .welcome-nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; padding: 16px 0; transition: all 0.3s; }
        .welcome-nav.scrolled { background: rgba(255,255,255,0.95); backdrop-filter: blur(12px); box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 10px 0; }
        .nav-inner { max-width: 1200px; margin: 0 auto; padding: 0 24px; display: flex; align-items: center; justify-content: space-between; }
        .nav-logos { display: flex; align-items: center; gap: 12px; }
        .nav-logos img { height: 36px; }
        .nav-logos .divider { width: 1px; height: 28px; background: rgba(0,107,63,0.2); }
        .nav-hospital-name { font-weight: 700; font-size: 1.15rem; color: var(--hos-color); }
        .nav-powered { font-size: 0.7rem; color: var(--text-muted); font-weight: 400; display: flex; align-items: center; gap: 4px; }
        .nav-powered img { height: 14px; opacity: 0.6; }
        .nav-links { display: flex; align-items: center; gap: 24px; }
        .nav-links a { text-decoration: none; color: var(--text-dark); font-weight: 500; font-size: 0.95rem; transition: color 0.2s; }
        .nav-links a:hover { color: var(--cs-green); }
        .btn-login { display: inline-flex; align-items: center; gap: 8px; padding: 10px 28px; background: var(--cs-green); color: #fff !important; border-radius: 8px; font-weight: 600; text-decoration: none; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,107,63,0.25); }
        .btn-login:hover { background: var(--cs-green-dark); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,107,63,0.3); }
        /* HERO */
        .hero { min-height: 100vh; display: flex; align-items: center; position: relative; background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 30%, #f0f9ff 70%, #eff6ff 100%); overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -200px; right: -200px; width: 600px; height: 600px; background: radial-gradient(circle, rgba(0,107,63,0.06) 0%, transparent 70%); border-radius: 50%; }
        .hero::after { content: ''; position: absolute; bottom: -150px; left: -150px; width: 500px; height: 500px; background: radial-gradient(circle, rgba(37,99,235,0.05) 0%, transparent 70%); border-radius: 50%; }
        .hero-inner { max-width: 1200px; margin: 0 auto; padding: 120px 24px 80px; display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; position: relative; z-index: 1; }
        .hero-badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 16px; background: var(--cs-accent); border: 1px solid rgba(0,107,63,0.15); border-radius: 24px; font-size: 0.85rem; font-weight: 500; color: var(--cs-green); margin-bottom: 20px; }
        .hero h1 { font-size: 3rem; font-weight: 800; line-height: 1.15; margin-bottom: 20px; color: var(--text-dark); }
        .hero h1 span { color: var(--hos-color); }
        .hero-hospital-intro { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
        .hero-hospital-logo { width: 64px; height: 64px; border-radius: 14px; border: 2px solid rgba(0,0,0,0.06); object-fit: contain; background: #fff; }
        .hero-hospital-logo-placeholder { width: 64px; height: 64px; border-radius: 14px; background: linear-gradient(135deg, var(--hos-color), var(--cs-green)); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 1.5rem; flex-shrink: 0; }
        .hero-hospital-meta h2 { font-size: 1.4rem; font-weight: 700; color: var(--text-dark); margin-bottom: 2px; }
        .hero-hospital-meta p { font-size: 0.85rem; color: var(--text-muted); }
        .hero-sub { font-size: 1.15rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 36px; max-width: 500px; }
        .hero-cta { display: flex; gap: 16px; flex-wrap: wrap; }
        .btn-primary-lg { display: inline-flex; align-items: center; gap: 10px; padding: 16px 36px; background: var(--cs-green); color: #fff; border-radius: 12px; font-weight: 700; font-size: 1.05rem; text-decoration: none; transition: all 0.3s; box-shadow: 0 4px 20px rgba(0,107,63,0.3); }
        .btn-primary-lg:hover { background: var(--cs-green-dark); transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,107,63,0.35); }
        .btn-outline-lg { display: inline-flex; align-items: center; gap: 10px; padding: 16px 36px; background: transparent; color: var(--cs-green); border: 2px solid var(--cs-green); border-radius: 12px; font-weight: 600; font-size: 1.05rem; text-decoration: none; transition: all 0.3s; }
        .btn-outline-lg:hover { background: var(--cs-accent); }
        .hero-visual { display: flex; align-items: center; justify-content: center; }
        .hero-card { background: #fff; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.08); border: 1px solid rgba(0,107,63,0.08); width: 100%; max-width: 460px; }
        .hero-card-logos { display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 32px; }
        .hero-card-logos img { height: 48px; }
        .hero-card-logos .x-icon { color: var(--cs-green); font-size: 1.2rem; font-weight: 300; }
        .hero-card-hospital-logo { width: 52px; height: 52px; background: linear-gradient(135deg, var(--hos-color), #1e40af); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 1.3rem; }
        .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .stat-item { text-align: center; padding: 20px 12px; background: var(--bg-light); border-radius: 12px; }
        .stat-num { font-size: 1.8rem; font-weight: 800; color: var(--cs-green); }
        .stat-label { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; }
        /* FEATURES */
        .features { padding: 100px 24px; background: #fff; }
        .features-inner { max-width: 1200px; margin: 0 auto; }
        .section-tag { display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; background: var(--cs-accent); border-radius: 20px; font-size: 0.8rem; font-weight: 600; color: var(--cs-green); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; }
        .section-title { font-size: 2.2rem; font-weight: 800; margin-bottom: 16px; }
        .section-sub { font-size: 1.05rem; color: var(--text-muted); max-width: 600px; margin-bottom: 48px; }
        .feat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .feat-card { padding: 32px; border-radius: 16px; border: 1px solid #e2e8f0; transition: all 0.3s; }
        .feat-card:hover { border-color: var(--cs-green); box-shadow: 0 8px 30px rgba(0,107,63,0.08); transform: translateY(-4px); }
        .feat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 16px; }
        .feat-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 8px; }
        .feat-card p { font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; }
        /* CTA BANNER */
        .cta-banner { padding: 80px 24px; background: linear-gradient(135deg, var(--cs-green) 0%, var(--cs-green-dark) 100%); }
        .cta-inner { max-width: 800px; margin: 0 auto; text-align: center; color: #fff; }
        .cta-inner h2 { font-size: 2rem; font-weight: 800; margin-bottom: 16px; }
        .cta-inner p { font-size: 1.1rem; opacity: 0.85; margin-bottom: 32px; }
        .btn-white { display: inline-flex; align-items: center; gap: 10px; padding: 16px 40px; background: #fff; color: var(--cs-green); border-radius: 12px; font-weight: 700; font-size: 1.05rem; text-decoration: none; transition: all 0.3s; }
        .btn-white:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.2); }
        /* FOOTER */
        .welcome-footer { background: #0f172a; color: #cbd5e1; padding: 64px 24px 32px; }
        .footer-inner { max-width: 1200px; margin: 0 auto; }
        .footer-top { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 40px; margin-bottom: 48px; }
        .footer-brand p { font-size: 0.9rem; line-height: 1.7; margin-top: 16px; color: #94a3b8; }
        .footer-brand-logos { display: flex; align-items: center; gap: 12px; }
        .footer-brand-logos img { height: 32px; filter: brightness(0) invert(1); }
        .footer-col h4 { color: #fff; font-size: 0.95rem; font-weight: 700; margin-bottom: 16px; }
        .footer-col a { display: block; color: #94a3b8; text-decoration: none; font-size: 0.9rem; padding: 4px 0; transition: color 0.2s; }
        .footer-col a:hover { color: #fff; }
        .footer-bottom { border-top: 1px solid #1e293b; padding-top: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; font-size: 0.85rem; color: #64748b; }
        .footer-bottom a { color: #94a3b8; text-decoration: none; }
        .footer-bottom a:hover { color: #fff; }
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .hero-inner { grid-template-columns: 1fr; text-align: center; padding-top: 100px; }
            .hero h1 { font-size: 2.2rem; }
            .hero-sub { margin-left: auto; margin-right: auto; }
            .hero-cta { justify-content: center; }
            .hero-card { max-width: 100%; }
            .feat-grid { grid-template-columns: 1fr; }
            .footer-top { grid-template-columns: 1fr 1fr; }
            .nav-links a:not(.btn-login) { display: none; }
        }
    </style>
</head>
<body>

<!-- NAVIGATION -->
<nav class="welcome-nav" id="welcomeNav">
    <div class="nav-inner">
        <div class="nav-logos">
            @if(appsettings()->logo)
                <img src="data:image/jpeg;base64,{{ appsettings()->logo }}" alt="{{ appsettings()->site_name ?? env('APP_NAME') }}" style="height:40px;">
            @endif
            <div>
                <span class="nav-hospital-name">{{ appsettings()->site_name ?? env('APP_NAME', 'CoreHealth') }}</span>
                <div class="nav-powered"><strong style="color:var(--text-dark);">CoreHealth</strong> &nbsp;&bull;&nbsp; {{ __('front.powered_by') }} <img src="{{ asset('assets/images/corestream_logo.png') }}" alt="Corestream"> Corestream</div>
            </div>
        </div>
        <div class="nav-links">
            @auth
                <a href="{{ route('home') }}">{{ __('front.home') }}</a>
            @else
                <a href="{{ route('login') }}" class="btn-login"><i class="fas fa-sign-in-alt"></i> {{ __('front.login') }}</a>
            @endauth
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-inner">
        <div class="hero-content">
            <div class="hero-hospital-intro">
                @if(appsettings()->logo)
                    <img src="data:image/jpeg;base64,{{ appsettings()->logo }}" alt="" class="hero-hospital-logo">
                @else
                    <div class="hero-hospital-logo-placeholder">{{ strtoupper(substr(appsettings()->site_abbreviation ?? 'CH', 0, 2)) }}</div>
                @endif
                <div class="hero-hospital-meta">
                    <h2>{{ appsettings()->site_name ?? env('APP_NAME', 'CoreHealth') }}</h2>
                    <p>CoreHealth &mdash; {{ __('front.enterprise_healthcare') }}</p>
                </div>
            </div>
            <h1>{{ __('front.welcome_to') }} <span>{{ appsettings()->site_name ?? env('APP_NAME', 'CoreHealth') }}</span></h1>
            <p class="hero-sub">{{ appsettings()->description ?? __('front.hero_subtitle') }}</p>
            <div class="hero-cta">
                @auth
                    <a href="{{ route('home') }}" class="btn-primary-lg"><i class="fas fa-columns"></i> {{ __('front.go_to_dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="btn-primary-lg"><i class="fas fa-sign-in-alt"></i> {{ __('front.access_portal') }}</a>
                @endauth
                <a href="https://corestream.ng/" target="_blank" class="btn-outline-lg"><i class="fas fa-external-link-alt"></i> {{ __('front.learn_more') }}</a>
            </div>
        </div>
        <div class="hero-visual">
            <div class="hero-card">
                <div style="text-align:center;margin-bottom:24px;">
                    @if(appsettings()->logo)
                        <img src="data:image/jpeg;base64,{{ appsettings()->logo }}" alt="" style="height:56px;margin-bottom:12px;">
                    @else
                        <div class="hero-card-hospital-logo" style="margin:0 auto 12px;">{{ strtoupper(substr(appsettings()->site_abbreviation ?? 'CH', 0, 2)) }}</div>
                    @endif
                    <h3 style="font-size:1.1rem;font-weight:700;color:var(--text-dark);">{{ appsettings()->site_name ?? env('APP_NAME') }}</h3>
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;"><strong>CoreHealth EMR</strong> &bull; {{ __('front.powered_by') }} Corestream NG</p>
                </div>
                <div class="stat-grid">
                    <div class="stat-item"><div class="stat-num">18+</div><div class="stat-label">{{ __('front.clinical_modules') }}</div></div>
                    <div class="stat-item"><div class="stat-num">7</div><div class="stat-label">{{ __('front.languages_supported') }}</div></div>
                    <div class="stat-item"><div class="stat-num">24/7</div><div class="stat-label">{{ __('front.uptime') }}</div></div>
                    <div class="stat-item"><div class="stat-num">100%</div><div class="stat-label">{{ __('front.hipaa_ready') }}</div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section class="features">
    <div class="features-inner">
        <div class="section-tag"><i class="fas fa-th-large"></i> CoreHealth {{ __('front.platform_capabilities') }}</div>
        <h2 class="section-title">{{ __('front.everything_you_need') }}</h2>
        <p class="section-sub">{{ __('front.features_subtitle') }}</p>
        <div class="feat-grid">
            <div class="feat-card"><div class="feat-icon" style="background:#ecfdf5;color:#006B3F;"><i class="fas fa-user-md"></i></div><h3>{{ __('front.feat_clinical') }}</h3><p>{{ __('front.feat_clinical_desc') }}</p></div>
            <div class="feat-card"><div class="feat-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-flask"></i></div><h3>{{ __('front.feat_lab') }}</h3><p>{{ __('front.feat_lab_desc') }}</p></div>
            <div class="feat-card"><div class="feat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-pills"></i></div><h3>{{ __('front.feat_pharmacy') }}</h3><p>{{ __('front.feat_pharmacy_desc') }}</p></div>
            <div class="feat-card"><div class="feat-icon" style="background:#fce7f3;color:#db2777;"><i class="fas fa-file-invoice-dollar"></i></div><h3>{{ __('front.feat_billing') }}</h3><p>{{ __('front.feat_billing_desc') }}</p></div>
            <div class="feat-card"><div class="feat-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-warehouse"></i></div><h3>{{ __('front.feat_inventory') }}</h3><p>{{ __('front.feat_inventory_desc') }}</p></div>
            <div class="feat-card"><div class="feat-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fas fa-users-cog"></i></div><h3>{{ __('front.feat_hr') }}</h3><p>{{ __('front.feat_hr_desc') }}</p></div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-banner">
    <div class="cta-inner">
        <h2>{{ __('front.cta_title') }}</h2>
        <p><strong>CoreHealth EMR:</strong> {{ __('front.cta_subtitle') }}</p>
        @guest
            <a href="{{ route('login') }}" class="btn-white"><i class="fas fa-sign-in-alt"></i> {{ __('front.login_now') }}</a>
        @else
            <a href="{{ route('home') }}" class="btn-white"><i class="fas fa-columns"></i> {{ __('front.go_to_dashboard') }}</a>
        @endguest
    </div>
</section>

<!-- FOOTER -->
<footer class="welcome-footer">
    <div class="footer-inner">
        <div class="footer-top">
            <div class="footer-brand">
                <div class="footer-brand-logos">
                    @if(appsettings()->logo)
                        <img src="data:image/jpeg;base64,{{ appsettings()->logo }}" alt="" style="height:36px;filter:brightness(0) invert(1);">
                    @endif
                    <span style="color:#fff;font-weight:700;font-size:1.1rem;">{{ appsettings()->site_name ?? env('APP_NAME', 'CoreHealth') }}</span>
                </div>
                <p>{{ appsettings()->description ?? __('front.footer_tagline') }}</p>
                <div style="margin-top:12px;display:flex;align-items:center;gap:6px;font-size:0.8rem;color:#64748b;">
                    <strong style="color:#cbd5e1;">CoreHealth</strong> &bull; {{ __('front.powered_by') }}
                    <img src="{{ asset('assets/images/corestream_logo.png') }}" alt="Corestream" style="height:16px;filter:brightness(0) invert(1);opacity:0.5;">
                    <a href="https://corestream.ng/" target="_blank" style="color:#94a3b8;">Corestream NG</a>
                </div>
            </div>
            <div class="footer-col">
                <h4>{{ __('front.contact_info') }}</h4>
                @if(appsettings()->contact_phones)
                    <a href="tel:{{ appsettings()->contact_phones }}"><i class="fas fa-phone fa-sm"></i> {{ appsettings()->contact_phones }}</a>
                @endif
                @if(appsettings()->contact_emails)
                    <a href="mailto:{{ appsettings()->contact_emails }}"><i class="fas fa-envelope fa-sm"></i> {{ appsettings()->contact_emails }}</a>
                @endif
                @if(appsettings()->contact_address)
                    <a href="#"><i class="fas fa-map-marker-alt fa-sm"></i> {{ appsettings()->contact_address }}</a>
                @endif
            </div>
            <div class="footer-col">
                <h4>{{ __('front.explore') }}</h4>
                <a href="https://corestream.ng/" target="_blank">{{ __('front.about_us') }}</a>
                <a href="#">{{ __('front.services') }}</a>
                <a href="#">{{ __('front.privacy_policy') }}</a>
                <a href="#">{{ __('front.contact_us') }}</a>
            </div>
            <div class="footer-col">
                <h4>{{ appsettings()->site_name ?? __('front.hospital') }}</h4>
                <a href="{{ route('login') }}">{{ __('front.staff_portal') }}</a>
                <a href="#">{{ __('front.support') }}</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; {{ date('Y') }} {{ appsettings()->site_name ?? env('APP_NAME') }}. {{ __('front.all_rights_reserved') }}</span>
            <span>{{ __('front.powered_by') }} <a href="https://corestream.ng/" target="_blank">CoreStream NG</a></span>
        </div>
    </div>
</footer>

<script>
window.addEventListener('scroll', function() {
    document.getElementById('welcomeNav').classList.toggle('scrolled', window.scrollY > 40);
});
</script>
</body>
</html>

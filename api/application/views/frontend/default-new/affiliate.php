<!-- Custom CSS for Affiliate Page -->
<style>
    .affiliate-hero {
        background: linear-gradient(135deg, #009ee6 0%, #f3cc5e 100%);
        color: white;
        padding: 100px 0;
        position: relative;
        overflow: hidden;
    }
    .stats-card {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
    }
    .stats-card:hover { transform: translateY(-5px); }
    .feature-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #009ee6, #f3cc5e);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        color: white;
        font-size: 30px;
    }
    .commission-tier {
        background: linear-gradient(135deg, #009ee6 0%, #f3cc5e 100%);
        color: white;
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 20px;
    }
    .btn-affiliate {
        background: linear-gradient(135deg, #009ee6 0%, #f3cc5e 100%);
        border: none;
        padding: 15px 30px;
        color: white;
        border-radius: 50px;
        font-weight: bold;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }
    .btn-affiliate:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(0, 158, 230, 0.4);
        color: white;
    }
    .dashboard-card {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    .affiliate-form {
        background: white;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .form-floating > label {
        color: #6c757d;
    }
    .form-control:focus {
        border-color: #009ee6;
        box-shadow: 0 0 0 0.2rem rgba(0, 158, 230, 0.25);
    }
    .text-primary {
        color: #009ee6 !important;
    }
    .progress-bar-custom {
        background: linear-gradient(90deg, #009ee6, #f3cc5e);
        border-radius: 10px;
    }
    .nav-link-custom.active {
        background: linear-gradient(135deg, #009ee6, #f3cc5e);
        color: white;
    }
</style>

<!-- Hero Section -->
<section class="affiliate-hero">
    <div class="container position-relative">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Join Our Affiliate Program</h1>
                <p class="lead mb-4">Earn generous commissions by promoting high-quality courses to your audience. Start monetizing your network today!</p>
                <div class="d-flex gap-3 mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <span>Up to 50% Commission</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <span>Real-time Tracking</span>
                    </div>
                </div>
                <a href="#register" class="btn-affiliate me-3">
                    <i class="fas fa-rocket me-2"></i>Join Now
                </a>
            </div>
            <div class="col-lg-6">
                <div class="text-center">
                    <i class="fas fa-users-cog" style="font-size: 200px; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="py-5" style="margin-top: -50px;">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="fw-bold text-primary mb-2">$2M+</h3>
                    <p class="mb-0">Total Earnings Paid</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="fw-bold text-primary mb-2">15,000+</h3>
                    <p class="mb-0">Active Affiliates</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="fw-bold text-primary mb-2">50%</h3>
                    <p class="mb-0">Max Commission</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="fw-bold text-primary mb-2">24/7</h3>
                    <p class="mb-0">Support Available</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold mb-3">How Our Affiliate Program Works</h2>
            <p class="lead">Start earning in 3 simple steps</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="text-center">
                    <div class="feature-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h4 class="fw-bold mb-3">1. Sign Up</h4>
                    <p>Register for our affiliate program in minutes. Get your unique referral links and marketing materials instantly.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center">
                    <div class="feature-icon">
                        <i class="fas fa-share-alt"></i>
                    </div>
                    <h4 class="fw-bold mb-3">2. Promote</h4>
                    <p>Share our courses with your audience using your referral links. Use our marketing materials to boost conversions.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center">
                    <div class="feature-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h4 class="fw-bold mb-3">3. Earn</h4>
                    <p>Receive commission for every successful referral. Track your earnings in real-time and get paid monthly.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Commission Structure -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold mb-3">Commission Structure</h2>
            <p class="lead">Earn more as you grow with our tiered commission system</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="commission-tier text-center">
                    <i class="fas fa-star fs-1 mb-3"></i>
                    <h3 class="fw-bold">Bronze Level</h3>
                    <div class="fs-1 fw-bold my-3">25%</div>
                    <p class="mb-3">Commission Rate</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check me-2"></i>0-50 referrals</li>
                        <li><i class="fas fa-check me-2"></i>Marketing materials</li>
                        <li><i class="fas fa-check me-2"></i>Email support</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="commission-tier text-center" style="background: linear-gradient(135deg, #009ee6 0%, #f3cc5e 100%);">
                    <i class="fas fa-gem fs-1 mb-3"></i>
                    <h3 class="fw-bold">Silver Level</h3>
                    <div class="fs-1 fw-bold my-3">35%</div>
                    <p class="mb-3">Commission Rate</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check me-2"></i>51-200 referrals</li>
                        <li><i class="fas fa-check me-2"></i>Premium materials</li>
                        <li><i class="fas fa-check me-2"></i>Priority support</li>
                        <li><i class="fas fa-check me-2"></i>Performance bonus</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="commission-tier text-center" style="background: linear-gradient(135deg, #009ee6 0%, #f3cc5e 100%);">
                    <i class="fas fa-crown fs-1 mb-3"></i>
                    <h3 class="fw-bold">Gold Level</h3>
                    <div class="fs-1 fw-bold my-3">50%</div>
                    <p class="mb-3">Commission Rate</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check me-2"></i>200+ referrals</li>
                        <li><i class="fas fa-check me-2"></i>Exclusive materials</li>
                        <li><i class="fas fa-check me-2"></i>Dedicated manager</li>
                        <li><i class="fas fa-check me-2"></i>Monthly bonuses</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Registration Section -->
<section id="register" class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="affiliate-form">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold">Join Our Affiliate Program</h2>
                        <p class="text-muted">Start earning commissions today - it's free to join!</p>
                    </div>
                    
                    <?php if($this->session->flashdata('success_message')): ?>
                        <div class="alert alert-success">
                            <?php echo $this->session->flashdata('success_message'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo site_url('home/affiliate_register'); ?>" method="post">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" name="first_name" placeholder="First Name" required>
                                    <label>First Name *</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" name="last_name" placeholder="Last Name" required>
                                    <label>Last Name *</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" name="email" placeholder="Email" required>
                                    <label>Email Address *</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="tel" class="form-control" name="phone" placeholder="Phone">
                                    <label>Phone Number</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="url" class="form-control" name="website" placeholder="Website">
                                    <label>Website URL</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" name="experience" style="height: 100px" placeholder="Experience"></textarea>
                                    <label>Tell us about your marketing experience</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" required>
                                    <label class="form-check-label">
                                        I agree to the <a href="<?php echo site_url('home/terms_and_condition'); ?>">Terms and Conditions</a> and <a href="<?php echo site_url('home/privacy_policy'); ?>">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn-affiliate w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Application
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold mb-3">Frequently Asked Questions</h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="affiliateFAQ">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                How much can I earn as an affiliate?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#affiliateFAQ">
                            <div class="accordion-body">
                                Your earnings depend on your promotional efforts and conversion rates. Our top affiliates earn $5,000+ per month with commission rates up to 50%.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                When do I get paid?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#affiliateFAQ">
                            <div class="accordion-body">
                                Payments are processed monthly on the 15th. Minimum payout is $100. We support PayPal, bank transfer, and other payment methods.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                What marketing materials do you provide?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#affiliateFAQ">
                            <div class="accordion-body">
                                We provide banner ads, email templates, social media kits, product descriptions, and more. All materials are professionally designed and optimized for conversions.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    function copyToClipboard(button) {
        const input = button.previousElementSibling;
        input.select();
        document.execCommand('copy');
        button.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => {
            button.innerHTML = '<i class="fas fa-copy"></i>';
        }, 2000);
    }
</script>

<?php
require_once 'config/config.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    redirectTo('pages/dashboard.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Connect with Your Campus Community</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/main.css">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico" type="image/x-icon">
</head>
<body>
    <!-- Welcome Hero Section -->
    <section class="welcome-section">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <i class="fas fa-comments fa-5x mb-4 opacity-75"></i>
                    <h1 class="display-4 fw-bold mb-4"><?php echo SITE_NAME; ?></h1>
                    <p class="lead mb-5">
                        Bridge the communication gap in your college. Connect with students, 
                        join section groups, participate in open discussions, and chat anonymously 
                        when needed.
                    </p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="login.php" class="btn btn-light btn-lg px-4">
                            <i class="fas fa-sign-in-alt"></i> Sign In
                        </a>
                        <a href="register.php" class="btn btn-outline-light btn-lg px-4">
                            <i class="fas fa-user-plus"></i> Join Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="display-5 fw-bold mb-3">Why Campus Talk?</h2>
                    <p class="lead text-muted">
                        Designed specifically for college communities to enhance communication 
                        between students, sections, and academic levels.
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-users"></i>
                        <h3>Section Groups</h3>
                        <p class="text-muted">
                            Connect with your classmates through dedicated section groups. 
                            Share notes, discuss assignments, and stay updated on class activities.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-door-open"></i>
                        <h3>Open Chat Rooms</h3>
                        <p class="text-muted">
                            Join public chat rooms to interact with students from other sections 
                            and years. Break down barriers and expand your network.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-user-secret"></i>
                        <h3>Anonymous Mode</h3>
                        <p class="text-muted">
                            Express yourself freely with anonymous messaging. Get help, 
                            ask questions, or share opinions without revealing your identity.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-shield-alt"></i>
                        <h3>Safe Environment</h3>
                        <p class="text-muted">
                            Comprehensive moderation system with complaint handling ensures 
                            a safe and respectful communication environment for everyone.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-graduation-cap"></i>
                        <h3>Academic Focus</h3>
                        <p class="text-muted">
                            Separate spaces for students and faculty with appropriate permissions 
                            and privacy controls for academic discussions.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-mobile-alt"></i>
                        <h3>Mobile Friendly</h3>
                        <p class="text-muted">
                            Responsive design ensures seamless communication whether you're 
                            on desktop, tablet, or mobile device.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- How It Works Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="display-5 fw-bold mb-3">How It Works</h2>
                    <p class="lead text-muted">
                        Getting started with Campus Talk is simple and secure.
                    </p>
                </div>
            </div>
            
            <div class="row g-4 align-items-center">
                <div class="col-md-6 order-md-1">
                    <div class="pe-md-4">
                        <div class="d-flex align-items-start mb-4">
                            <div class="flex-shrink-0">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px;">
                                    <span class="fw-bold fs-4">1</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4>Register with College Email</h4>
                                <p class="text-muted">
                                    Sign up using your official college email address to ensure 
                                    only verified students and faculty can join.
                                </p>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-start mb-4">
                            <div class="flex-shrink-0">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px;">
                                    <span class="fw-bold fs-4">2</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4>Join Your Section Group</h4>
                                <p class="text-muted">
                                    Automatically get access to your section group based on your 
                                    registration details. Connect with classmates instantly.
                                </p>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px;">
                                    <span class="fw-bold fs-4">3</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4>Start Communicating</h4>
                                <p class="text-muted">
                                    Begin chatting in groups, join public rooms, or start direct 
                                    conversations. Toggle anonymous mode whenever needed.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 order-md-2">
                    <div class="text-center">
                        <i class="fas fa-laptop fa-10x text-primary opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Statistics Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="mb-4">
                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                        <h3 class="fw-bold">Secure</h3>
                        <p class="text-muted">College email verification ensures only authorized users</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="mb-4">
                        <i class="fas fa-comments fa-3x text-primary mb-3"></i>
                        <h3 class="fw-bold">Interactive</h3>
                        <p class="text-muted">Real-time messaging for instant communication</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="mb-4">
                        <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                        <h3 class="fw-bold">Moderated</h3>
                        <p class="text-muted">Built-in moderation system for safe conversations</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="mb-4">
                        <i class="fas fa-mobile-alt fa-3x text-primary mb-3"></i>
                        <h3 class="fw-bold">Responsive</h3>
                        <p class="text-muted">Works perfectly on all devices and screen sizes</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Call to Action Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container text-center">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h2 class="display-5 fw-bold mb-4">Ready to Connect?</h2>
                    <p class="lead mb-4">
                        Join your college community on Campus Talk today. Break down communication 
                        barriers and stay connected with your peers and faculty.
                    </p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="register.php" class="btn btn-light btn-lg px-5">
                            <i class="fas fa-rocket"></i> Get Started Now
                        </a>
                        <a href="login.php" class="btn btn-outline-light btn-lg px-5">
                            <i class="fas fa-sign-in-alt"></i> Sign In
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">
                        <i class="fas fa-comments me-2"></i>
                        © 2025 <?php echo SITE_NAME; ?>. Built for college communities.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-light me-3">Privacy Policy</a>
                    <a href="#" class="text-light me-3">Terms of Service</a>
                    <a href="#" class="text-light">Help</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Add animation on scroll
        function animateOnScroll() {
            const elements = document.querySelectorAll('.feature-card');
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;
                
                if (elementTop < window.innerHeight - elementVisible) {
                    element.classList.add('fade-in');
                }
            });
        }
        
        window.addEventListener('scroll', animateOnScroll);
        
        // Initialize animation on page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(animateOnScroll, 100);
        });
    </script>
</body>
</html>
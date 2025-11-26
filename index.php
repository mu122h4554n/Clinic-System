<?php
// Smart landing page - shows different content based on login status
require_once 'config/database.php';
require_once 'includes/functions.php';

startSession();

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$userRole = $isLoggedIn ? $_SESSION['role'] : null;
$userName = $isLoggedIn ? $_SESSION['full_name'] : null;
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Get quick stats for logged-in users
$quickStats = [];
if ($isLoggedIn) {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($userRole === 'patient') {
        // Get upcoming appointments count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND appointment_date >= CURDATE() AND status IN ('scheduled', 'confirmed')");
        $stmt->execute([$userId]);
        $quickStats['upcoming_appointments'] = $stmt->fetch()['count'];
        
        // Get recent medical records count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM medical_records WHERE patient_id = ? AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $stmt->execute([$userId]);
        $quickStats['recent_records'] = $stmt->fetch()['count'];
    } elseif ($userRole === 'doctor') {
        // Get today's appointments for doctor
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE()");
        $stmt->execute([$userId]);
        $quickStats['today_appointments'] = $stmt->fetch()['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isLoggedIn ? "Welcome " . htmlspecialchars(explode(' ', $userName)[0]) . " - HealthCare Clinic" : "HealthCare Clinic - Your Health, Our Priority"; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ACC0C9;
            --secondary-color: #ACC9C3;
            --accent-color: #C9ACB2;
            --success-color: #B3C9AD;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            background-size: 200% 200%;
            animation: heroGradientShift 8s ease infinite;
            color: white;
            padding: 100px 0;
            margin-top: 130px; /* Account for fixed navbar + emergency banner */
            position: relative;
            overflow: hidden; /* Keeps particles inside */
        }

        @keyframes heroGradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Hero Particles Container */
        .hero-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .hero-section .container {
            position: relative;
            z-index: 2; /* Keep content above particles */
        }

        .hero-particle {
            position: absolute;
            width: 8px;
            height: 8px;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            animation: heroParticleFloat 15s infinite ease-in-out;
        }

        .hero-particle:nth-child(1) {
            left: 10%;
            animation-delay: 0s;
            animation-duration: 12s;
        }

        .hero-particle:nth-child(2) {
            left: 30%;
            animation-delay: 2s;
            animation-duration: 14s;
        }

        .hero-particle:nth-child(3) {
            left: 50%;
            animation-delay: 4s;
            animation-duration: 16s;
        }

        .hero-particle:nth-child(4) {
            left: 70%;
            animation-delay: 1s;
            animation-duration: 13s;
        }

        .hero-particle:nth-child(5) {
            left: 90%;
            animation-delay: 3s;
            animation-duration: 15s;
        }

        .hero-particle:nth-child(6) {
            left: 20%;
            animation-delay: 5s;
            animation-duration: 17s;
        }

        .hero-particle:nth-child(7) {
            left: 60%;
            animation-delay: 2.5s;
            animation-duration: 14.5s;
        }

        .hero-particle:nth-child(8) {
            left: 80%;
            animation-delay: 4.5s;
            animation-duration: 13.5s;
        }

        @keyframes heroParticleFloat {
            0% {
                transform: translateY(100%) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.6;
            }
            90% {
                opacity: 0.6;
            }
            100% {
                transform: translateY(-100%) translateX(50px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Hero Icon Float Animation */
        .hero-section .fa-hospital-alt {
            animation: heroIconFloat 3s ease-in-out infinite;
        }

        @keyframes heroIconFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Hero Text Fade-in Animations */
        .hero-section h1 {
            animation: heroFadeInUp 1s ease-out;
        }

        .hero-section .lead {
            animation: heroFadeInUp 1.2s ease-out;
        }

        .hero-section .d-flex {
            animation: heroFadeInUp 1.4s ease-out;
        }

        @keyframes heroFadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Hero Wave Divider */
        .hero-wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: auto;
            z-index: 1;
        }

        /* Neumorphism Buttons for Hero Section */
        .neuro-button {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 20px 50px;
            font-size: 18px;
            font-weight: 600;
            text-decoration: none;
            letter-spacing: 0.5px;
            border: none;
            border-radius: 50px;
            transition: all 0.3s ease;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #353131ff;
        }

        /* Raised Neumorphism Button (Primary) */
        .neuro-button-raised {
            box-shadow:
                12px 12px 24px rgba(0, 0, 0, 0.2),
                -12px -12px 24px rgba(255, 255, 255, 0.3),
                inset 2px 2px 5px rgba(255, 255, 255, 0.2),
                inset -2px -2px 5px rgba(0, 0, 0, 0.1);
        }

        .neuro-button-raised:hover {
            box-shadow:
                16px 16px 32px rgba(0, 0, 0, 0.25),
                -16px -16px 32px rgba(255, 255, 255, 0.35),
                inset 3px 3px 8px rgba(255, 255, 255, 0.25),
                inset -3px -3px 8px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.2);
        }

        .neuro-button-raised:active {
            box-shadow:
                inset 8px 8px 16px rgba(0, 0, 0, 0.2),
                inset -8px -8px 16px rgba(255, 255, 255, 0.1),
                2px 2px 4px rgba(0, 0, 0, 0.1);
            transform: translateY(1px);
        }

        /* Pressed Neumorphism Button (Secondary) */
        .neuro-button-pressed {
            box-shadow:
                inset 8px 8px 16px rgba(0, 0, 0, 0.15),
                inset -8px -8px 16px rgba(255, 255, 255, 0.15),
                4px 4px 8px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.1);
        }

        .neuro-button-pressed:hover {
            box-shadow:
                inset 10px 10px 20px rgba(0, 0, 0, 0.2),
                inset -10px -10px 20px rgba(255, 255, 255, 0.2),
                6px 6px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
            background: rgba(255, 255, 255, 0.15);
        }

        .neuro-button-pressed:active {
            box-shadow:
                inset 12px 12px 24px rgba(0, 0, 0, 0.25),
                inset -12px -12px 24px rgba(255, 255, 255, 0.1);
            transform: translateY(0);
        }

        /* Gradient Overlay for Neumorphism Buttons */
        .neuro-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 50px;
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.2) 0%,
                rgba(255, 255, 255, 0) 50%,
                rgba(0, 0, 0, 0.1) 100%);
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .neuro-button:hover::before {
            opacity: 0.7;
        }

        /* Shine Effect on Hover */
        .neuro-button::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 30%,
                rgba(255, 255, 255, 0.3) 50%,
                transparent 70%
            );
            transform: translateX(-100%) translateY(-100%) rotate(45deg);
            transition: transform 0.6s ease;
        }

        .neuro-button:hover::after {
            transform: translateX(100%) translateY(100%) rotate(45deg);
        }

        /* Icon Styling */
        .neuro-button i {
            margin-right: 12px;
            font-size: 20px;
            position: relative;
            z-index: 1;
        }

        /* Text Styling */
        .neuro-button span,
        .neuro-button {
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        /* Subtle Pulse Animation */
        @keyframes neuroPulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.02);
            }
        }

        .neuro-button-raised {
            animation: neuroPulse 3s ease-in-out infinite;
        }

        /* Responsive adjustments for neumorphism buttons */
        @media (max-width: 768px) {
            .neuro-button {
                padding: 16px 40px;
                font-size: 16px;
            }

            .neuro-button i {
                font-size: 18px;
                margin-right: 10px;
            }
        }

        /* Colored Glass Morphism Feature Cards */
        .feature-card {
            position: relative;
            border-radius: 20px;
            border: none;
            overflow: hidden;
            transition: all 0.4s ease;
            background-size: 200% 200%;
            animation: featureGradientShift 8s ease infinite;
        }

        @keyframes featureGradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Shine Effect */
        .feature-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 30%,
                rgba(255, 255, 255, 0.2) 50%,
                transparent 70%
            );
            transform: translateX(-100%) rotate(45deg);
            transition: transform 0.6s ease;
            z-index: 1;
        }

        .feature-card:hover::before {
            transform: translateX(100%) rotate(45deg);
        }

        .feature-card > * {
            position: relative;
            z-index: 2;
        }

        /* Blue Glass - Online Booking */
        .feature-card-blue {
            background: linear-gradient(135deg,
                rgba(59, 130, 246, 0.15),
                rgba(96, 165, 250, 0.1));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(59, 130, 246, 0.3);
            box-shadow:
                0 8px 32px rgba(59, 130, 246, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .feature-card-blue:hover {
            background: linear-gradient(135deg,
                rgba(59, 130, 246, 0.25),
                rgba(96, 165, 250, 0.2));
            box-shadow:
                0 12px 48px rgba(59, 130, 246, 0.25),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            transform: translateY(-8px);
            border-color: rgba(59, 130, 246, 0.5);
        }

        .feature-card-blue .service-icon {
            color: #3b82f6;
            text-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
        }

        /* Green Glass - Digital Records */
        .feature-card-green {
            background: linear-gradient(135deg,
                rgba(34, 197, 94, 0.15),
                rgba(74, 222, 128, 0.1));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(34, 197, 94, 0.3);
            box-shadow:
                0 8px 32px rgba(34, 197, 94, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .feature-card-green:hover {
            background: linear-gradient(135deg,
                rgba(34, 197, 94, 0.25),
                rgba(74, 222, 128, 0.2));
            box-shadow:
                0 12px 48px rgba(34, 197, 94, 0.25),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            transform: translateY(-8px);
            border-color: rgba(34, 197, 94, 0.5);
        }

        .feature-card-green .service-icon {
            color: #22c55e;
            text-shadow: 0 0 20px rgba(34, 197, 94, 0.5);
        }

        /* Yellow/Amber Glass - Expert Doctors */
        .feature-card-yellow {
            background: linear-gradient(135deg,
                rgba(245, 158, 11, 0.15),
                rgba(251, 191, 36, 0.1));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(245, 158, 11, 0.3);
            box-shadow:
                0 8px 32px rgba(245, 158, 11, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .feature-card-yellow:hover {
            background: linear-gradient(135deg,
                rgba(245, 158, 11, 0.25),
                rgba(251, 191, 36, 0.2));
            box-shadow:
                0 12px 48px rgba(245, 158, 11, 0.25),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            transform: translateY(-8px);
            border-color: rgba(245, 158, 11, 0.5);
        }

        .feature-card-yellow .service-icon {
            color: #f59e0b;
            text-shadow: 0 0 20px rgba(245, 158, 11, 0.5);
        }

        /* Red/Pink Glass - Secure & Private */
        .feature-card-red {
            background: linear-gradient(135deg,
                rgba(239, 68, 68, 0.15),
                rgba(248, 113, 113, 0.1));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(239, 68, 68, 0.3);
            box-shadow:
                0 8px 32px rgba(239, 68, 68, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .feature-card-red:hover {
            background: linear-gradient(135deg,
                rgba(239, 68, 68, 0.25),
                rgba(248, 113, 113, 0.2));
            box-shadow:
                0 12px 48px rgba(239, 68, 68, 0.25),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            transform: translateY(-8px);
            border-color: rgba(239, 68, 68, 0.5);
        }

        .feature-card-red .service-icon {
            color: #ef4444;
            text-shadow: 0 0 20px rgba(239, 68, 68, 0.5);
        }

        /* Icon Styling and Animation */
        .feature-card .service-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .feature-card:hover .service-icon {
            animation: iconPulse 1s ease-in-out infinite;
        }

        /* Card Title and Text */
        .feature-card h5 {
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1f2937;
        }

        .feature-card .text-muted {
            color: #6b7280 !important;
        }

        /* Responsive adjustments for feature cards */
        @media (max-width: 768px) {
            .feature-card {
                margin-bottom: 1.5rem;
            }

            .feature-card .service-icon {
                font-size: 2.5rem;
            }
        }
        
        .feature-card {
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .service-icon {
            font-size: 3rem;
            color: var(--secondary-color);
            margin-bottom: 1rem;
        }
        
        .cta-section {
            background-color: #f8f9fa;
            padding: 80px 0;
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.3s ease;
        }
        
        .navbar-brand:hover {
            transform: scale(1.05);
        }
        
        .navbar-brand .logo-img {
            height: 60px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.15));
            transition: all 0.3s ease;
        }
        
        .navbar-brand:hover .logo-img {
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.25));
            transform: rotate(-2deg);
        }
        
        @media (max-width: 768px) {
            .navbar-brand .logo-img {
                height: 45px;
            }
            .navbar-brand span {
                font-size: 1.2rem;
            }
        }
        
        .step-number {
            width: 80px;
            height: 80px;
            font-size: 1.5rem;
        }
        
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Custom button styles */
        .btn-custom {
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Pricing card styles */
        .pricing-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
        }
        
        .price-tag h3 {
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        /* Doctor card styles */
        .doctor-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .doctor-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        }
        
        .avatar-circle {
            transition: transform 0.3s ease;
        }
        
        .doctor-card:hover .avatar-circle {
            transform: scale(1.1);
        }

        /* Glass Morphism Button Styles */
        @property --angle-1 {
            syntax: "<angle>";
            inherits: false;
            initial-value: -75deg;
        }

        @property --angle-2 {
            syntax: "<angle>";
            inherits: false;
            initial-value: -45deg;
        }

        .button-wrap {
            transition: all 400ms cubic-bezier(0.25, 1, 0.5, 1);
        }

        .glass-button {
            background: linear-gradient(-75deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.05));
            box-shadow:
                inset 0 0.125em 0.125em rgba(0, 0, 0, 0.05),
                inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.5),
                0 0.25em 0.125em -0.125em rgba(0, 0, 0, 0.2),
                0 0 0.1em 0.25em rgba(255, 255, 255, 0.2) inset,
                0 0 0 0 rgba(255, 255, 255, 1);
            backdrop-filter: blur(clamp(1px, 0.125em, 4px));
            transition: all 400ms cubic-bezier(0.25, 1, 0.5, 1);
            border: none;
            padding: 0;
            position: relative;
            border-radius: 999px;
            outline: none;
        }

        .glass-button:hover {
            transform: scale(0.975);
            backdrop-filter: blur(0.01em);
            box-shadow:
                inset 0 0.125em 0.125em rgba(0, 0, 0, 0.05),
                inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.5),
                0 0.15em 0.05em -0.1em rgba(0, 0, 0, 0.25),
                0 0 0.05em 0.1em rgba(255, 255, 255, 0.5) inset,
                0 0 0 0 rgba(255, 255, 255, 1);
        }

        .glass-button:active {
            transform: scale(0.95) rotate3d(1, 0, 0, 25deg);
            box-shadow:
                inset 0 0.125em 0.125em rgba(0, 0, 0, 0.05),
                inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.5),
                0 0.125em 0.125em -0.125em rgba(0, 0, 0, 0.2),
                0 0 0.1em 0.25em rgba(255, 255, 255, 0.2) inset,
                0 0.225em 0.05em 0 rgba(0, 0, 0, 0.05),
                0 0.25em 0 0 rgba(255, 255, 255, 0.75),
                inset 0 0.25em 0.05em 0 rgba(0, 0, 0, 0.15);
        }

        .glass-button:focus {
            outline: none;
        }

        .button-text {
            text-shadow: 0em 0.25em 0.05em rgba(0, 0, 0, 0.1);
            transition: all 400ms cubic-bezier(0.25, 1, 0.5, 1);
            position: relative;
            display: block;
            user-select: none;
            font-weight: 500;
            font-size: 14px;
            color: #1a1a1a;
            letter-spacing: -0.02em;
            padding: 10px 20px;
        }

        .glass-button:hover .button-text {
            text-shadow: 0.025em 0.025em 0.025em rgba(0, 0, 0, 0.12);
        }

        .glass-button:active .button-text {
            text-shadow: 0.025em 0.25em 0.05em rgba(0, 0, 0, 0.12);
        }

        .glass-button::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 999px;
            width: calc(100% + 2px);
            height: calc(100% + 2px);
            top: -1px;
            left: -1px;
            padding: 1px;
            box-sizing: border-box;
            background:
                conic-gradient(from var(--angle-1) at 50% 50%, rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0) 5% 40%, rgba(0, 0, 0, 0.5) 50%, rgba(0, 0, 0, 0) 60% 95%, rgba(0, 0, 0, 0.5)),
                linear-gradient(180deg, rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.5));
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            mask-composite: exclude;
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            transition: all 400ms cubic-bezier(0.25, 1, 0.5, 1), --angle-1 500ms ease;
            box-shadow: inset 0 0 0 0.5px rgba(255, 255, 255, 0.5);
            pointer-events: none;
        }

        .glass-button:hover::after {
            --angle-1: -125deg;
        }

        .glass-button:active::after {
            --angle-1: -75deg;
        }

        .button-shine {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            width: calc(100% - 1px);
            height: calc(100% - 1px);
            top: 0.5px;
            left: 0.5px;
            background: linear-gradient(var(--angle-2), rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.5) 40% 50%, rgba(255, 255, 255, 0) 55%);
            mix-blend-mode: screen;
            pointer-events: none;
            background-size: 200% 200%;
            background-position: 0% 50%;
            background-repeat: no-repeat;
            transition: background-position 500ms cubic-bezier(0.25, 1, 0.5, 1), --angle-2 500ms cubic-bezier(0.25, 1, 0.5, 1);
        }

        .glass-button:hover .button-shine {
            background-position: 25% 50%;
        }

        .glass-button:active .button-shine {
            background-position: 50% 15%;
            --angle-2: -15deg;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Glass Morphism Nav Links */
        .nav-link.glass-nav-link {
            background: linear-gradient(-75deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.05));
            box-shadow:
                inset 0 0.125em 0.125em rgba(0, 0, 0, 0.05),
                inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.5),
                0 0.25em 0.125em -0.125em rgba(0, 0, 0, 0.2),
                0 0 0.1em 0.25em rgba(255, 255, 255, 0.2) inset,
                0 0 0 0 rgba(255, 255, 255, 1);
            backdrop-filter: blur(clamp(1px, 0.125em, 4px));
            transition: all 400ms cubic-bezier(0.25, 1, 0.5, 1);
            border-radius: 999px;
            padding: 10px 24px !important;
            margin: 4px 6px;
            position: relative;
            overflow: hidden;
            color: #1a1a1a !important;
            font-weight: 500;
            text-shadow: 0em 0.25em 0.05em rgba(0, 0, 0, 0.1);
        }

        .nav-link.glass-nav-link:hover {
            transform: scale(0.975);
            backdrop-filter: blur(0.01em);
            box-shadow:
                inset 0 0.125em 0.125em rgba(0, 0, 0, 0.05),
                inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.5),
                0 0.15em 0.05em -0.1em rgba(0, 0, 0, 0.25),
                0 0 0.05em 0.1em rgba(255, 255, 255, 0.5) inset,
                0 0 0 0 rgba(255, 255, 255, 1);
            text-shadow: 0.025em 0.025em 0.025em rgba(0, 0, 0, 0.12);
        }

        .nav-link.glass-nav-link:active,
        .nav-link.glass-nav-link.active {
            transform: scale(0.95);
            box-shadow:
                inset 0 0.125em 0.125em rgba(0, 0, 0, 0.05),
                inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.5),
                0 0.125em 0.125em -0.125em rgba(0, 0, 0, 0.2),
                0 0 0.1em 0.25em rgba(255, 255, 255, 0.2) inset,
                0 0.225em 0.05em 0 rgba(0, 0, 0, 0.05),
                0 0.25em 0 0 rgba(255, 255, 255, 0.75),
                inset 0 0.25em 0.05em 0 rgba(0, 0, 0, 0.15);
            text-shadow: 0.025em 0.25em 0.05em rgba(0, 0, 0, 0.12);
        }

        .nav-link.glass-nav-link::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 999px;
            width: calc(100% + 2px);
            height: calc(100% + 2px);
            top: -1px;
            left: -1px;
            padding: 1px;
            box-sizing: border-box;
            background:
                conic-gradient(from var(--angle-1) at 50% 50%, rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0) 5% 40%, rgba(0, 0, 0, 0.5) 50%, rgba(0, 0, 0, 0) 60% 95%, rgba(0, 0, 0, 0.5)),
                linear-gradient(180deg, rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.5));
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            mask-composite: exclude;
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            transition: all 400ms cubic-bezier(0.25, 1, 0.5, 1);
            box-shadow: inset 0 0 0 0.5px rgba(255, 255, 255, 0.5);
            pointer-events: none;
        }

        .nav-link.glass-nav-link:hover::after {
            --angle-1: -125deg;
        }

        .nav-link.glass-nav-link::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 999px;
            width: calc(100% - 1px);
            height: calc(100% - 1px);
            top: 0.5px;
            left: 0.5px;
            background: linear-gradient(var(--angle-2), rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.5) 40% 50%, rgba(255, 255, 255, 0) 55%);
            mix-blend-mode: screen;
            pointer-events: none;
            background-size: 200% 200%;
            background-position: 0% 50%;
            background-repeat: no-repeat;
            transition: background-position 500ms cubic-bezier(0.25, 1, 0.5, 1);
            opacity: 0;
        }

        .nav-link.glass-nav-link:hover::before {
            background-position: 25% 50%;
            opacity: 1;
        }

        @media (max-width: 991px) {
            .nav-link.glass-nav-link {
                margin: 8px 0;
                display: block;
                text-align: center;
            }
        }

        /* Animated Login Button Styles */
        .animated-login-button {
            cursor: pointer;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            transition: all 0.25s ease;
            background: radial-gradient(65.28% 65.28% at 50% 100%,
                rgba(99, 102, 241, 0.6) 0%,
                rgba(129, 140, 248, 0.3) 50%,
                rgba(99, 102, 241, 0) 100%),
              linear-gradient(135deg, #4338ca, #6366f1, #8b5cf6);
            border-radius: 9999px;
            border: none;
            outline: none;
            padding: 10px 28px;
            min-height: 50px;
            min-width: 120px;
            box-shadow: 0 8px 25px -8px rgba(99, 102, 241, 0.6),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            text-decoration: none;
        }

        .animated-login-button::before,
        .animated-login-button::after {
            content: "";
            position: absolute;
            transition: all 0.5s ease-in-out;
            z-index: 0;
        }

        .animated-login-button::before {
            inset: 1px;
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.2) 0%,
                rgba(255, 255, 255, 0.05) 50%,
                rgba(255, 255, 255, 0) 100%);
            border-radius: 9999px;
        }

        .animated-login-button::after {
            inset: 2px;
            background: radial-gradient(65.28% 65.28% at 50% 100%,
                rgba(99, 102, 241, 0.4) 0%,
                rgba(129, 140, 248, 0.2) 50%,
                rgba(99, 102, 241, 0) 100%),
              linear-gradient(135deg, #4338ca, #6366f1, #8b5cf6);
            border-radius: 9999px;
        }

        .animated-login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px -8px rgba(99, 102, 241, 0.8),
                        0 0 0 1px rgba(255, 255, 255, 0.2);
        }

        .animated-login-button:active {
            transform: translateY(-1px) scale(0.98);
        }

        .login-points-wrapper {
            overflow: hidden;
            width: 100%;
            height: 100%;
            pointer-events: none;
            position: absolute;
            z-index: 1;
        }

        .login-points-wrapper .login-point {
            bottom: -10px;
            position: absolute;
            animation: floating-points infinite ease-in-out;
            pointer-events: none;
            width: 2px;
            height: 2px;
            background-color: #c7d2fe;
            border-radius: 9999px;
            box-shadow: 0 0 4px rgba(199, 210, 254, 0.8);
        }

        @keyframes floating-points {
            0% {
                transform: translateY(0);
                opacity: 0.8;
            }

            50% {
                opacity: 1;
            }

            85% {
                opacity: 0.3;
            }

            100% {
                transform: translateY(-60px);
                opacity: 0;
            }
        }

        .login-points-wrapper .login-point:nth-child(1) {
            left: 15%;
            opacity: 0.9;
            animation-duration: 2.8s;
            animation-delay: 0.3s;
        }

        .login-points-wrapper .login-point:nth-child(2) {
            left: 25%;
            opacity: 0.7;
            animation-duration: 3.2s;
            animation-delay: 0.7s;
        }

        .login-points-wrapper .login-point:nth-child(3) {
            left: 35%;
            opacity: 0.8;
            animation-duration: 2.6s;
            animation-delay: 0.2s;
        }

        .login-points-wrapper .login-point:nth-child(4) {
            left: 50%;
            opacity: 0.6;
            animation-duration: 2.4s;
            animation-delay: 0.1s;
        }

        .login-points-wrapper .login-point:nth-child(5) {
            left: 60%;
            opacity: 0.9;
            animation-duration: 2.1s;
            animation-delay: 0s;
        }

        .login-points-wrapper .login-point:nth-child(6) {
            left: 70%;
            opacity: 0.5;
            animation-duration: 2.9s;
            animation-delay: 1.2s;
        }

        .login-points-wrapper .login-point:nth-child(7) {
            left: 80%;
            opacity: 0.8;
            animation-duration: 2.7s;
            animation-delay: 0.4s;
        }

        .login-points-wrapper .login-point:nth-child(8) {
            left: 45%;
            opacity: 0.7;
            animation-duration: 3.0s;
            animation-delay: 0.6s;
        }

        .login-points-wrapper .login-point:nth-child(9) {
            left: 85%;
            opacity: 0.6;
            animation-duration: 2.3s;
            animation-delay: 0.8s;
        }

        .login-points-wrapper .login-point:nth-child(10) {
            left: 65%;
            opacity: 0.9;
            animation-duration: 2.5s;
            animation-delay: 0.5s;
        }

        .login-button-inner {
            z-index: 2;
            gap: 8px;
            position: relative;
            width: 100%;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.4;
            transition: all 0.2s ease-in-out;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .login-button-inner svg.login-icon {
            width: 16px;
            height: 16px;
            transition: transform 0.3s ease;
            stroke: #ffffff;
            fill: none;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.2));
        }

        .animated-login-button:hover svg.login-icon {
            transform: translateX(3px);
        }

        .animated-login-button:hover svg.login-icon path {
            animation: dash 0.8s linear forwards;
        }

        @keyframes dash {
            0% {
                stroke-dasharray: 0, 25;
                stroke-dashoffset: 0;
            }

            50% {
                stroke-dasharray: 12, 12;
                stroke-dashoffset: -6;
            }

            100% {
                stroke-dasharray: 25, 0;
                stroke-dashoffset: -12;
            }
        }

        /* Animated Sign Up Button Styles - Red/Pink Theme */
        .animated-signup-button {
            cursor: pointer;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            transition: all 0.25s ease;
            background: radial-gradient(65.28% 65.28% at 50% 100%,
                rgba(244, 63, 94, 0.6) 0%,
                rgba(251, 113, 133, 0.3) 50%,
                rgba(244, 63, 94, 0) 100%),
              linear-gradient(135deg, #be123c, #e11d48, #f43f5e, #fb7185);
            border-radius: 9999px;
            border: none;
            outline: none;
            padding: 10px 28px;
            min-height: 50px;
            min-width: 120px;
            box-shadow: 0 8px 25px -8px rgba(244, 63, 94, 0.6),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            text-decoration: none;
        }

        .animated-signup-button::before,
        .animated-signup-button::after {
            content: "";
            position: absolute;
            transition: all 0.5s ease-in-out;
            z-index: 0;
        }

        .animated-signup-button::before {
            inset: 1px;
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.2) 0%,
                rgba(255, 255, 255, 0.05) 50%,
                rgba(255, 255, 255, 0) 100%);
            border-radius: 9999px;
        }

        .animated-signup-button::after {
            inset: 2px;
            background: radial-gradient(65.28% 65.28% at 50% 100%,
                rgba(244, 63, 94, 0.4) 0%,
                rgba(251, 113, 133, 0.2) 50%,
                rgba(244, 63, 94, 0) 100%),
              linear-gradient(135deg, #be123c, #e11d48, #f43f5e, #fb7185);
            border-radius: 9999px;
        }

        .animated-signup-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px -8px rgba(244, 63, 94, 0.8),
                        0 0 0 1px rgba(255, 255, 255, 0.2);
        }

        .animated-signup-button:active {
            transform: translateY(-1px) scale(0.98);
        }

        .signup-points-wrapper {
            overflow: hidden;
            width: 100%;
            height: 100%;
            pointer-events: none;
            position: absolute;
            z-index: 1;
        }

        .signup-points-wrapper .signup-point {
            bottom: -10px;
            position: absolute;
            animation: floating-points infinite ease-in-out;
            pointer-events: none;
            width: 2px;
            height: 2px;
            background-color: #fecdd3;
            border-radius: 9999px;
            box-shadow: 0 0 4px rgba(254, 205, 211, 0.8);
        }

        .signup-points-wrapper .signup-point:nth-child(1) {
            left: 15%;
            opacity: 0.9;
            animation-duration: 2.8s;
            animation-delay: 0.3s;
        }

        .signup-points-wrapper .signup-point:nth-child(2) {
            left: 25%;
            opacity: 0.7;
            animation-duration: 3.2s;
            animation-delay: 0.7s;
        }

        .signup-points-wrapper .signup-point:nth-child(3) {
            left: 35%;
            opacity: 0.8;
            animation-duration: 2.6s;
            animation-delay: 0.2s;
        }

        .signup-points-wrapper .signup-point:nth-child(4) {
            left: 50%;
            opacity: 0.6;
            animation-duration: 2.4s;
            animation-delay: 0.1s;
        }

        .signup-points-wrapper .signup-point:nth-child(5) {
            left: 60%;
            opacity: 0.9;
            animation-duration: 2.1s;
            animation-delay: 0s;
        }

        .signup-points-wrapper .signup-point:nth-child(6) {
            left: 70%;
            opacity: 0.5;
            animation-duration: 2.9s;
            animation-delay: 1.2s;
        }

        .signup-points-wrapper .signup-point:nth-child(7) {
            left: 80%;
            opacity: 0.8;
            animation-duration: 2.7s;
            animation-delay: 0.4s;
        }

        .signup-points-wrapper .signup-point:nth-child(8) {
            left: 45%;
            opacity: 0.7;
            animation-duration: 3.0s;
            animation-delay: 0.6s;
        }

        .signup-points-wrapper .signup-point:nth-child(9) {
            left: 85%;
            opacity: 0.6;
            animation-duration: 2.3s;
            animation-delay: 0.8s;
        }

        .signup-points-wrapper .signup-point:nth-child(10) {
            left: 65%;
            opacity: 0.9;
            animation-duration: 2.5s;
            animation-delay: 0.5s;
        }

        .signup-button-inner {
            z-index: 2;
            gap: 8px;
            position: relative;
            width: 100%;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.4;
            transition: all 0.2s ease-in-out;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .signup-button-inner svg.signup-icon {
            width: 16px;
            height: 16px;
            transition: transform 0.3s ease;
            stroke: #ffffff;
            fill: none;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.2));
        }

        .animated-signup-button:hover svg.signup-icon {
            transform: translateX(3px);
        }

        .animated-signup-button:hover svg.signup-icon path {
            animation: dash 0.8s linear forwards;
        }

        /* Enhanced User Dropdown Styles */
        .user-dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 2px;
            padding: 12px 50px;
            border-radius: 50px;
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 110, 253, 0.05) 100%);
            border: 1px solid rgba(13, 110, 253, 0.2);
            transition: all 0.3s ease;
            text-decoration: none;
            color: #0d6efd !important;
            font-weight: 600;
        }

        .user-dropdown-toggle:hover {
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.15) 0%, rgba(13, 110, 253, 0.1) 100%);
            border-color: rgba(13, 110, 253, 0.3);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.15);
        }

        .user-dropdown-toggle i {
            font-size: 1.2rem;
        }

        .user-dropdown-menu {
            min-width: 280px;
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            padding: 0;
            overflow: hidden;
            margin-top: 10px;
        }

        .user-dropdown-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }

        .user-dropdown-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.2);
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            font-size: 1.8rem;
        }

        .user-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .user-role-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .dropdown-item-enhanced {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
            position: relative;
            color: #000000;
        }

        .dropdown-item-enhanced i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .dropdown-item-enhanced:hover {
            background: linear-gradient(90deg, rgba(13, 110, 253, 0.08) 0%, rgba(13, 110, 253, 0.03) 100%);
            border-left-color: #0d6efd;
            padding-left: 24px;
            transform: translateX(4px);
        }

        .dropdown-item-enhanced.danger:hover {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.05) 100%);
            border-left-color: #dc3545;
        }

        .dropdown-divider-enhanced {
            margin: 8px 0;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }

        .dropdown-section-label {
            padding: 8px 20px 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Emergency Banner -->
    <div class="alert alert-danger alert-dismissible fade show m-0 text-center" role="alert" style="border-radius: 0; z-index: 1060;">
        <div class="container">
            <strong><i class="fas fa-exclamation-triangle me-2"></i>Emergency?</strong> 
            Call us immediately at <a href="tel:+15559111357" class="alert-link fw-bold">(555) 911-HELP</a> 
            or visit our emergency department 24/7
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand text-primary" href="#home">
                <img src="img/LOGO_CLINIC-removebg-preview.png" alt="CareAid Clinic Logo" class="logo-img">
                <span>CareAid Clinic</span>
            </a>
            <!-- Glass Morphism Toggle Button -->
            <div class="button-wrap" style="animation: fadeIn 1s ease-out 0.3s both;">
                <button class="glass-button" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <div class="button-shine"></div>
                </button>
            </div>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link glass-nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link glass-nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link glass-nav-link" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link glass-nav-link" href="#pricing">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link glass-nav-link" href="#how-it-works">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link glass-nav-link" href="#contact">Contact</a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                        <!-- Logged-in user navigation -->
                        <li class="nav-item dropdown">
                            <a class="user-dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle"></i>
                                <span><?php echo htmlspecialchars(explode(' ', $userName)[0]); ?></span>
                                <i class="fas fa-chevron-down" style="font-size: 0.7rem; margin-left: 4px;"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu">
                                <!-- User Header -->
                                <li class="user-dropdown-header">
                                    <div class="user-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                                    <div class="user-role-badge">
                                        <i class="fas fa-id-badge me-1"></i><?php echo ucfirst($userRole); ?>
                                    </div>
                                </li>
                                
                                <!-- Quick Actions -->
                                <li class="dropdown-section-label">Quick Actions</li>
                                <li>
                                    <a class="dropdown-item dropdown-item-enhanced" href="dashboard.php">
                                        <i class="fas fa-tachometer-alt text-primary"></i>
                                        <span>Dashboard</span>
                                    </a>
                                </li>
                                
                                <?php if ($userRole === 'patient'): ?>
                                    <li>
                                        <a class="dropdown-item dropdown-item-enhanced" href="my-appointments.php">
                                            <i class="fas fa-calendar-check text-success"></i>
                                            <span>My Appointments</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item dropdown-item-enhanced" href="book-appointment.php">
                                            <i class="fas fa-calendar-plus text-info"></i>
                                            <span>Book Appointment</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item dropdown-item-enhanced" href="my-records.php">
                                            <i class="fas fa-file-medical-alt text-warning"></i>
                                            <span>Medical Records</span>
                                        </a>
                                    </li>
                                <?php elseif ($userRole === 'doctor'): ?>
                                    <li>
                                        <a class="dropdown-item dropdown-item-enhanced" href="doctor/appointments.php">
                                            <i class="fas fa-calendar-check text-success"></i>
                                            <span>My Appointments</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item dropdown-item-enhanced" href="doctor/medical-records.php">
                                            <i class="fas fa-notes-medical text-info"></i>
                                            <span>Medical Records</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item dropdown-item-enhanced" href="doctor/checkups.php">
                                            <i class="fas fa-stethoscope text-warning"></i>
                                            <span>Preliminary Checkups</span>
                                        </a>
                                    </li>
                                <?php elseif ($userRole === 'admin'): ?>
                                    <li>
                                        <a class="dropdown-item dropdown-item-enhanced" href="admin/users.php">
                                            <i class="fas fa-users-cog text-success"></i>
                                            <span>Manage Users</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item dropdown-item-enhanced" href="admin/reports.php">
                                            <i class="fas fa-chart-bar text-info"></i>
                                            <span>System Reports</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item dropdown-item-enhanced" href="admin/doctor_approvals.php">
                                            <i class="fas fa-user-md text-warning"></i>
                                            <span>Doctor Approvals</span>
                                        </a>
                                    </li>
                                <?php elseif ($userRole === 'receptionist'): ?>
                                    <li>
                                        <a class="dropdown-item dropdown-item-enhanced" href="receptionist/appointments.php">
                                            <i class="fas fa-calendar-alt text-success"></i>
                                            <span>Appointments</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item dropdown-item-enhanced" href="receptionist/patients.php">
                                            <i class="fas fa-user-injured text-info"></i>
                                            <span>Patients</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- Account Section -->
                                <li><hr class="dropdown-divider-enhanced"></li>
                                <li class="dropdown-section-label">Account</li>
                                <li>
                                    <a class="dropdown-item dropdown-item-enhanced" href="profile.php">
                                        <i class="fas fa-user-edit text-secondary"></i>
                                        <span>Profile Settings</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item dropdown-item-enhanced danger text-danger" href="logout.php">
                                        <i class="fas fa-sign-out-alt"></i>
                                        <span>Logout</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Guest navigation -->
                        <li class="nav-item">
                            <a class="animated-login-button ms-2" href="login.php">
                                <div class="login-points-wrapper">
                                    <i class="login-point"></i>
                                    <i class="login-point"></i>
                                    <i class="login-point"></i>
                                    <i class="login-point"></i>
                                    <i class="login-point"></i>
                                    <i class="login-point"></i>
                                    <i class="login-point"></i>
                                    <i class="login-point"></i>
                                    <i class="login-point"></i>
                                    <i class="login-point"></i>
                                </div>
                                <span class="login-button-inner">
                                    Login
                                    <svg class="login-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5">
                                        <path d="M5 12h14"></path>
                                        <path d="m12 5 7 7-7 7"></path>
                                    </svg>
                                </span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="animated-signup-button ms-2" href="signup.php">
                                <div class="signup-points-wrapper">
                                    <i class="signup-point"></i>
                                    <i class="signup-point"></i>
                                    <i class="signup-point"></i>
                                    <i class="signup-point"></i>
                                    <i class="signup-point"></i>
                                    <i class="signup-point"></i>
                                    <i class="signup-point"></i>
                                    <i class="signup-point"></i>
                                    <i class="signup-point"></i>
                                    <i class="signup-point"></i>
                                </div>
                                <span class="signup-button-inner">
                                    Sign Up
                                    <svg class="signup-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5">
                                        <path d="M5 12h14"></path>
                                        <path d="m12 5 7 7-7 7"></path>
                                    </svg>
                                </span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <!-- Floating Particles -->
        <div class="hero-particles">
            <span class="hero-particle"></span>
            <span class="hero-particle"></span>
            <span class="hero-particle"></span>
            <span class="hero-particle"></span>
            <span class="hero-particle"></span>
            <span class="hero-particle"></span>
            <span class="hero-particle"></span>
            <span class="hero-particle"></span>
        </div>

        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <?php if ($isLoggedIn): ?>
                        <!-- Personalized content for logged-in users -->
                        <h1 class="display-4 fw-bold mb-4">Welcome back, <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>!</h1>
                        <?php if ($userRole === 'patient'): ?>
                            <p class="lead mb-4">Ready to manage your health?
                            <div class="d-flex flex-wrap gap-3">
                                <a href="dashboard.php" class="btn btn-light btn-lg btn-custom">
                                    <i class="fas fa-tachometer-alt me-2"></i>My Dashboard
                                </a>
                                <a href="book-appointment.php" class="btn btn-light btn-lg btn-custom">
                                    <i class="fas fa-calendar-plus me-2"></i>Book Appointment
                                </a>
                            </div>
                        <?php elseif ($userRole === 'doctor'): ?>
                            <p class="lead mb-4">You have <?php echo $quickStats['today_appointments']; ?> appointment(s) scheduled for today. Ready to provide excellent care to your patients.</p>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="dashboard.php" class="btn btn-light btn-lg btn-custom">
                                    <i class="fas fa-tachometer-alt me-2"></i>Doctor Dashboard
                                </a>
                                <a href="appointments.php" class="btn btn-outline-light btn-lg btn-custom">
                                    <i class="fas fa-calendar-check me-2"></i>Today's Appointments
                                </a>
                            </div>
                        <?php elseif ($userRole === 'receptionist'): ?>
                            <p class="lead mb-4">Manage patient registrations, appointments, and provide excellent customer service through your dashboard.</p>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="dashboard.php" class="btn btn-light btn-lg btn-custom">
                                    <i class="fas fa-tachometer-alt me-2"></i>Reception Dashboard
                                </a>
                                <a href="appointments.php" class="btn btn-outline-light btn-lg btn-custom">
                                    <i class="fas fa-calendar-alt me-2"></i>Manage Appointments
                                </a>
                            </div>
                        <?php elseif ($userRole === 'admin'): ?>
                            <p class="lead mb-4">Monitor system performance, manage users, and oversee clinic operations through your administrative dashboard.</p>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="dashboard.php" class="btn btn-light btn-lg btn-custom">
                                    <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                                </a>
                                <a href="admin/reports.php" class="btn btn-outline-light btn-lg btn-custom">
                                    <i class="fas fa-chart-bar me-2"></i>System Reports
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Default content for guests -->
                        <h1 class="display-4 fw-bold mb-4">Your Health, Our Priority</h1>
                        <p class="lead mb-4">Experience modern healthcare with our comprehensive clinic management system. Book appointments, access medical records, and manage your health journey online.</p>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="signup.php" class="neuro-button neuro-button-raised">
                                <i class="fas fa-user-plus"></i>Register Now
                            </a>
                            <a href="login.php" class="neuro-button neuro-button-pressed">
                                <i class="fas fa-sign-in-alt"></i>Patient Portal
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-hospital-alt" style="font-size: 15rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Wave Divider - 6 Layered Design -->
        <svg class="hero-wave" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
            <!-- Layer 1 - Deepest (15% opacity) -->
            <path fill="#ffffff" fill-opacity="0.15" d="M0,192L48,197.3C96,203,192,213,288,197.3C384,181,480,139,576,133.3C672,128,768,160,864,170.7C960,181,1056,171,1152,154.7C1248,139,1344,117,1392,106.7L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
            <!-- Layer 2 (25% opacity) -->
            <path fill="#ffffff" fill-opacity="0.25" d="M0,224L48,213.3C96,203,192,181,288,181.3C384,181,480,203,576,213.3C672,224,768,224,864,213.3C960,203,1056,181,1152,181.3C1248,181,1344,203,1392,213.3L1440,224L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
            <!-- Layer 3 (40% opacity) -->
            <path fill="#ffffff" fill-opacity="0.4" d="M0,240L48,234.7C96,229,192,219,288,218.7C384,219,480,229,576,234.7C672,240,768,240,864,234.7C960,229,1056,219,1152,218.7C1248,219,1344,229,1392,234.7L1440,240L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
            <!-- Layer 4 (60% opacity) -->
            <path fill="#ffffff" fill-opacity="0.6" d="M0,256L48,245.3C96,235,192,213,288,213.3C384,213,480,235,576,245.3C672,256,768,256,864,245.3C960,235,1056,213,1152,213.3C1248,213,1344,235,1392,245.3L1440,256L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
            <!-- Layer 5 (80% opacity) -->
            <path fill="#ffffff" fill-opacity="0.8" d="M0,272L48,266.7C96,261,192,251,288,250.7C384,251,480,261,576,266.7C672,272,768,272,864,266.7C960,261,1056,251,1152,250.7C1248,251,1344,261,1392,266.7L1440,272L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
            <!-- Layer 6 - Front (100% opacity) -->
            <path fill="#ffffff" fill-opacity="1" d="M0,288L48,277.3C96,267,192,245,288,245.3C384,245,480,267,576,277.3C672,288,768,288,864,277.3C960,267,1056,245,1152,245.3C1248,245,1344,267,1392,277.3L1440,288L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
        </svg>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="mb-3">Why Choose Our Clinic?</h2>
                <p class="lead text-muted">Modern healthcare solutions designed for your convenience</p>
            </div>
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card feature-card feature-card-blue text-center p-4">
                        <i class="fas fa-calendar-check service-icon"></i>
                        <h5>Online Booking</h5>
                        <p class="text-muted">Schedule appointments 24/7 with our easy-to-use booking system.</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card feature-card feature-card-green text-center p-4">
                        <i class="fas fa-file-medical service-icon"></i>
                        <h5>Digital Records</h5>
                        <p class="text-muted">Access your medical history and prescriptions anytime, anywhere.</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card feature-card feature-card-yellow text-center p-4">
                        <i class="fas fa-user-md service-icon"></i>
                        <h5>Expert Doctors</h5>
                        <p class="text-muted">Experienced healthcare professionals dedicated to your wellbeing.</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card feature-card feature-card-red text-center p-4">
                        <i class="fas fa-shield-alt service-icon"></i>
                        <h5>Secure & Private</h5>
                        <p class="text-muted">Your health information is protected with advanced security measures.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
        <!-- Decorative Background Elements -->
        <div class="position-absolute top-0 start-0 w-100 h-100" style="opacity: 0.05;">
            <i class="fas fa-heartbeat position-absolute" style="font-size: 15rem; top: -5%; left: -5%; color: var(--primary-color);"></i>
            <i class="fas fa-user-md position-absolute" style="font-size: 12rem; bottom: -5%; right: -3%; color: var(--secondary-color);"></i>
        </div>

        <div class="container position-relative">
            <!-- Section Header -->
            <div class="text-center mb-5">
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 mb-3" style="font-size: 0.9rem; font-weight: 600;">
                    <i class="fas fa-hospital-alt me-2"></i>ABOUT US
                </span>
                <h2 class="display-4 fw-bold mb-3" style="color: var(--primary-color);">Excellence in Healthcare</h2>
                <p class="lead text-muted mx-auto" style="max-width: 700px;">
                    Combining compassionate care with cutting-edge technology to deliver exceptional medical services
                </p>
            </div>

            <!-- Main Content -->
            <div class="row align-items-center mb-5">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <div class="pe-lg-4">
                        <h3 class="h2 fw-bold mb-4" style="color: var(--dark-color);">
                            Your Health, Our Priority
                        </h3>
                        <p class="text-muted mb-4" style="font-size: 1.05rem; line-height: 1.8;">
                            At our clinic, we believe in providing personalized healthcare that puts you first. Our team of experienced medical professionals uses state-of-the-art technology and evidence-based practices to ensure you receive the highest quality care.
                        </p>
                        <p class="text-muted mb-4" style="font-size: 1.05rem; line-height: 1.8;">
                            With our comprehensive digital management system, we streamline every aspect of your healthcare journey—from appointment scheduling to medical records—making your experience seamless and stress-free.
                        </p>

                        <!-- Feature List with Icons -->
                        <div class="row g-3 mt-4">
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <div class="rounded-circle bg-primary bg-opacity-10 p-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user-md text-primary"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="fw-bold mb-1">Expert Doctors</h6>
                                        <p class="text-muted small mb-0">Highly qualified medical professionals</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <div class="rounded-circle bg-success bg-opacity-10 p-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-hospital text-success"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="fw-bold mb-1">Modern Facilities</h6>
                                        <p class="text-muted small mb-0">Advanced medical equipment</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <div class="rounded-circle bg-info bg-opacity-10 p-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-file-medical text-info"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="fw-bold mb-1">Digital Records</h6>
                                        <p class="text-muted small mb-0">Secure electronic health records</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <div class="rounded-circle bg-warning bg-opacity-10 p-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-heart text-warning"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="fw-bold mb-1">Patient-Centered</h6>
                                        <p class="text-muted small mb-0">Compassionate care approach</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="position-relative">
                        <!-- Image Placeholder with Overlay -->
                        <div class="rounded-4 overflow-hidden shadow-lg" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); height: 450px; position: relative;">
                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                                <div class="text-center text-white p-4">
                                    <i class="fas fa-stethoscope mb-4" style="font-size: 6rem; opacity: 0.9;"></i>
                                    <h4 class="fw-bold mb-3">Trusted Healthcare Provider</h4>
                                    <p class="mb-0" style="font-size: 1.1rem;">Serving our community with dedication and excellence</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Section -->
            <div class="row g-4 mt-4">
                <div class="col-md-3 col-sm-6">
                    <div class="text-center p-4 bg-white rounded-3 shadow-sm h-100 border border-primary border-opacity-10">
                        <div class="mb-3">
                            <i class="fas fa-users text-primary" style="font-size: 2.5rem;"></i>
                        </div>
                        <h3 class="fw-bold mb-2" style="color: var(--primary-color);">10,000+</h3>
                        <p class="text-muted mb-0">Happy Patients</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="text-center p-4 bg-white rounded-3 shadow-sm h-100 border border-success border-opacity-10">
                        <div class="mb-3">
                            <i class="fas fa-user-md text-success" style="font-size: 2.5rem;"></i>
                        </div>
                        <h3 class="fw-bold mb-2" style="color: var(--success-color);">50+</h3>
                        <p class="text-muted mb-0">Medical Experts</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="text-center p-4 bg-white rounded-3 shadow-sm h-100 border border-info border-opacity-10">
                        <div class="mb-3">
                            <i class="fas fa-procedures text-info" style="font-size: 2.5rem;"></i>
                        </div>
                        <h3 class="fw-bold mb-2" style="color: var(--info-color);">5</h3>
                        <p class="text-muted mb-0">Specialized Departments</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="text-center p-4 bg-white rounded-3 shadow-sm h-100 border border-warning border-opacity-10">
                        <div class="mb-3">
                            <i class="fas fa-clock text-warning" style="font-size: 2.5rem;"></i>
                        </div>
                        <h3 class="fw-bold mb-2" style="color: var(--warning-color);">24/7</h3>
                        <p class="text-muted mb-0">Emergency Service</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-5 position-relative overflow-hidden">
        <!-- Animated Background -->
        <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 50%, #e9ecef 100%); opacity: 0.7;"></div>
        <div class="position-absolute" style="top: 10%; right: -5%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(13, 110, 253, 0.1) 0%, transparent 70%); border-radius: 50%;"></div>
        <div class="position-absolute" style="bottom: 10%; left: -5%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(25, 135, 84, 0.1) 0%, transparent 70%); border-radius: 50%;"></div>

        <div class="container position-relative">
            <!-- Section Header -->
            <div class="text-center mb-5">
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 mb-3" style="font-size: 0.9rem; font-weight: 600;">
                    <i class="fas fa-briefcase-medical me-2"></i>OUR SERVICES
                </span>
                <h2 class="display-4 fw-bold mb-3" style="color: var(--primary-color);">Comprehensive Healthcare Solutions</h2>
                <p class="lead text-muted mx-auto" style="max-width: 700px;">
                    From routine checkups to specialized care, we provide a full spectrum of medical services tailored to your needs
                </p>
            </div>

            <!-- Services Grid -->
            <div class="row g-4">
                <!-- Service Card 1 -->
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden" style="transition: all 0.3s ease; border-radius: 20px;">
                        <div class="position-absolute top-0 end-0 p-3">
                            <div class="rounded-circle bg-primary bg-opacity-10" style="width: 80px; height: 80px; position: absolute; top: -20px; right: -20px;"></div>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-4 p-3" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); width: 70px; height: 70px;">
                                    <i class="fas fa-heartbeat text-white" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                            <h5 class="card-title fw-bold mb-3" style="color: var(--dark-color);">General Consultation</h5>
                            <p class="card-text text-muted mb-3" style="line-height: 1.7;">Comprehensive health checkups and routine medical consultations with our experienced doctors.</p>
                            <a href="signup.php" class="text-primary fw-semibold text-decoration-none">
                                Learn More <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Service Card 2 -->
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden" style="transition: all 0.3s ease; border-radius: 20px;">
                        <div class="position-absolute top-0 end-0 p-3">
                            <div class="rounded-circle bg-success bg-opacity-10" style="width: 80px; height: 80px; position: absolute; top: -20px; right: -20px;"></div>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-4 p-3" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%); width: 70px; height: 70px;">
                                    <i class="fas fa-pills text-white" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                            <h5 class="card-title fw-bold mb-3" style="color: var(--dark-color);">Prescription Management</h5>
                            <p class="card-text text-muted mb-3" style="line-height: 1.7;">Digital prescription tracking and medication management with easy online access.</p>
                            <a href="signup.php" class="text-success fw-semibold text-decoration-none">
                                Learn More <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Service Card 3 -->
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden" style="transition: all 0.3s ease; border-radius: 20px;">
                        <div class="position-absolute top-0 end-0 p-3">
                            <div class="rounded-circle bg-info bg-opacity-10" style="width: 80px; height: 80px; position: absolute; top: -20px; right: -20px;"></div>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-4 p-3" style="background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%); width: 70px; height: 70px;">
                                    <i class="fas fa-notes-medical text-white" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                            <h5 class="card-title fw-bold mb-3" style="color: var(--dark-color);">Medical Records</h5>
                            <p class="card-text text-muted mb-3" style="line-height: 1.7;">Complete digital medical history accessible to you and your healthcare providers.</p>
                            <a href="signup.php" class="text-info fw-semibold text-decoration-none">
                                Learn More <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Service Card 4 -->
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden" style="transition: all 0.3s ease; border-radius: 20px;">
                        <div class="position-absolute top-0 end-0 p-3">
                            <div class="rounded-circle bg-warning bg-opacity-10" style="width: 80px; height: 80px; position: absolute; top: -20px; right: -20px;"></div>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-4 p-3" style="background: linear-gradient(135deg, #ffc107 0%, #cc9a06 100%); width: 70px; height: 70px;">
                                    <i class="fas fa-thermometer text-white" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                            <h5 class="card-title fw-bold mb-3" style="color: var(--dark-color);">Health Checkups</h5>
                            <p class="card-text text-muted mb-3" style="line-height: 1.7;">Regular health screenings and preliminary checkups by our nursing staff.</p>
                            <a href="signup.php" class="text-warning fw-semibold text-decoration-none">
                                Learn More <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Service Card 5 -->
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden" style="transition: all 0.3s ease; border-radius: 20px;">
                        <div class="position-absolute top-0 end-0 p-3">
                            <div class="rounded-circle bg-danger bg-opacity-10" style="width: 80px; height: 80px; position: absolute; top: -20px; right: -20px;"></div>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-4 p-3" style="background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); width: 70px; height: 70px;">
                                    <i class="fas fa-calendar-alt text-white" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                            <h5 class="card-title fw-bold mb-3" style="color: var(--dark-color);">Appointment Management</h5>
                            <p class="card-text text-muted mb-3" style="line-height: 1.7;">Easy online booking, rescheduling, and appointment tracking system.</p>
                            <a href="signup.php" class="text-danger fw-semibold text-decoration-none">
                                Learn More <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Service Card 6 -->
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden" style="transition: all 0.3s ease; border-radius: 20px;">
                        <div class="position-absolute top-0 end-0 p-3">
                            <div class="rounded-circle bg-secondary bg-opacity-10" style="width: 80px; height: 80px; position: absolute; top: -20px; right: -20px;"></div>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-4 p-3" style="background: linear-gradient(135deg, #6c757d 0%, #565e64 100%); width: 70px; height: 70px;">
                                    <i class="fas fa-ambulance text-white" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                            <h5 class="card-title fw-bold mb-3" style="color: var(--dark-color);">Emergency Support</h5>
                            <p class="card-text text-muted mb-3" style="line-height: 1.7;">24/7 emergency contact and urgent care coordination services.</p>
                            <a href="signup.php" class="text-secondary fw-semibold text-decoration-none">
                                Learn More <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-5 position-relative" style="background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);">
        <div class="container">
            <!-- Section Header -->
            <div class="text-center mb-5">
                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 mb-3" style="font-size: 0.9rem; font-weight: 600;">
                    <i class="fas fa-route me-2"></i>SIMPLE PROCESS
                </span>
                <h2 class="display-4 fw-bold mb-3" style="color: var(--primary-color);">How It Works</h2>
                <p class="lead text-muted mx-auto" style="max-width: 700px;">
                    Getting started with our clinic is simple and straightforward. Follow these easy steps
                </p>
            </div>

            <!-- Steps Timeline -->
            <div class="row position-relative">
                <!-- Connection Line -->
                <div class="position-absolute top-50 start-0 w-100 d-none d-md-block" style="height: 2px; background: linear-gradient(90deg, transparent 0%, #0d6efd 10%, #0d6efd 90%, transparent 100%); z-index: 0; transform: translateY(-50%);"></div>

                <!-- Step 1 -->
                <div class="col-md-3 mb-4 position-relative">
                    <div class="text-center h-100 d-flex flex-column">
                        <div class="mb-4 position-relative" style="z-index: 1;">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle shadow-lg mx-auto" style="width: 100px; height: 100px; background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
                                <div class="text-center">
                                    <i class="fas fa-user-plus text-white mb-1" style="font-size: 2rem;"></i>
                                    <div class="position-absolute bottom-0 end-0 bg-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; border: 3px solid #0d6efd;">
                                        <span class="fw-bold text-primary" style="font-size: 0.9rem;">1</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-4 shadow-sm p-4 flex-grow-1">
                            <h5 class="fw-bold mb-3" style="color: #000000;">Register Account</h5>
                            <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.6;">Create your patient account with your basic information and medical details</p>
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="col-md-3 mb-4 position-relative">
                    <div class="text-center h-100 d-flex flex-column">
                        <div class="mb-4 position-relative" style="z-index: 1;">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle shadow-lg mx-auto" style="width: 100px; height: 100px; background: linear-gradient(135deg, #198754 0%, #146c43 100%);">
                                <div class="text-center">
                                    <i class="fas fa-calendar-check text-white mb-1" style="font-size: 2rem;"></i>
                                    <div class="position-absolute bottom-0 end-0 bg-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; border: 3px solid #198754;">
                                        <span class="fw-bold text-success" style="font-size: 0.9rem;">2</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-4 shadow-sm p-4 flex-grow-1">
                            <h5 class="fw-bold mb-3" style="color: #000000;">Book Appointment</h5>
                            <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.6;">Schedule your visit with available doctors at your convenient time</p>
                        </div>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="col-md-3 mb-4 position-relative">
                    <div class="text-center h-100 d-flex flex-column">
                        <div class="mb-4 position-relative" style="z-index: 1;">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle shadow-lg mx-auto" style="width: 100px; height: 100px; background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%);">
                                <div class="text-center">
                                    <i class="fas fa-hospital-user text-white mb-1" style="font-size: 2rem;"></i>
                                    <div class="position-absolute bottom-0 end-0 bg-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; border: 3px solid #0dcaf0;">
                                        <span class="fw-bold text-info" style="font-size: 0.9rem;">3</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-4 shadow-sm p-4 flex-grow-1">
                            <h5 class="fw-bold mb-3" style="color: var(--info-color);">Visit Clinic</h5>
                            <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.6;">Attend your appointment and receive quality healthcare from our professionals</p>
                        </div>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="col-md-3 mb-4 position-relative">
                    <div class="text-center h-100 d-flex flex-column">
                        <div class="mb-4 position-relative" style="z-index: 1;">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle shadow-lg mx-auto" style="width: 100px; height: 100px; background: linear-gradient(135deg, #ffc107 0%, #cc9a06 100%);">
                                <div class="text-center">
                                    <i class="fas fa-file-medical-alt text-white mb-1" style="font-size: 2rem;"></i>
                                    <div class="position-absolute bottom-0 end-0 bg-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; border: 3px solid #ffc107;">
                                        <span class="fw-bold text-warning" style="font-size: 0.9rem;">4</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-4 shadow-sm p-4 flex-grow-1">
                            <h5 class="fw-bold mb-3" style="color: var(--warning-color);">Access Records</h5>
                            <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.6;">View your medical records, prescriptions, and appointment history online</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA Button -->
            <div class="text-center mt-5">
                <a href="signup.php" class="btn btn-primary btn-lg px-5 py-3 rounded-pill shadow-lg" style="font-weight: 600;">
                    <i class="fas fa-rocket me-2"></i>Get Started Now
                </a>
            </div>
        </div>
    </section>

    <!-- Meet Our Doctors Section -->
    <section class="py-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
        <!-- Decorative Elements -->
        <div class="position-absolute" style="top: 20%; left: -10%; width: 250px; height: 250px; background: radial-gradient(circle, rgba(13, 110, 253, 0.08) 0%, transparent 70%); border-radius: 50%;"></div>
        <div class="position-absolute" style="bottom: 20%; right: -10%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(25, 135, 84, 0.08) 0%, transparent 70%); border-radius: 50%;"></div>

        <div class="container position-relative">
            <!-- Section Header -->
            <div class="text-center mb-5">
                <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 mb-3" style="font-size: 0.9rem; font-weight: 600;">
                    <i class="fas fa-user-md me-2"></i>OUR TEAM
                </span>
                <h2 class="display-4 fw-bold mb-3" style="color: var(--primary-color);">Meet Our Doctors</h2>
                <p class="lead text-muted mx-auto" style="max-width: 700px;">
                    Experienced healthcare professionals dedicated to your wellbeing and committed to excellence
                </p>
            </div>

            <!-- Doctors Grid -->
            <div class="row g-4">
                <!-- Doctor Card 1 -->
                <div class="col-lg-4 col-md-6">
                    <div class="card border-0 shadow-lg h-100 overflow-hidden" style="border-radius: 25px; transition: transform 0.3s ease;">
                        <!-- Card Header with Gradient -->
                        <div class="position-relative" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); padding: 30px 20px 80px;">
                            <div class="text-center text-white">
                                <div class="mb-2">
                                    <i class="fas fa-quote-left" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                </div>
                                <p class="mb-0" style="font-size: 0.9rem; font-style: italic;">"Committed to providing compassionate care"</p>
                            </div>
                        </div>

                        <!-- Doctor Avatar -->
                        <div class="position-absolute start-50 translate-middle" style="top: 140px;">
                            <div class="position-relative">
                                <div class="rounded-circle bg-white p-3 shadow-lg" style="width: 130px; height: 130px;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center h-100" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
                                        <i class="fas fa-user-md text-white" style="font-size: 3rem;"></i>
                                    </div>
                                </div>
                                <div class="position-absolute bottom-0 end-0 bg-success rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; border: 3px solid white;">
                                    <i class="fas fa-check text-white" style="font-size: 0.8rem;"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body text-center pt-5 pb-4" style="margin-top: 50px;">
                            <h5 class="fw-bold mb-1" style="color: var(--dark-color);">Dr. John Smith</h5>
                            <p class="text-primary fw-semibold mb-3" style="font-size: 0.95rem;">General Medicine</p>

                            <!-- Info Badges -->
                            <div class="d-flex flex-wrap gap-2 justify-content-center mb-3">
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                                    <i class="fas fa-graduation-cap me-1"></i>15+ Years
                                </span>
                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2">
                                    <i class="fas fa-certificate me-1"></i>Certified
                                </span>
                            </div>

                            <!-- Schedule -->
                            <div class="bg-light rounded-3 p-3 mb-3">
                                <small class="text-muted d-flex align-items-center justify-content-center">
                                    <i class="fas fa-clock me-2 text-primary"></i>
                                    <span>Mon-Fri: 9AM-5PM</span>
                                </small>
                            </div>

                            <p class="text-muted small mb-4" style="line-height: 1.6;">Specializes in preventive care, chronic disease management, and family medicine.</p>

                            <!-- Action Buttons -->
                            <div class="d-grid gap-2">
                                <a href="signup.php" class="btn btn-primary rounded-pill text-white" style="color: #ffffff !important;">
                                    <i class="fas fa-calendar-check me-2"></i>Book Appointment
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Doctor Card 2 -->
                <div class="col-lg-4 col-md-6">
                    <div class="card border-0 shadow-lg h-100 overflow-hidden" style="border-radius: 25px; transition: transform 0.3s ease;">
                        <!-- Card Header with Gradient -->
                        <div class="position-relative" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%); padding: 30px 20px 80px;">
                            <div class="text-center text-white">
                                <div class="mb-2">
                                    <i class="fas fa-quote-left" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                </div>
                                <p class="mb-0" style="font-size: 0.9rem; font-style: italic;">"Your heart health is my priority"</p>
                            </div>
                        </div>

                        <!-- Doctor Avatar -->
                        <div class="position-absolute start-50 translate-middle" style="top: 140px;">
                            <div class="position-relative">
                                <div class="rounded-circle bg-white p-3 shadow-lg" style="width: 130px; height: 130px;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center h-100" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%);">
                                        <i class="fas fa-user-md text-white" style="font-size: 3rem;"></i>
                                    </div>
                                </div>
                                <div class="position-absolute bottom-0 end-0 bg-success rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; border: 3px solid white;">
                                    <i class="fas fa-check text-white" style="font-size: 0.8rem;"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body text-center pt-5 pb-4" style="margin-top: 50px;">
                            <h5 class="fw-bold mb-1" style="color: var(--dark-color);">Dr. Sarah Johnson</h5>
                            <p class="text-success fw-semibold mb-3" style="font-size: 0.95rem;">Cardiology</p>

                            <!-- Info Badges -->
                            <div class="d-flex flex-wrap gap-2 justify-content-center mb-3">
                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2">
                                    <i class="fas fa-graduation-cap me-1"></i>12+ Years
                                </span>
                                <span class="badge bg-info bg-opacity-10 text-info px-3 py-2">
                                    <i class="fas fa-certificate me-1"></i>Specialist
                                </span>
                            </div>

                            <!-- Schedule -->
                            <div class="bg-light rounded-3 p-3 mb-3">
                                <small class="text-muted d-flex align-items-center justify-content-center">
                                    <i class="fas fa-clock me-2 text-success"></i>
                                    <span>Tue-Thu: 10AM-6PM</span>
                                </small>
                            </div>

                            <p class="text-muted small mb-4" style="line-height: 1.6;">Expert in heart health, cardiovascular diseases, and cardiac rehabilitation programs.</p>

                            <!-- Action Buttons -->
                            <div class="d-grid gap-2">
                                <a href="signup.php" class="btn btn-success rounded-pill text-white" style="color: #ffffff !important;">
                                    <i class="fas fa-calendar-check me-2"></i>Book Appointment
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Doctor Card 3 -->
                <div class="col-lg-4 col-md-6">
                    <div class="card border-0 shadow-lg h-100 overflow-hidden" style="border-radius: 25px; transition: transform 0.3s ease;">
                        <!-- Card Header with Gradient -->
                        <div class="position-relative" style="background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%); padding: 30px 20px 80px;">
                            <div class="text-center text-white">
                                <div class="mb-2">
                                    <i class="fas fa-quote-left" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                </div>
                                <p class="mb-0" style="font-size: 0.9rem; font-style: italic;">"Gentle care for your little ones"</p>
                            </div>
                        </div>

                        <!-- Doctor Avatar -->
                        <div class="position-absolute start-50 translate-middle" style="top: 140px;">
                            <div class="position-relative">
                                <div class="rounded-circle bg-white p-3 shadow-lg" style="width: 130px; height: 130px;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center h-100" style="background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%);">
                                        <i class="fas fa-user-md text-white" style="font-size: 3rem;"></i>
                                    </div>
                                </div>
                                <div class="position-absolute bottom-0 end-0 bg-success rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; border: 3px solid white;">
                                    <i class="fas fa-check text-white" style="font-size: 0.8rem;"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body text-center pt-5 pb-4" style="margin-top: 50px;">
                            <h5 class="fw-bold mb-1" style="color: var(--dark-color);">Dr. Michael Chen</h5>
                            <p class="text-info fw-semibold mb-3" style="font-size: 0.95rem;">Pediatrics</p>

                            <!-- Info Badges -->
                            <div class="d-flex flex-wrap gap-2 justify-content-center mb-3">
                                <span class="badge bg-info bg-opacity-10 text-info px-3 py-2">
                                    <i class="fas fa-graduation-cap me-1"></i>10+ Years
                                </span>
                                <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2">
                                    <i class="fas fa-certificate me-1"></i>Specialist
                                </span>
                            </div>

                            <!-- Schedule -->
                            <div class="bg-light rounded-3 p-3 mb-3">
                                <small class="text-muted d-flex align-items-center justify-content-center">
                                    <i class="fas fa-clock me-2 text-info"></i>
                                    <span>Mon-Wed-Fri: 8AM-4PM</span>
                                </small>
                            </div>

                            <p class="text-muted small mb-4" style="line-height: 1.6;">Dedicated to children's health, from newborns to adolescents, with gentle care approach.</p>

                            <!-- Action Buttons -->
                            <div class="d-grid gap-2">
                                <a href="signup.php" class="btn btn-info rounded-pill text-white" style="color: #ffffff !important;">
                                    <i class="fas fa-calendar-check me-2"></i>Book Appointment
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Info -->
            <div class="text-center mt-5">
                <div class="bg-white rounded-4 shadow-sm p-4 d-inline-block">
                    <p class="text-muted mb-0">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        All our doctors are available through our online booking system.
                        <a href="signup.php" class="text-primary fw-semibold text-decoration-none">Register now</a> to see available appointment slots.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-5 position-relative overflow-hidden" style="background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);">
        <!-- Decorative Background -->
        <div class="position-absolute top-0 start-0 w-100 h-100" style="opacity: 0.03;">
            <i class="fas fa-dollar-sign position-absolute" style="font-size: 20rem; top: 10%; left: 5%; color: var(--primary-color);"></i>
            <i class="fas fa-hand-holding-medical position-absolute" style="font-size: 18rem; bottom: 10%; right: 5%; color: var(--success-color);"></i>
        </div>

        <div class="container position-relative">
            <!-- Section Header -->
            <div class="text-center mb-5">
                <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 mb-3" style="font-size: 0.9rem; font-weight: 600;">
                    <i class="fas fa-tags me-2"></i>PRICING
                </span>
                <h2 class="display-4 fw-bold mb-3" style="color: var(--primary-color);">Transparent Pricing</h2>
                <p class="lead text-muted mx-auto" style="max-width: 700px;">
                    Quality healthcare services at affordable prices. No hidden fees, just honest care
                </p>
            </div>

            <!-- Pricing Cards -->
            <div class="row g-4 align-items-center">
                <!-- Pricing Card 1 -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-lg h-100 overflow-hidden" style="border-radius: 25px; transition: transform 0.3s ease;">
                        <div class="card-body p-0">
                            <!-- Header -->
                            <div class="text-center p-4" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
                                <div class="mb-3">
                                    <i class="fas fa-heartbeat text-white" style="font-size: 3rem;"></i>
                                </div>
                                <h5 class="text-white fw-bold mb-2">General Consultation</h5>
                                <p class="text-white-50 small mb-0">Perfect for routine checkups</p>
                            </div>

                            <!-- Price -->
                            <div class="text-center py-4 bg-white">
                                <div class="d-flex align-items-center justify-content-center mb-2">
                                    <span class="h2 fw-bold text-primary mb-0">$50</span>
                                    <span class="text-muted ms-2">/visit</span>
                                </div>
                            </div>

                            <!-- Features -->
                            <div class="px-4 pb-4">
                                <ul class="list-unstyled mb-4">
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success me-3 mt-1"></i>
                                        <span class="text-muted">Basic health assessment</span>
                                    </li>
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success me-3 mt-1"></i>
                                        <span class="text-muted">Medical consultation</span>
                                    </li>
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success me-3 mt-1"></i>
                                        <span class="text-muted">Prescription if needed</span>
                                    </li>
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success me-3 mt-1"></i>
                                        <span class="text-muted">Follow-up recommendations</span>
                                    </li>
                                </ul>
                                <a href="signup.php" class="btn btn-primary w-100 rounded-pill py-3 fw-semibold text-white" style="color: #ffffff !important;">
                                    <i class="fas fa-calendar-check me-2"></i>Book Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing Card 2 - Featured -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-lg h-100 overflow-hidden position-relative" style="border-radius: 25px; transform: scale(1.05); transition: transform 0.3s ease;">
                        <!-- Popular Badge -->
                        <div class="position-absolute top-0 end-0 m-3" style="z-index: 10;">
                            <span class="badge bg-warning text-dark px-3 py-2 shadow-sm" style="font-size: 0.85rem; font-weight: 600;">
                                <i class="fas fa-star me-1"></i>MOST POPULAR
                            </span>
                        </div>

                        <div class="card-body p-0">
                            <!-- Header -->
                            <div class="text-center p-4" style="background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%);">
                                <div class="mb-3">
                                    <i class="fas fa-user-md text-white" style="font-size: 3rem;"></i>
                                </div>
                                <h5 class="text-white fw-bold mb-2">Specialist Consultation</h5>
                                <p class="text-white-50 small mb-0">Expert medical care</p>
                            </div>

                            <!-- Price -->
                            <div class="text-center py-4 bg-white">
                                <div class="d-flex align-items-center justify-content-center mb-2">
                                    <span class="h2 fw-bold text-info mb-0">$85</span>
                                    <span class="text-muted ms-2">/session</span>
                                </div>
                            </div>

                            <!-- Features -->
                            <div class="px-4 pb-4">
                                <ul class="list-unstyled mb-4">
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success me-3 mt-1"></i>
                                        <span class="text-muted">Specialized medical consultation</span>
                                    </li>
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success me-3 mt-1"></i>
                                        <span class="text-muted">Detailed diagnosis</span>
                                    </li>
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success me-3 mt-1"></i>
                                        <span class="text-muted">Treatment plan</span>
                                    </li>
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success me-3 mt-1"></i>
                                        <span class="text-muted">Digital medical records</span>
                                    </li>
                                </ul>
                                <a href="signup.php" class="btn btn-info w-100 rounded-pill py-3 fw-semibold shadow text-white" style="color: #ffffff !important;">
                                    <i class="fas fa-calendar-check me-2"></i>Book Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing Card 3 -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-lg h-100 overflow-hidden" style="border-radius: 25px; transition: transform 0.3s ease;">
                        <div class="card-body p-0">
                            <!-- Header -->
                            <div class="text-center p-4" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%);">
                                <div class="mb-3">
                                    <i class="fas fa-clipboard-check text-white" style="font-size: 3rem;"></i>
                                </div>
                                <h5 class="text-white fw-bold mb-2">Health Checkup</h5>
                                <p class="text-white-50 small mb-0">Comprehensive examination</p>
                            </div>

                            <!-- Price -->
                            <div class="text-center py-4 bg-white">
                                <div class="d-flex align-items-center justify-content-center mb-2">
                                    <span class="h2 fw-bold text-success mb-0">$120</span>
                                    <span class="text-muted ms-2">/package</span>
                                </div>
                            </div>

                            <!-- Features -->
                            <div class="px-4 pb-4">
                                <ul class="list-unstyled mb-4">
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success me-3 mt-1"></i>
                                        <span class="text-muted">Complete physical examination</span>
                                    </li>
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success me-3 mt-1"></i>
                                        <span class="text-muted">Vital signs monitoring</span>
                                    </li>
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success me-3 mt-1"></i>
                                        <span class="text-muted">Basic lab tests</span>
                                    </li>
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success me-3 mt-1"></i>
                                        <span class="text-muted">Health report & recommendations</span>
                                    </li>
                                </ul>
                                <a href="signup.php" class="btn btn-success w-100 rounded-pill py-3 fw-semibold text-white" style="color: #ffffff !important;">
                                    <i class="fas fa-calendar-check me-2"></i>Book Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Info -->
            <div class="text-center mt-5">
                <div class="bg-white rounded-4 shadow-sm p-4 d-inline-block">
                    <p class="text-muted mb-0">
                        <i class="fas fa-shield-alt me-2 text-success"></i>
                        All prices include digital medical records and online appointment management.
                        <a href="login.php" class="text-primary fw-semibold text-decoration-none">Login</a> to view your personalized pricing
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="position-relative overflow-hidden py-5" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
        <!-- Animated Background Pattern -->
        <div class="position-absolute top-0 start-0 w-100 h-100" style="opacity: 0.1;">
            <div class="position-absolute" style="top: -10%; left: -5%; width: 300px; height: 300px; background: radial-gradient(circle, white 0%, transparent 70%); border-radius: 50%;"></div>
            <div class="position-absolute" style="bottom: -10%; right: -5%; width: 400px; height: 400px; background: radial-gradient(circle, white 0%, transparent 70%); border-radius: 50%;"></div>
        </div>

        <div class="container text-center position-relative py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Icon -->
                    <div class="mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-10 p-4 mb-3">
                            <i class="fas fa-rocket text-white" style="font-size: 3rem;"></i>
                        </div>
                    </div>

                    <!-- Heading -->
                    <h2 class="display-4 fw-bold text-white mb-4">Ready to Get Started?</h2>
                    <p class="lead text-white mb-5" style="opacity: 0.9; font-size: 1.3rem;">
                        Join thousands of patients who trust us with their healthcare journey. Experience modern medical care today.
                    </p>

                    <!-- CTA Buttons -->
                    <div class="d-flex justify-content-center flex-wrap gap-3 mb-4">
                        <a href="signup.php" class="btn btn-light btn-lg px-5 py-3 rounded-pill shadow-lg" style="font-weight: 600;">
                            <i class="fas fa-user-plus me-2"></i>Register as Patient
                        </a>
                        <a href="login.php" class="btn btn-outline-light btn-lg px-5 py-3 rounded-pill" style="font-weight: 600; border-width: 2px;">
                            <i class="fas fa-sign-in-alt me-2"></i>Login to Portal
                        </a>
                    </div>

                    <!-- Trust Indicators -->
                    <div class="row g-4 mt-4">
                        <div class="col-md-4">
                            <div class="text-white">
                                <i class="fas fa-shield-alt mb-2" style="font-size: 2rem; opacity: 0.8;"></i>
                                <p class="mb-0 small" style="opacity: 0.9;">Secure & Private</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-white">
                                <i class="fas fa-clock mb-2" style="font-size: 2rem; opacity: 0.8;"></i>
                                <p class="mb-0 small" style="opacity: 0.9;">24/7 Support</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-white">
                                <i class="fas fa-award mb-2" style="font-size: 2rem; opacity: 0.8;"></i>
                                <p class="mb-0 small" style="opacity: 0.9;">Certified Professionals</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5 position-relative" style="background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);">
        <div class="container">
            <!-- Section Header -->
            <div class="text-center mb-5">
                <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 mb-3" style="font-size: 0.9rem; font-weight: 600;">
                    <i class="fas fa-phone-alt me-2"></i>GET IN TOUCH
                </span>
                <h2 class="display-4 fw-bold mb-3" style="color: var(--primary-color);">Contact Us</h2>
                <p class="lead text-muted mx-auto" style="max-width: 700px;">
                    Have questions? We're here to help. Reach out to us through any of these channels
                </p>
            </div>

            <!-- Contact Cards -->
            <div class="row g-4">
                <!-- Address Card -->
                <div class="col-lg-4 col-md-6">
                    <div class="card border-0 shadow-lg h-100 overflow-hidden" style="border-radius: 20px; transition: transform 0.3s ease;">
                        <div class="card-body p-4 text-center">
                            <!-- Icon -->
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-4 p-3" style="background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); width: 80px; height: 80px;">
                                    <i class="fas fa-map-marker-alt text-white" style="font-size: 2.5rem;"></i>
                                </div>
                            </div>

                            <!-- Content -->
                            <h5 class="fw-bold mb-3" style="color: var(--dark-color);">Visit Our Clinic</h5>
                            <p class="text-muted mb-2" style="line-height: 1.8;">
                                123 Healthcare Street<br>
                                Medical District<br>
                                City, State 12345
                            </p>
                            <a href="#" class="btn btn-outline-danger btn-sm rounded-pill mt-3">
                                <i class="fas fa-directions me-2"></i>Get Directions
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Phone Card -->
                <div class="col-lg-4 col-md-6">
                    <div class="card border-0 shadow-lg h-100 overflow-hidden" style="border-radius: 20px; transition: transform 0.3s ease;">
                        <div class="card-body p-4 text-center">
                            <!-- Icon -->
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-4 p-3" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%); width: 80px; height: 80px;">
                                    <i class="fas fa-phone text-white" style="font-size: 2.5rem;"></i>
                                </div>
                            </div>

                            <!-- Content -->
                            <h5 class="fw-bold mb-3" style="color: var(--dark-color);">Call Us</h5>
                            <div class="text-muted mb-2" style="line-height: 1.8;">
                                <div class="mb-2">
                                    <small class="d-block text-success fw-semibold">Emergency</small>
                                    <span>(555) 911-HELP</span>
                                </div>
                                <div class="mb-2">
                                    <small class="d-block text-primary fw-semibold">Appointments</small>
                                    <span>(555) 123-CARE</span>
                                </div>
                                <div>
                                    <small class="d-block text-secondary fw-semibold">General</small>
                                    <span>(555) 456-7890</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hours Card -->
                <div class="col-lg-4 col-md-6">
                    <div class="card border-0 shadow-lg h-100 overflow-hidden" style="border-radius: 20px; transition: transform 0.3s ease;">
                        <div class="card-body p-4 text-center">
                            <!-- Icon -->
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-4 p-3" style="background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%); width: 80px; height: 80px;">
                                    <i class="fas fa-clock text-white" style="font-size: 2.5rem;"></i>
                                </div>
                            </div>

                            <!-- Content -->
                            <h5 class="fw-bold mb-3" style="color: var(--dark-color);">Working Hours</h5>
                            <div class="text-muted mb-2" style="line-height: 1.8;">
                                <div class="d-flex justify-content-between mb-2 px-3">
                                    <span class="fw-semibold">Mon - Fri</span>
                                    <span>8:00 AM - 8:00 PM</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 px-3">
                                    <span class="fw-semibold">Sat - Sun</span>
                                    <span>9:00 AM - 5:00 PM</span>
                                </div>
                                <div class="mt-3">
                                    <span class="badge bg-success px-3 py-2">
                                        <i class="fas fa-ambulance me-1"></i>Emergency: 24/7
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Contact Info -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="bg-white rounded-4 shadow-sm p-4 text-center">
                        <h5 class="fw-bold mb-3" style="color: var(--dark-color);">
                            <i class="fas fa-envelope text-primary me-2"></i>Email Us
                        </h5>
                        <p class="text-muted mb-3">For non-urgent inquiries, you can also reach us via email</p>
                        <div class="d-flex flex-wrap justify-content-center gap-3">
                            <a href="mailto:info@careaidclinic.com" class="text-decoration-none">
                                <span class="badge bg-primary bg-opacity-10 text-primary px-4 py-3" style="font-size: 0.95rem;">
                                    <i class="fas fa-envelope me-2"></i>info@careaidclinic.com
                                </span>
                            </a>
                            <a href="mailto:appointments@careaidclinic.com" class="text-decoration-none">
                                <span class="badge bg-success bg-opacity-10 text-success px-4 py-3" style="font-size: 0.95rem;">
                                    <i class="fas fa-calendar me-2"></i>appointments@careaidclinic.com
                                </span>
                            </a>
                            <a href="mailto:emergency@careaidclinic.com" class="text-decoration-none">
                                <span class="badge bg-danger bg-opacity-10 text-danger px-4 py-3" style="font-size: 0.95rem;">
                                    <i class="fas fa-ambulance me-2"></i>emergency@careaidclinic.com
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="position-relative overflow-hidden" style="background: linear-gradient(135deg, #1a1d29 0%, #2d3142 100%);">
        <!-- Decorative Elements -->
        <div class="position-absolute top-0 start-0 w-100 h-100" style="opacity: 0.03;">
            <i class="fas fa-heartbeat position-absolute" style="font-size: 15rem; top: -20%; left: -5%;"></i>
            <i class="fas fa-stethoscope position-absolute" style="font-size: 12rem; bottom: -15%; right: -3%;"></i>
        </div>

        <div class="container position-relative">
            <!-- Main Footer Content -->
            <div class="row py-5">
                <!-- Brand Column -->
                <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <img src="img/LOGO_CLINIC-removebg-preview.png" alt="CareAid Clinic Logo" style="height: 55px; width: auto; margin-right: 12px; filter: brightness(0) invert(1);">
                            <h4 class="mb-0 fw-bold text-white">CareAid Clinic</h4>
                        </div>
                        <p class="text-white-50 mb-4" style="line-height: 1.8;">
                            Your health, our priority. Providing quality healthcare with modern technology and compassionate care.
                        </p>
                        <!-- Social Media -->
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-outline-light btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="btn btn-outline-light btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="btn btn-outline-light btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="btn btn-outline-light btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Links Column -->
                <div class="col-lg-2 col-md-6 mb-4 mb-lg-0">
                    <h6 class="text-white fw-bold mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="#home" class="text-white-50 text-decoration-none d-flex align-items-center" style="transition: all 0.3s;">
                                <i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>Home
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#about" class="text-white-50 text-decoration-none d-flex align-items-center" style="transition: all 0.3s;">
                                <i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>About Us
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#services" class="text-white-50 text-decoration-none d-flex align-items-center" style="transition: all 0.3s;">
                                <i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>Services
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#pricing" class="text-white-50 text-decoration-none d-flex align-items-center" style="transition: all 0.3s;">
                                <i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>Pricing
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#contact" class="text-white-50 text-decoration-none d-flex align-items-center" style="transition: all 0.3s;">
                                <i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>Contact
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Services Column -->
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <h6 class="text-white fw-bold mb-3">Our Services</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="#services" class="text-white-50 text-decoration-none d-flex align-items-center" style="transition: all 0.3s;">
                                <i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>General Consultation
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#services" class="text-white-50 text-decoration-none d-flex align-items-center" style="transition: all 0.3s;">
                                <i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>Specialist Care
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#services" class="text-white-50 text-decoration-none d-flex align-items-center" style="transition: all 0.3s;">
                                <i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>Health Checkups
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#services" class="text-white-50 text-decoration-none d-flex align-items-center" style="transition: all 0.3s;">
                                <i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>Emergency Care
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#services" class="text-white-50 text-decoration-none d-flex align-items-center" style="transition: all 0.3s;">
                                <i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>Medical Records
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Contact Column -->
                <div class="col-lg-3 col-md-6">
                    <h6 class="text-white fw-bold mb-3">Contact Info</h6>
                    <ul class="list-unstyled">
                        <li class="mb-3 d-flex align-items-start">
                            <i class="fas fa-map-marker-alt text-primary me-3 mt-1"></i>
                            <span class="text-white-50 small">123 Healthcare Street<br>Medical District<br>City, State 12345</span>
                        </li>
                        <li class="mb-3 d-flex align-items-start">
                            <i class="fas fa-phone text-success me-3 mt-1"></i>
                            <span class="text-white-50 small">(555) 123-CARE</span>
                        </li>
                        <li class="mb-3 d-flex align-items-start">
                            <i class="fas fa-envelope text-info me-3 mt-1"></i>
                            <span class="text-white-50 small">info@careaidclinic.com</span>
                        </li>
                        <li class="mb-3 d-flex align-items-start">
                            <i class="fas fa-clock text-warning me-3 mt-1"></i>
                            <span class="text-white-50 small">Mon-Fri: 8AM-8PM<br>Emergency: 24/7</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="border-top border-secondary border-opacity-25 py-4">
                <div class="row align-items-center">
                    <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                        <p class="text-white-50 mb-0 small">
                            &copy; 2024 <span class="text-white fw-semibold">CareAid Clinic</span>. All rights reserved.
                        </p>
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <ul class="list-inline mb-0">
                            <li class="list-inline-item">
                                <a href="#" class="text-white-50 text-decoration-none small">Privacy Policy</a>
                            </li>
                            <li class="list-inline-item">
                                <span class="text-white-50">|</span>
                            </li>
                            <li class="list-inline-item">
                                <a href="#" class="text-white-50 text-decoration-none small">Terms of Service</a>
                            </li>
                            <li class="list-inline-item">
                                <span class="text-white-50">|</span>
                            </li>
                            <li class="list-inline-item">
                                <a href="#" class="text-white-50 text-decoration-none small">Cookie Policy</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Back to Top Button -->
        <a href="#home" class="position-fixed bottom-0 end-0 m-4 btn btn-primary rounded-circle shadow-lg d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; z-index: 1000;" title="Back to Top">
            <i class="fas fa-arrow-up"></i>
        </a>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for navigation links (fallback for older browsers)
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

        // Add active class to navbar items on scroll
        window.addEventListener('scroll', function() {
            let current = '';
            const sections = document.querySelectorAll('section[id]');
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (scrollY >= (sectionTop - 200)) {
                    current = section.getAttribute('id');
                }
            });

            document.querySelectorAll('.navbar-nav a[href^="#"]').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>

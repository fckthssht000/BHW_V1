<?php
require_once 'db_connect.php';

// Fetch dashboard card data
$query = "
    SELECT 
        COUNT(DISTINCT p.household_number) AS total_household,
        COUNT(DISTINCT p.person_id) AS total_population,
        COUNT(DISTINCT CASE WHEN p.gender = 'M' THEN p.person_id END) AS total_male,
        COUNT(DISTINCT CASE WHEN p.gender = 'F' THEN p.person_id END) AS total_female
    FROM person p
    JOIN address a ON p.address_id = a.address_id
    LEFT JOIN records r ON p.person_id = r.person_id
    LEFT JOIN users u ON r.user_id = u.user_id
    WHERE (p.deceased IS NULL OR p.deceased = 0)
    AND (u.role_id IS NULL OR u.role_id NOT IN (1, 2, 4));
";
$stmt = $pdo->prepare($query);
$stmt->execute();
$total_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Ensure integer values and handle nulls
$total_data = [
    'total_household' => (int)($total_data['total_household'] ?? 0),
    'total_population' => (int)($total_data['total_population'] ?? 0),
    'total_male' => (int)($total_data['total_male'] ?? 0),
    'total_female' => (int)($total_data['total_female'] ?? 0)
];
?>



<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>BRGYCare Sta. Maria, Camiling, Tarlac</title>

    <link rel="icon" type="image/png" href="assets/images/favicon.ico">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>

        body {

            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;

            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 50%, #f7fafc 100%);

            color: #1a202c;

            margin: 0;

            padding: 0;

            min-height: 100vh;

            display: flex;

            flex-direction: column;

        }

        .navbar {

            background: rgba(43, 108, 176, 0.9);

            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);

           padding: 2px 15px !important;

            position: fixed;

            top: 0;

            width: 100%;

            z-index: 1000;

            backdrop-filter: blur(10px);

            -webkit-backdrop-filter: blur(10px);

            padding-top: 4px !important;
            padding-bottom: 4px !important;

        }
        .navbar, .navbar * {
            line-height: 1.2 !important;
        }
        .navbar .nav-link,
        .navbar .dropdown-toggle,
        .search-icon {
            padding-top: 4px !important;
            padding-bottom: 4px !important;
            line-height: 1.2 !important;
        }

        .navbar-brand img {

            max-height: 65px;

            margin-right: 10px;

        }

        .navbar .navbar-brand {

            color: #fff;

            font-weight: 600;

            font-size: 1.5rem;

            display: flex;

            align-items: center;

        }

        .navbar .nav-link, .navbar .btn {

            color: #fff;

            margin-left: 15px;

            font-weight: 500;

            text-decoration: none;

        }

        .navbar .btn {

            background: rgba(255, 255, 255, 0.1);

            border: 1px solid #fff;

            padding: 8px 20px;

            border-radius: 5px;

            transition: background 0.3s, color 0.3s;

        }

        .navbar .btn:hover {

            background: rgba(255, 255, 255, 0.2);

            color: #e2e8f0;

        }

        .navbar-nav .dropdown-menu {

            background: rgba(43, 108, 176, 0.9);

            position: absolute;

            right: 0;

            left: auto;

        }

        .navbar-nav .dropdown-item {

            color: #fff;

        }

        .navbar-nav .dropdown-item:hover {

            background: rgba(255, 255, 255, 0.1);

            color: #e2e8f0;

        }

        .hero {

            height: 450px;

            background: url('sta_maria_hall.jpg') no-repeat center center;

            background-size: cover;

            position: relative;

            display: flex;

            align-items: center;

            justify-content: center;

            text-align: center;

            color: #fff;

        }

        .hero-overlay {

            background: rgba(0, 0, 0, 0.5);

            position: absolute;

            top: 0;

            left: 0;

            width: 100%;

            height: 100%;

            z-index: 1;

        }

        .hero-content {

            position: relative;

            z-index: 2;

            padding: 20px;

        }

        .hero-content h1 {

            font-size: 3rem;

            margin-bottom: 20px;

        }

        .hero-content p {

            font-size: 1.2rem;

            margin-bottom: 30px;

        }

        .hero .btn {

            background: #2b6cb0;

            border: none;

            padding: 12px 30px;

            font-size: 1.1rem;

            font-weight: 600;

        }

        .hero .btn:hover {

            background: #1e4a8a;

            color: #fff;

        }

        .top-two-panel {

            display: flex;

            margin: 0;

            background: rgba(255, 255, 255, 0.9);

            border-radius: 10px;

            overflow: hidden;

            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);

            padding: 25px 0 12px;

            margin-top: calc(60px + 10px);

        }

        .top-panel-left h1, .top-panel-right h2 {

            font-size: 30px;

            align-items: center;

            justify-content: center;

            text-align: center;

            padding-top: 10px;

            padding-bottom: 0;

        }

        .top-panel-left, .top-panel-right {

            flex: 1;

            display: flex;

            align-items: center;

            justify-content: center;

            text-align: center;

            min-width: 0; /* Allow shrinking */

            padding-left: 12px;

        }

        .top-panel-left img, .top-panel-right img {

            max-height: 50px;

            margin-right: 10px;

        }

        .description {

            padding: 40px 20px;

            text-align: center;

            background: rgba(255, 255, 255, 0.9);

            margin: 10px 0; /* Reduced margin to close the gap */

            border-radius: 10px;

            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);

            transition: transform 0.3s ease;

        }

        .description:hover {

            transform: translateY(-5px);

        }

        .stats-card {

            background: rgba(255, 255, 255, 0.95);

            border: 1px solid rgba(43, 108, 176, 0.2);

            border-radius: 15px;

            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);

            text-align: center;

            padding: 15px;

            margin: 5px;

            height: 120px;

            display: flex;

            flex-direction: column;

            justify-content: center;

            transition: transform 0.3s ease, box-shadow 0.3s ease;

        }

        .stats-card:hover {

            transform: translateY(-5px);

            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);

        }

        .stats-card h4 {

            margin: 0;

            font-size: 1rem;

            color: #2b6cb0;

        }

        .stats-card p {

            margin: 5px 0 0;

            font-size: 1.2rem;

            font-weight: 600;

            color: #1a202c;

        }

        .two-panel {

            display: flex;

            margin: 20px 0;

            background: rgba(255, 255, 255, 0.9);

            border-radius: 10px;

            overflow: hidden;

            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);

            position: relative;

        }

        .stats-panel {

            display: flex;

            flex-wrap: wrap;

            justify-content: space-around;

            padding: 15px;

            background: rgba(255, 255, 255, 0.9);

            border-radius: 10px 0 0 10px;

            flex: 1;

        }

        .panel-image {

            flex: 2;

            min-height: 300px;

            overflow: hidden;

            border-radius: 0 10px 10px 0;

            display: flex;

            align-items: center;

            justify-content: center;

            position: relative;

            z-index: 1;

        }

        .panel-image img {

            width: 100%;

            height: auto;

            object-fit: cover;

        }

        .panel-description {

            flex: 1;

            padding: 20px;

            background: #fff;

            border-radius: 0 0 10px 0;

        }

        .features {

            padding: 40px 20px;

            background: rgba(255, 255, 255, 0.9);

            margin: 20px 0;

            border-radius: 10px;

            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);

            text-align: center;

        }

        .feature-card {
            background: #fff;
            border: 1px solid rgba(43, 108, 176, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: height 0.3s ease, transform 0.3s ease;
            overflow: visible;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }
        .feature-card .show-more-btn {
            background: none;
            border: none;
            color: #2b6cb0;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 5px 0;
            text-decoration: underline;
        }
        .feature-card .show-more-btn:hover {
            color: #1e4a8a;
        }
        .feature-card .description {
            margin-top: 10px;
            font-size: 0.9rem;
            line-height: 1.4;
            word-wrap: break-word;
        }
        .feature-card.expanded {
            height: auto;
        }
        .col-md-4.expanded, .col-sm-12.expanded {
            height: auto;
            flex: none;
        }

        .testimonials {

            padding: 40px 20px;

            background: rgba(255, 255, 255, 0.9);

            margin: 20px 0;

            border-radius: 10px;

            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);

            text-align: center;

        }

        .testimonial-card {

            background: #fff;

            border: 1px solid rgba(43, 108, 176, 0.2);

            border-radius: 10px;

            padding: 20px;

            margin: 15px 0;

            height: 200px;

            display: flex;

            flex-direction: column;

            justify-content: center;

            transition: transform 0.3s ease;

        }

        .testimonial-card:hover {
            transform: translateY(-5px);
        }
        .testimonial-card .show-more-btn {
            background: none;
            border: none;
            color: #2b6cb0;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 5px 0;
            text-decoration: underline;
        }
        .testimonial-card .show-more-btn:hover {
            color: #1e4a8a;
        }
        .testimonial-card .description {
            margin-top: 10px;
        }

        .newsletter {

            padding: 40px 20px;

            background: rgba(43, 108, 176, 0.9);

            color: #fff;

            margin: 20px 0;

            border-radius: 10px;

            text-align: center;

        }

        .editable-description {

            padding: 40px 20px;

            text-align: center;

            background: rgba(255, 255, 255, 0.9);

            margin: 20px 0;

            border-radius: 10px;

            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);

            transition: transform 0.3s ease;

        }

        .editable-description:hover {

            transform: translateY(-5px);

        }

        .logo {

            background-color: #f7fafc;

            border-radius: 50px;

        }

        footer {

            background: #2b6cb0e6;

            color: #fff;

            padding: 20px 0;

            margin-top: auto;

            text-align: center;

        }

        footer .container {

            display: flex;

            flex-wrap: wrap;

            justify-content: center;

            gap: 15px;

            max-width: 1200px;

            margin: 0 auto;

        }

        footer .logo-container {

            display: flex;

            justify-content: center;

            gap: 15px;

            margin-bottom: 10px;

        }

        footer .logo-container img {

            max-height: 80px;

            border-radius: 50px;

        }

        footer .contacts, footer .sections {

            flex: 1;

            min-width: 200px;

            text-align: left;

            padding: 0 10px;

        }

        footer .end {

            flex: 100%;

            text-align: center;

            padding: 10px 0;

        }

        footer ul {

            list-style: none;

            padding: 0;

            margin: 0;

        }

        footer p, footer a {

            margin: 5px 0;

        }

        footer a {

            color: #e2e8f0;

            text-decoration: none;

        }

        footer a:hover {

            color: #fff;

        }

        .back-to-top {

            position: fixed;

            bottom: 20px;

            right: 20px;

            background: #2b6cb0;

            color: #fff;

            border: none;

            border-radius: 50%;

            width: 50px;

            height: 50px;

            font-size: 1.2rem;

            display: flex;

            align-items: center;

            justify-content: center;

            cursor: pointer;

            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);

            opacity: 0;

            transition: opacity 0.3s ease;

            z-index: 1000;

        }

        .back-to-top.show {

            opacity: 1;

        }

        .back-to-top:hover {

            background: #1e4a8a;

        }

        @media (max-width: 768px) {
            .row>* {
                flex-shrink: 0;
                width: 50%;
                max-width: 100%;
                margin-top: var(--bs-gutter-y);
            }
            .navbar-nav .btn {
                display: none;
            }
            .navbar-nav .dropdown {
                display: block;
            }
            .navbar-nav .dropdown-menu {
                position: absolute;
                right: 0;
                left: auto;
                width: 150px;
            }
            .top-two-panel {
                flex-direction: row;
                flex-wrap: wrap;
                padding: 18px 0 0;
            }
            .top-panel-left, .top-panel-right {
                flex: 1 1 40%;
                min-width: 200px;
                height: auto;
                margin-bottom: 5px;
                align-items: left;
                justify-content: left;
                text-align: left;
            }
            .top-panel-left h1, .top-panel-right h2 {
                font-size: 20px;
                align-items: center;
                justify-content: center;
                text-align: center;
            }
            .top-panel-left img, .top-panel-right img {
                max-height: 40px;
            }
            .hero {
                height: 300px;
            }
            .hero-content h1 {
                font-size: 2rem;
            }
            .hero-content p {
                font-size: 1rem;
            }
            .two-panel {
                flex-direction: column;
            }
            .stats-panel {
                order: 3;
                border-radius: 10px 10px 0 0;
            }
            .panel-description {
                order: 1;
                border-radius: 10px 10px 0 0;
            }
            .panel-image {
                order: 2;
                border-radius: 0 0 10px 10px;
            }
            .panel-image, .panel-description {
                width: 100%;
            }
            .stats-card {
                height: 100px;
                padding: 10px;
            }
            .stats-card h4 {
                font-size: 0.9rem;
            }
            .stats-card p {
                font-size: 1rem;
            }
            .features .feature-card {
                height: auto;
                min-height: 180px;
                padding: 15px;
                margin: 10px 0;
            }
            .testimonials .testimonial-card {
                height: auto;
                min-height: 180px;
                padding: 15px;
                margin: 10px 0;
            }
            .back-to-top {
                bottom: 15px;
                right: 15px;
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            footer {
                padding: 10px;
            }
            footer .container {
                flex-direction: column;
                align-items: center;
                padding: 5px;
                gap: 1px;
            }
            footer .logo-container {
                margin-bottom: 15px;
            }
            footer .contacts, footer .sections, footer .end {
                text-align: center;
                padding: 5px 0;
                width: 100%;
            }
        }

        @media (min-width: 769px) {

            .navbar-nav .btn {

                display: inline-block;

            }

            .navbar-nav .dropdown {

                display: none;

            }

            .content-wrapper {

                margin-top: 60px; /* Adjusted to reduce gap */

            }

            .features .feature-card,

            .testimonials .testimonial-card {

                height: 200px;

            }

        }

    </style>

</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark">

        <div class="container">

            <a class="navbar-brand" href="#">

                <img class="logo" src="logo.png">

                BRGYCare

            </a>

            <div class="navbar-nav ms-auto">

                <a href="login.php" class="btn">Login</a>

                <a href="register.php" class="btn">Register</a>

                <div class="dropdown d-lg-none">

                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">

                        <span style="font-size: 1.5rem;"><i class="far fa-user"></i></span>

                    </a>

                    <ul class="dropdown-menu" aria-labelledby="navbarDropdown">

                        <li><a class="dropdown-item" href="login.php">Login</a></li>

                        <li><a class="dropdown-item" href="register.php">Register</a></li>

                    </ul>

                </div>

            </div>

        </div>

    </nav>

    <div class="top-two-panel">

        <div class="top-panel-left">

            <img src="sta.maria.png">

            <h1>Barangay Sta. Maria</h1>

        </div>

        <div class="top-panel-right">

            <img src="camiling.jpg">

            <h2>Camiling, Tarlac</h2>

        </div>

    </div>

    <div class="hero">

        <div class="hero-overlay"></div>

        <div class="hero-content">

            <h1>Welcome to BRGYCare</h1>

            <p>Transforming community health with innovative solutions and real-time insights.</p>

            <a href="#home" class="btn">Get Started</a>

        </div>

    </div>

    <div class="container content-wrapper" id="home">

        <div class="description">

            <h2>About BRGYCare</h2>

            <p>At BRGYCare, we're passionate about making health management easier and more effective for communities like Sta. Maria in Camiling, Tarlac. Our platform brings together real-time data, insights, and simple tools to help health workers and residents work together for better health outcomes.</p>

            <p>We believe in innovation that matters—turning everyday health records into information that drives positive change, ensuring everyone in our community gets the care they deserve.</p>

        </div>

        <div class="two-panel" id="about">

            <div class="stats-panel">

                <div class="row">

                    <div class="col-md-6 col-sm-6">

                        <div class="stats-card">

                            <h4>Total Registered Households</h4>

                            <p><?php echo htmlspecialchars((int)$total_data['total_household']); ?></p>

                        </div>

                    </div>

                    <div class="col-md-6 col-sm-6">

                        <div class="stats-card">

                            <h4>Total Residents</h4>

                            <p><?php echo htmlspecialchars((int)$total_data['total_population']); ?></p>

                        </div>

                    </div>

                    <div class="col-md-6 col-sm-6">

                        <div class="stats-card">

                            <h4>Total Male</h4>

                            <p><?php echo htmlspecialchars((int)$total_data['total_male']); ?></p>

                        </div>

                    </div>

                    <div class="col-md-6 col-sm-6">

                        <div class="stats-card">

                            <h4>Total Female</h4>

                            <p><?php echo htmlspecialchars((int)$total_data['total_female']); ?></p>

                        </div>

                    </div>

                </div>

            </div>

            <div class="panel-image">

                <img src="map.jpg">

            </div>

            <div class="panel-description">

                <h3>About Sta. Maria, Camiling, Tarlac</h3>

                <p>Santa Maria is a barangay in the municipality of Camiling, in the province of Tarlac. Its population as determined by the 2020 Census was 2,466. This represented 2.82% of the total population of Camiling.</p>

            </div>

        </div>

        <div class="features">

            <h2>Our Features</h2>

            <div class="row">

                <div class="col-md-4 col-sm-12">
                    <div class="feature-card">
                        <h4>Manage Household</h4>
                        <button class="show-more-btn">Show More</button>
                        <p class="description" style="display: none;">Track households, residents, and demographics for effective community management.</p>
                    </div>
                </div>

                <div class="col-md-4 col-sm-12">
                    <div class="feature-card">
                        <h4>Family Planning</h4>
                        <button class="show-more-btn">Show More</button>
                        <p class="description" style="display: none;">Manage family planning programs and compliance tracking.</p>
                    </div>
                </div>

                <div class="col-md-4 col-sm-12">
                    <div class="feature-card">
                        <h4>Child Nutrition</h4>
                        <button class="show-more-btn">Show More</button>
                        <p class="description" style="display: none;">Monitor child growth and nutrition using WHO standards.</p>
                    </div>
                </div>

                <div class="col-md-4 col-sm-12">
                    <div class="feature-card">
                        <h4>Maternal Care</h4>
                        <button class="show-more-btn">Show More</button>
                        <p class="description" style="display: none;">Support prenatal and postnatal care for mothers and infants.</p>
                    </div>
                </div>

                <div class="col-md-4 col-sm-12">
                    <div class="feature-card">
                        <h4>Health Analytics</h4>
                        <button class="show-more-btn">Show More</button>
                        <p class="description" style="display: none;">Analyze health trends with visual dashboards.</p>
                    </div>
                </div>

                <div class="col-md-4 col-sm-12">
                    <div class="feature-card">
                        <h4>Health Mapping</h4>
                        <button class="show-more-btn">Show More</button>
                        <p class="description" style="display: none;">Visualize health data on interactive maps.</p>
                    </div>
                </div>

                <div class="col-md-4 col-sm-12">
                    <div class="feature-card">
                        <h4>Manage User</h4>
                        <button class="show-more-btn">Show More</button>
                        <p class="description" style="display: none;">Role-based secure access for admins, workers, and residents.</p>
                    </div>
                </div>

                <div class="col-md-4 col-sm-12">
                    <div class="feature-card">
                        <h4>Environment Health Factors</h4>
                        <button class="show-more-btn">Show More</button>
                        <p class="description" style="display: none;">Track sanitation and environmental health factors.</p>
                    </div>
                </div>

            </div>

        </div>

        <div class="testimonials">

            <h2>What People Say</h2>

            <div class="row">

                <div class="col-md-6 col-sm-12">

                    <div class="testimonial-card">

                        <p>"BRGYCare has transformed how we manage health in our community!"</p>

                        <h5>- Anonymous 5689</h5>

                    </div>

                </div>

                <div class="col-md-6 col-sm-12">

                    <div class="testimonial-card">

                        <p>"The insights are invaluable for our healthcare planning."</p>

                        <h5>- Anonymous 1892</h5>

                    </div>

                </div>

            </div>

        </div>

        <div class="editable-description">
            <h2>Key Benefits of BRGYCare</h2>
            <p class="lead">Discover how BRGYCare enhances community health management with innovative tools and data-driven insights.</p>
            <div class="row">
                <div class="col-md-6">
                    <ul class="text-start">
                        <li><i class="fas fa-chart-line text-primary me-2"></i><strong>Data-Driven Decisions:</strong> Access real-time analytics to identify health trends and allocate resources effectively.</li>
                        <li><i class="fas fa-users text-primary me-2"></i><strong>Community Engagement:</strong> Empower residents with easy access to health records and personalized notifications.</li>
                        <li><i class="fas fa-shield-alt text-primary me-2"></i><strong>Enhanced Security:</strong> Built with MySQL encryption and HTTPS for complete data protection.</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="text-start">
                        <li><i class="fas fa-heartbeat text-primary me-2"></i><strong>Improved Health Outcomes:</strong> Proactive monitoring reduces risks and supports timely interventions.</li>
                        <li><i class="fas fa-clock text-primary me-2"></i><strong>Efficiency Gains:</strong> Automate reporting and streamline workflows for health workers.</li>
                        <li><i class="fas fa-globe text-primary me-2"></i><strong>Scalable Solution:</strong> Designed for barangays like Sta. Maria, with potential for broader municipal use.</li>
                    </ul>
                </div>
            </div>
            <hr>
            <h5>System Overview</h5>
            <p>BRGYCare runs on PHP and MySQL, ensuring reliability and compliance with local data privacy laws. No special hardware required—works on standard devices.</p>
            <p><em>Upcoming:</em> Mobile app integration and AI predictions for even smarter health insights.</p>
        </div>

        <div class="newsletter" id="contact">

            <h2>Stay Updated</h2>

            <p>Subscribe to our newsletter for the latest health updates.</p>

            <form>

                <input type="email" placeholder="Enter your email" class="form-control mb-2" required>

                <button type="submit" class="btn btn-light">Subscribe</button>

            </form>

        </div>

    </div>

    <footer>

        <div class="container">

            <div class="logo-container">

                <img src="sta.maria.png" alt="Logo 1">

                <img src="camiling.jpg" alt="Logo 2">

            </div>

            <div class="contacts">

                <ul>

                    <li><strong>Contact</strong></li>

                    <li><a href="https://mail.google.com/mail/?view=cm&fs=1&to=support@brgycare.com" target="_blank" rel="noopener noreferrer">support@brgycare.com</a></li>

                    <li>0930-162-8108</li>
                    <li>0927-529-3992</li>

                    <li><strong>Location:</strong> Sta. Maria, Camiling, Tarlac</li>

                </ul>

            </div>

            <div class="sections">

                <ul>

                    <li><strong>Sections</strong></li>

                    <li><a href="#home">Home</a></li>

                    <li><a href="#about">About</a></li>

                    <li><a href="#contact">Contact</a></li>

                </ul>

            </div>

            <div class="end">

                <p>&copy; 2025 BRGYCare. All rights reserved.</p>

            </div>

        </div>

    </footer>

    <button class="back-to-top" onclick="scrollToTop()">↑</button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>

        // Show/hide back-to-top button based on scroll position

        window.addEventListener('scroll', function() {

            const backToTop = document.querySelector('.back-to-top');

            if (window.scrollY > 300) {

                backToTop.classList.add('show');

            } else {

                backToTop.classList.remove('show');

            }

        });



        // Smooth scroll to top

        function scrollToTop() {

            window.scrollTo({ top: 0, behavior: 'smooth' });

        }

        // Show more functionality for feature cards

        document.addEventListener('DOMContentLoaded', function() {

            const showMoreBtns = document.querySelectorAll('.show-more-btn');

            showMoreBtns.forEach(btn => {

                btn.addEventListener('click', function() {

                    const card = this.parentElement;

                    const col = card.parentElement;

                    const description = card.querySelector('.description');

                    if (description.style.display === 'none' || description.style.display === '') {

                        description.style.display = 'block';

                        this.textContent = 'Show Less';

                        card.classList.add('expanded');

                        col.classList.add('expanded');

                    } else {

                        description.style.display = 'none';

                        this.textContent = 'Show More';

                        card.classList.remove('expanded');

                        col.classList.remove('expanded');

                    }

                });

            });

        });

    </script>

</body>

</html>
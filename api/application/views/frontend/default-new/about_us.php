<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My-Communication Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'custom-blue': '#009ee5',
                        'custom-gold': '#f3cc5e',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        #my-communication-academy {
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        #my-communication-academy .card-shadow-hover {
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        #my-communication-academy .card-shadow-hover:hover {
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.25), 0 10px 10px rgba(0, 0, 0, 0.22);
            transform: translateY(-8px);
        }

        .animate-fade-in-up {
            animation: fadeInUp 1s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Custom image sizing rules */
        #my-communication-academy .academy-logo {
            height: auto;
            width: 100%;
            max-width: 300px;
        }

        #my-communication-academy .leader-photo-container {
            width: 192px;
            height: 192px;
            flex-shrink: 0;
        }

        #my-communication-academy .highlight-img {
            aspect-ratio: 4 / 3;
        }

        /* Enhanced Timeline */
        #my-communication-academy .timeline-item {
            position: relative;
            transition: all 0.3s ease;
        }

        #my-communication-academy .timeline-item::before {
            content: '';
            position: absolute;
            top: 1.5rem;
            left: -0.8rem;
            width: 1.5rem;
            height: 1.5rem;
            background: linear-gradient(45deg, #009ee5, #0077b6);
            border-radius: 50%;
            border: 4px solid #f3cc5e;
            transform: translateX(-50%);
            z-index: 10;
            box-shadow: 0 4px 12px rgba(0, 158, 229, 0.3);
            transition: all 0.3s ease;
        }

        #my-communication-academy .timeline-item:hover::before {
            transform: translateX(-50%) scale(1.2);
            box-shadow: 0 8px 20px rgba(0, 158, 229, 0.4);
        }

        #my-communication-academy .timeline-item:nth-child(even)::before {
            left: calc(100% + 0.8rem);
        }

        #my-communication-academy .timeline-container {
            position: relative;
        }

        #my-communication-academy .timeline-container::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: #009ee5;
            transform: translateX(-50%);
            z-index: 5;
        }

        /* Mobile-specific improvements */
        @media (max-width: 768px) {
            #my-communication-academy .academy-logo {
                max-width: 220px;
            }

            #my-communication-academy .leader-photo-container {
                width: 120px;
                height: 120px;
            }

            /* Enhanced mobile cards */
            #my-communication-academy .card-shadow-hover {
                padding: 1.25rem !important;
                margin-bottom: 1rem;
            }

            #my-communication-academy .card-shadow-hover:hover {
                transform: translateY(-4px);
                box-shadow: 0 10px 20px rgba(0, 158, 229, 0.15);
            }

            /* Mobile timeline - vertical left-aligned */
            #my-communication-academy .timeline-container {
                padding-left: 2rem;
            }

            #my-communication-academy .timeline-container::after {
                left: 1rem;
                width: 3px;
                transform: none;
            }

            #my-communication-academy .timeline-item {
                width: 100% !important;
                margin-left: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            #my-communication-academy .timeline-item::before {
                left: 1rem !important;
                width: 1.2rem;
                height: 1.2rem;
                transform: translateX(-50%);
                border: 3px solid #f3cc5e;
            }

            #my-communication-academy .timeline-item .timeline-content {
                margin-left: 0;
                width: 100%;
            }

            /* Responsive text sizes */
            h1 {
                font-size: 1.75rem !important;
            }

            h2 {
                font-size: 1.5rem !important;
            }

            h3 {
                font-size: 1.125rem !important;
            }

            /* Touch-friendly spacing */
            section {
                padding-top: 2.5rem !important;
                padding-bottom: 2.5rem !important;
            }
        }

        /* Extra small screens */
        @media (max-width: 480px) {
            #my-communication-academy .academy-logo {
                max-width: 180px;
            }

            /* Compact cards */
            #my-communication-academy .card-shadow-hover {
                padding: 1rem !important;
                border-radius: 12px;
            }

            /* Smaller leader photo */
            #my-communication-academy .leader-photo-container {
                width: 100px;
                height: 100px;
            }

            /* Ultra-compact timeline */
            #my-communication-academy .timeline-container {
                padding-left: 1.5rem;
            }

            #my-communication-academy .timeline-container::after {
                left: 0.75rem;
                width: 2px;
            }

            #my-communication-academy .timeline-item::before {
                left: 0.75rem !important;
                width: 1rem;
                height: 1rem;
                border: 2px solid #f3cc5e;
            }

            /* Smaller text */
            h1 {
                font-size: 1.5rem !important;
            }

            h2 {
                font-size: 1.25rem !important;
            }
        }

        /* Landscape mobile optimization */
        @media (max-width: 768px) and (orientation: landscape) {
            section {
                padding-top: 2rem !important;
                padding-bottom: 2rem !important;
            }
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-800">
    <div id="my-communication-academy">
        <main>
            <!-- Logo and Name Section -->
            <section class="py-6 md:py-8 bg-white text-center shadow-sm">
                <div class="container mx-auto px-4 md:px-6">
                    <img src="https://my-communication.uk/uploads/system/f9bd58e0ae7969a7c78e417fcbfeff51.png"
                        alt="My-Communication Academy Logo" class="mx-auto mb-4 academy-logo animate-fade-in-up"
                        width="455" height="128" loading="lazy">
                    <h1 class="text-2xl md:text-4xl font-bold text-gray-900 animate-fade-in-up">My-Communication Academy
                    </h1>
                </div>
            </section>

            <!-- About Us Section -->
            <section id="about"
                class="py-12 md:py-16 lg:py-20 bg-custom-blue text-white animate-fade-in-up relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-custom-blue to-blue-800 opacity-90 z-0"></div>
                <div class="container mx-auto px-4 md:px-6 text-center relative z-10">
                    <h2 class="text-2xl md:text-3xl lg:text-4xl font-extrabold mb-4 md:mb-6 leading-tight">About Us:
                        Passion For Telecom Excellence</h2>
                    <p class="text-sm md:text-base lg:text-lg max-w-4xl mx-auto opacity-95 leading-relaxed">
                        MY-COMMUNICATION Academy is a pioneering institution in the Middle East that trains
                        communications engineering students and professionals globally to the highest standards of
                        quality and excellence. The academy offers a variety of high-level educational and application
                        courses in communications engineering, information technology, networks, and cloud computing.
                        The curriculum is designed to simplify complex information and terminology, providing a smooth
                        learning path from foundational knowledge to professional expertise. The academy was started as
                        a modest venture with a single course and has since grown to train over 5,000 students
                        worldwide.
                    </p>
                </div>
            </section>

            <!-- Vision, Mission & Goal Section -->
            <section id="vision-mission-goal" class="py-12 md:py-16 bg-gray-100 animate-fade-in-up">
                <div class="container mx-auto px-4 md:px-6">
                    <h2 class="text-2xl md:text-3xl font-bold text-center mb-8 md:mb-12 animate-fade-in-up">Vision,
                        Mission & Goal</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 max-w-6xl mx-auto">
                        <div
                            class="bg-white p-5 md:p-7 rounded-xl border border-gray-200 card-shadow-hover animate-fade-in-up">
                            <h3 class="text-lg md:text-xl font-semibold text-custom-blue mb-3">Vision</h3>
                            <p class="text-gray-600 text-sm md:text-base leading-relaxed">
                                To become a leading global provider of online courses for telecom engineers, offering
                                high-quality, up-to-date educational resources accessible from anywhere in the world.
                            </p>
                        </div>
                        <div
                            class="bg-white p-5 md:p-7 rounded-xl border border-gray-200 card-shadow-hover animate-fade-in-up">
                            <h3 class="text-lg md:text-xl font-semibold text-custom-blue mb-3">Mission</h3>
                            <p class="text-gray-600 text-sm md:text-base leading-relaxed">
                                To deliver world-class online education to telecom engineers through cutting-edge
                                courses, expert instructors, and immersive learning experiences. The academy aims to
                                equip learners with the latest industry insights and practical skills needed to excel in
                                the evolving telecommunications field.
                            </p>
                        </div>
                        <div
                            class="bg-white p-5 md:p-7 rounded-xl border border-gray-200 card-shadow-hover animate-fade-in-up">
                            <h3 class="text-lg md:text-xl font-semibold text-custom-blue mb-3">Goal</h3>
                            <p class="text-gray-600 text-sm md:text-base leading-relaxed">
                                To empower Arab telecom engineers with the knowledge and skills required to excel
                                professionally and contribute to the growth of the telecommunications industry in the
                                Arab world.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Our Core Values Section -->
            <section id="core-values" class="py-12 md:py-16 bg-white animate-fade-in-up">
                <div class="container mx-auto px-4 md:px-6">
                    <h2 class="text-2xl md:text-3xl font-bold text-center mb-8 md:mb-12 animate-fade-in-up">Our Core
                        Values</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-5 max-w-5xl mx-auto">
                        <div
                            class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover animate-fade-in-up">
                            <h3 class="text-base md:text-lg font-semibold text-custom-blue mb-2">Excellence</h3>
                            <p class="text-gray-600 text-sm md:text-base">Pursuing the highest standards in all
                                educational offerings.</p>
                        </div>
                        <div
                            class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover animate-fade-in-up">
                            <h3 class="text-base md:text-lg font-semibold text-custom-blue mb-2">Integrity</h3>
                            <p class="text-gray-600 text-sm md:text-base">Operating with honesty, transparency, and
                                ethical behavior.</p>
                        </div>
                        <div
                            class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover animate-fade-in-up">
                            <h3 class="text-base md:text-lg font-semibold text-custom-blue mb-2">Innovation</h3>
                            <p class="text-gray-600 text-sm md:text-base">Embracing creativity and technology to advance
                                learning experiences.</p>
                        </div>
                        <div
                            class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover animate-fade-in-up">
                            <h3 class="text-base md:text-lg font-semibold text-custom-blue mb-2">Empowerment</h3>
                            <p class="text-gray-600 text-sm md:text-base">Enabling learners to enhance their
                                professional growth.</p>
                        </div>
                        <div
                            class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover animate-fade-in-up">
                            <h3 class="text-base md:text-lg font-semibold text-custom-blue mb-2">Collaboration</h3>
                            <p class="text-gray-600 text-sm md:text-base">Promoting teamwork and open communication.</p>
                        </div>
                        <div
                            class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover animate-fade-in-up">
                            <h3 class="text-base md:text-lg font-semibold text-custom-blue mb-2">Diversity and Inclusion
                            </h3>
                            <p class="text-gray-600 text-sm md:text-base">Valuing diverse perspectives to build an
                                inclusive community.</p>
                        </div>
                        <div
                            class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover animate-fade-in-up">
                            <h3 class="text-base md:text-lg font-semibold text-custom-blue mb-2">Learner-Centric
                                Approach</h3>
                            <p class="text-gray-600 text-sm md:text-base">Prioritizing the needs and success of
                                learners.</p>
                        </div>
                        <div
                            class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover animate-fade-in-up">
                            <h3 class="text-base md:text-lg font-semibold text-custom-blue mb-2">Global Impact</h3>
                            <p class="text-gray-600 text-sm md:text-base">Contributing to the development of the global
                                telecommunications industry.</p>
                        </div>
                        <div
                            class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover animate-fade-in-up">
                            <h3 class="text-base md:text-lg font-semibold text-custom-blue mb-2">Lifelong Learning</h3>
                            <p class="text-gray-600 text-sm md:text-base">Encouraging continuous learning and
                                adaptability.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Company History & Milestones -->
            <section id="history" class="py-12 md:py-16 bg-gray-100 animate-fade-in-up">
                <div class="container mx-auto px-4 md:px-6">
                    <h2 class="text-2xl md:text-3xl font-bold text-center mb-8 md:mb-12 animate-fade-in-up">Company
                        History & Milestones</h2>
                    <div class="relative max-w-4xl mx-auto timeline-container">
                        <!-- 2021 -->
                        <div
                            class="relative mb-6 md:mb-8 md:flex md:justify-between md:items-center w-full md:w-1/2 md:pr-12 timeline-item animate-fade-in-up">
                            <div
                                class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover w-full timeline-content">
                                <h3 class="text-base md:text-lg font-semibold text-custom-gold">2021</h3>
                                <p class="text-gray-600 mt-2 text-sm md:text-base">Established the academy, launched the
                                    first course, and graduated the first group of engineers.</p>
                            </div>
                        </div>

                        <!-- 2022 -->
                        <div
                            class="relative mb-6 md:mb-8 md:flex md:justify-between md:items-center w-full md:w-1/2 md:ml-auto md:pl-12 timeline-item animate-fade-in-up">
                            <div
                                class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover w-full timeline-content">
                                <h3 class="text-base md:text-lg font-semibold text-custom-gold">2022</h3>
                                <p class="text-gray-600 mt-2 text-sm md:text-base">Opened new branches and organized the
                                    first communications conference of its kind in the region.</p>
                            </div>
                        </div>

                        <!-- 2023 -->
                        <div
                            class="relative mb-6 md:mb-8 md:flex md:justify-between md:items-center w-full md:w-1/2 md:pr-12 timeline-item animate-fade-in-up">
                            <div
                                class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover w-full timeline-content">
                                <h3 class="text-base md:text-lg font-semibold text-custom-gold">2023</h3>
                                <p class="text-gray-600 mt-2 text-sm md:text-base">Continued its achievements and began
                                    implementing the goal of moving to a global level.</p>
                            </div>
                        </div>

                        <!-- 2024 -->
                        <div
                            class="relative mb-6 md:mb-8 md:flex md:justify-between md:items-center w-full md:w-1/2 md:ml-auto md:pl-12 timeline-item animate-fade-in-up">
                            <div
                                class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover w-full timeline-content">
                                <h3 class="text-base md:text-lg font-semibold text-custom-gold">2024</h3>
                                <p class="text-gray-600 mt-2 text-sm md:text-base">Formed partnerships with leading
                                    academic institutions and companies in IT and communications, and diversified course
                                    offerings.</p>
                            </div>
                        </div>

                        <!-- 2025 -->
                        <div
                            class="relative mb-6 md:mb-8 md:flex md:justify-between md:items-center w-full md:w-1/2 md:pr-12 timeline-item animate-fade-in-up">
                            <div
                                class="bg-white p-4 md:p-6 rounded-xl shadow-md border border-gray-200 card-shadow-hover w-full timeline-content">
                                <h3 class="text-base md:text-lg font-semibold text-custom-gold">2025</h3>
                                <p class="text-gray-600 mt-2 text-sm md:text-base">Actively pursued global partnerships
                                    and developed English-language courses, expanding offerings to include data
                                    analytics and cybersecurity.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Key Achievements & Contributions -->
            <section id="achievements" class="py-12 md:py-16 bg-white animate-fade-in-up">
                <div class="container mx-auto px-4 md:px-6">
                    <h2 class="text-2xl md:text-3xl font-bold text-center mb-8 md:mb-12">Key Achievements &
                        Contributions</h2>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 max-w-6xl mx-auto">
                        <div
                            class="bg-custom-blue bg-opacity-5 p-5 md:p-7 rounded-xl border-l-4 border-custom-gold card-shadow-hover">
                            <h3 class="text-lg md:text-xl font-semibold text-custom-blue mb-3">Orange Digital Center
                                Partnership</h3>
                            <p class="text-gray-600 text-sm md:text-base">Collaborated with Orange Digital Center to
                                provide free training in Cloud Computing and Industrial Automation in Cairo from
                                February to March 2025.</p>
                        </div>
                        <div
                            class="bg-custom-blue bg-opacity-5 p-5 md:p-7 rounded-xl border-l-4 border-custom-gold card-shadow-hover">
                            <h3 class="text-lg md:text-xl font-semibold text-custom-blue mb-3">Orange Jordan Conference
                            </h3>
                            <p class="text-gray-600 text-sm md:text-base">Hosted a conference with Orange Jordan on the
                                impact of 5G technology, where Engineer Ibrahim Ibrahim discussed its transformative
                                effects on various industries. A partnership with Orange Jordan was also announced to
                                drive innovation.</p>
                        </div>
                        <div
                            class="bg-custom-blue bg-opacity-5 p-5 md:p-7 rounded-xl border-l-4 border-custom-gold card-shadow-hover">
                            <h3 class="text-lg md:text-xl font-semibold text-custom-blue mb-3">Syrian Telecom
                                Collaboration</h3>
                            <p class="text-gray-600 text-sm md:text-base">Developed a 12-week custom program for a
                                Syrian telecom company to train 150 engineers on 5G core networks, leading to 50% faster
                                deployment cycles and $4.8M in annual savings.</p>
                        </div>
                        <div
                            class="bg-custom-blue bg-opacity-5 p-5 md:p-7 rounded-xl border-l-4 border-custom-gold card-shadow-hover">
                            <h3 class="text-lg md:text-xl font-semibold text-custom-blue mb-3">Libya Exhibition</h3>
                            <p class="text-gray-600 text-sm md:text-base">Participated in the Libya International Forum
                                and Exhibition for Communications and Information Technology, a prominent annual event
                                in the field.</p>
                        </div>
                        <div
                            class="bg-custom-blue bg-opacity-5 p-5 md:p-7 rounded-xl border-l-4 border-custom-gold card-shadow-hover">
                            <h3 class="text-lg md:text-xl font-semibold text-custom-blue mb-3">MCTC Conference in Jordan
                            </h3>
                            <p class="text-gray-600 text-sm md:text-base">Played a key role in the inaugural MCTC
                                Conference in Jordan, bridging graduates with industry leaders and discussing
                                advancements in 5G technology.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Leadership Section -->
            <section id="leadership" class="py-12 md:py-16 bg-gray-100 animate-fade-in-up">
                <div class="container mx-auto px-4 md:px-6">
                    <h2 class="text-2xl md:text-3xl font-bold text-center mb-8 md:mb-12">Leadership</h2>
                    <div
                        class="flex flex-col md:flex-row items-center justify-center gap-6 md:gap-10 bg-white rounded-2xl shadow-xl p-6 md:p-8 border border-gray-200">
                        <div
                            class="relative rounded-full overflow-hidden shadow-lg border-4 border-custom-gold leader-photo-container">
                            <img src="https://my-communication.com/wp-content/uploads/2023/09/ibrahim-2.jpg"
                                alt="Eng. Ibrahim Ibrahim" class="absolute inset-0 w-full h-full object-cover">
                        </div>
                        <div class="max-w-xl text-center md:text-left">
                            <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">Founder & Chairman: Eng.
                                Ibrahim Kamel Ibrahim</h3>
                            <p class="text-base md:text-lg text-gray-600 font-medium mb-4">Certified expert in numerous
                                fields, including wireless networks, project management (PMP), risk management (RMP),
                                and Huawei-certified 5G networks.</p>
                            <h4 class="text-lg md:text-xl font-bold text-gray-900 mb-2">Board of Directors:</h4>
                            <ul class="text-gray-600 text-sm md:text-base list-disc list-inside space-y-1">
                                <li>ENG. Ibrahim Ibrahim (Chairman & Founder)</li>
                                <li>Alaa Ghassan Rajab (CSO)</li>
                                <li>Zainab Al Majid (Administrative Director)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Academy Highlights Section -->
            <section id="highlights" class="py-12 md:py-16 bg-white animate-fade-in-up">
                <div class="container mx-auto px-4 md:px-6">
                    <h2 class="text-2xl md:text-3xl font-bold text-center mb-8 md:mb-12">Academy Highlights</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 max-w-6xl mx-auto">
                        <img src="https://my-communication.com/wp-content/uploads/2023/09/DSC05038.jpg"
                            alt="Academy students working together"
                            class="w-full object-cover rounded-xl shadow-lg border border-gray-200 card-shadow-hover highlight-img">
                        <img src="https://my-communication.com/wp-content/uploads/2023/05/2023-03-18-14-55-261.jpg"
                            alt="Classroom session at the academy"
                            class="w-full object-cover rounded-xl shadow-lg border border-gray-200 card-shadow-hover highlight-img">
                    </div>
                </div>
            </section>

            <!-- Join Our Community Section -->
            <section id="join-community" class="py-12 md:py-20 bg-custom-gold text-gray-900 animate-fade-in-up">
                <div class="container mx-auto px-4 md:px-6 text-center">
                    <h2 class="text-2xl md:text-3xl font-bold mb-4">Join Our Global Community</h2>
                    <p class="text-base md:text-lg max-w-2xl mx-auto mb-6 opacity-90 leading-relaxed">
                        When you enroll at My-Communication Academy, you join a diverse and vibrant community of
                        learners from over 29 countries. We are proud to support students and professionals across the
                        MENA region and beyond, from Jordan and Iraq to the United States and Germany.
                    </p>
                    <p class="text-lg md:text-xl font-medium mb-6 md:mb-8">
                        We are here to support your journey and help you achieve your professional goals.
                    </p>
                    <a href="https://my-communication.com/en/home/courses"
                        class="inline-block bg-white text-custom-blue font-bold py-3 px-6 md:px-8 rounded-full shadow-lg hover:bg-gray-100 transform transition-transform duration-300 hover:scale-105 text-sm md:text-base">
                        Explore Our Courses
                    </a>
                </div>
            </section>
        </main>
    </div>
</body>

</html>
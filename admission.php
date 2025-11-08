<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admission Process - Unique Brilliant Schools">
    <title>Admission Process | Unique Brilliant Schools</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Poppins:wght@300;400;500;600;700&display=swap');
        
        .logo-font {
            font-family: 'Dancing Script', cursive;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <a href="index.php" class="text-xl logo-font text-orange-500 font-bold">
                    Unique Brilliant Schools
                </a>
                <a href="index.html" class="text-gray-600 hover:text-orange-500 transition duration-300">
                    ← Back to Home
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-16">
        <div class="max-w-2xl mx-auto text-center">
            <!-- Icon -->
            <div class="w-20 h-20 bg-orange-100 rounded-2xl flex items-center justify-center mx-auto mb-8">
                <i data-lucide="user-plus" class="w-10 h-10 text-orange-500"></i>
            </div>

            <!-- Heading -->
            <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-6">
                Start Your Child's <span class="text-orange-500">Journey</span>
            </h1>

            <!-- Description -->
            <p class="text-xl text-gray-600 mb-8 leading-relaxed">
                We're excited to welcome your child to our community of brilliant learners. 
                Let's begin the admission process together.
            </p>

            <!-- Process Steps -->
            <div class="bg-white rounded-2xl p-8 shadow-lg border border-gray-100 mb-12">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Simple Admission Process</h2>
                
                <div class="space-y-6 text-left">
                    <!-- Step 1 -->
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-8 h-8 bg-orange-500 text-white rounded-full flex items-center justify-center font-bold">
                            1
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800 mb-1">Contact Us via WhatsApp</h3>
                            <p class="text-gray-600">Send us a message to start the conversation</p>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-8 h-8 bg-orange-500 text-white rounded-full flex items-center justify-center font-bold">
                            2
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800 mb-1">Schedule a Visit</h3>
                            <p class="text-gray-600">Tour our campus and meet our team</p>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-8 h-8 bg-orange-500 text-white rounded-full flex items-center justify-center font-bold">
                            3
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800 mb-1">Complete Registration</h3>
                            <p class="text-gray-600">We'll guide you through the final steps</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WhatsApp CTA -->
            <div class="bg-green-50 rounded-2xl p-8 border border-green-200">
                <h3 class="text-2xl font-bold text-gray-800 mb-4">Ready to Get Started?</h3>
                <p class="text-gray-600 mb-6">
                    Contact us directly on WhatsApp for immediate assistance with admissions
                </p>
                <a href="https://wa.me/2348052477420?text=Hello! I'm interested in admission for my child at Unique Brilliant Schools. Can you please provide more information?" 
                   class="inline-flex items-center bg-green-500 hover:bg-green-600 text-white px-8 py-4 rounded-lg font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg text-lg">
                    <i data-lucide="message-circle" class="w-6 h-6 mr-3"></i>
                    Start Admission on WhatsApp
                </a>
                <p class="text-sm text-gray-500 mt-4">
                    +234 805 247 7420
                </p>
            </div>

            <!-- Alternative Contact -->
            <div class="mt-8 text-center">
                <p class="text-gray-600 mb-4">Prefer to call instead?</p>
                <a href="tel:+2348052477420" 
                   class="inline-flex items-center border-2 border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white px-6 py-3 rounded-lg font-semibold transition-all duration-300">
                    <i data-lucide="phone" class="w-5 h-5 mr-2"></i>
                    Call +234 805 247 7420
                </a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-16">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-400">
                &copy; 2024 Unique Brilliant Schools. All rights reserved.
            </p>
        </div>
    </footer>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>
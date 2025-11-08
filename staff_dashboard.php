<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Unique Brilliant Schools</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto p-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold mb-4">Welcome, <?php echo $_SESSION['first_name']; ?>!</h1>
            <p class="text-gray-600">Staff Dashboard - Under Development</p>
            <div class="mt-6">
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                    Logout
                </a>
            </div>
        </div>
    </div>
</body>
</html>
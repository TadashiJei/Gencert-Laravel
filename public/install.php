<?php
/**
 * CertificateHub Pre-Installation Script
 * This file should be uploaded with the application files to handle initial setup
 */

// Prevent direct access if application is installed
if (file_exists(__DIR__ . '/../storage/installed')) {
    header('Location: /');
    exit;
}

// Function to check directory permissions
function checkDirectoryPermissions($path) {
    return is_writable($path) ? true : false;
}

// Function to check PHP extensions
function checkExtension($name) {
    return extension_loaded($name);
}

// Function to create directory if not exists
function createDirectory($path) {
    if (!file_exists($path)) {
        return @mkdir($path, 0755, true);
    }
    return true;
}

// Function to copy directory
function copyDirectory($source, $destination) {
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $srcFile = $source . '/' . $file;
            $destFile = $destination . '/' . $file;
            if (is_dir($srcFile)) {
                copyDirectory($srcFile, $destFile);
            } else {
                copy($srcFile, $destFile);
            }
        }
    }
    closedir($dir);
}

// Handle form submission
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create necessary directories
        $directories = [
            __DIR__ . '/../storage',
            __DIR__ . '/../storage/app',
            __DIR__ . '/../storage/framework',
            __DIR__ . '/../storage/framework/cache',
            __DIR__ . '/../storage/framework/sessions',
            __DIR__ . '/../storage/framework/views',
            __DIR__ . '/../storage/logs',
            __DIR__ . '/../bootstrap/cache'
        ];

        foreach ($directories as $directory) {
            if (!createDirectory($directory)) {
                throw new Exception("Failed to create directory: $directory");
            }
        }

        // Set directory permissions
        foreach ($directories as $directory) {
            if (!chmod($directory, 0755)) {
                throw new Exception("Failed to set permissions for: $directory");
            }
        }

        // Create .env file if not exists
        if (!file_exists(__DIR__ . '/../.env')) {
            if (!copy(__DIR__ . '/../.env.example', __DIR__ . '/../.env')) {
                throw new Exception("Failed to create .env file");
            }
        }

        $message = "Pre-installation completed successfully! You can now proceed with the main installation.";
        header("Refresh: 3; url=/install");
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check requirements
$requirements = [
    'php_version' => [
        'name' => 'PHP Version (>= 8.1)',
        'result' => version_compare(PHP_VERSION, '8.1.0', '>=')
    ],
    'bcmath' => [
        'name' => 'BCMath Extension',
        'result' => checkExtension('bcmath')
    ],
    'ctype' => [
        'name' => 'Ctype Extension',
        'result' => checkExtension('ctype')
    ],
    'json' => [
        'name' => 'JSON Extension',
        'result' => checkExtension('json')
    ],
    'mbstring' => [
        'name' => 'Mbstring Extension',
        'result' => checkExtension('mbstring')
    ],
    'openssl' => [
        'name' => 'OpenSSL Extension',
        'result' => checkExtension('openssl')
    ],
    'pdo' => [
        'name' => 'PDO Extension',
        'result' => checkExtension('pdo')
    ],
    'tokenizer' => [
        'name' => 'Tokenizer Extension',
        'result' => checkExtension('tokenizer')
    ],
    'xml' => [
        'name' => 'XML Extension',
        'result' => checkExtension('xml')
    ]
];

// Check directory permissions
$directories = [
    'storage' => [
        'name' => 'Storage Directory',
        'path' => __DIR__ . '/../storage',
        'result' => checkDirectoryPermissions(__DIR__ . '/../storage')
    ],
    'bootstrap' => [
        'name' => 'Bootstrap Cache Directory',
        'path' => __DIR__ . '/../bootstrap/cache',
        'result' => checkDirectoryPermissions(__DIR__ . '/../bootstrap/cache')
    ]
];

$canProceed = !in_array(false, array_column($requirements, 'result')) && 
              !in_array(false, array_column($directories, 'result'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CertificateHub Pre-Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-4xl w-full mx-4">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">CertificateHub Pre-Installation</h1>
                    <p class="text-gray-600">This script will prepare your server for CertificateHub installation</p>
                </div>

                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4">System Requirements</h2>
                    <div class="space-y-2">
                        <?php foreach ($requirements as $requirement): ?>
                            <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                <span><?php echo $requirement['name']; ?></span>
                                <?php if ($requirement['result']): ?>
                                    <span class="text-green-500">✓</span>
                                <?php else: ?>
                                    <span class="text-red-500">✗</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4">Directory Permissions</h2>
                    <div class="space-y-2">
                        <?php foreach ($directories as $directory): ?>
                            <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                <span><?php echo $directory['name']; ?></span>
                                <?php if ($directory['result']): ?>
                                    <span class="text-green-500">✓ Writable</span>
                                <?php else: ?>
                                    <span class="text-red-500">✗ Not Writable</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($canProceed): ?>
                    <form method="post" class="text-center">
                        <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Prepare Installation
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-center text-red-600">
                        Please fix the above requirements before proceeding with the installation.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

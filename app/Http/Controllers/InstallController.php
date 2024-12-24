<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use PDO;

class InstallController extends Controller
{
    /**
     * Show the installation page
     */
    public function index()
    {
        // Check if already installed
        if (file_exists(storage_path('installed'))) {
            return redirect('/')->with('error', 'Application is already installed.');
        }

        $requirements = $this->checkRequirements();
        return view('install.index', compact('requirements'));
    }

    /**
     * Process the installation
     */
    public function setup(Request $request)
    {
        // Validate request
        $request->validate([
            'db_host' => 'required',
            'db_name' => 'required',
            'db_user' => 'required',
            'db_password' => 'required',
            'admin_email' => 'required|email',
            'admin_password' => 'required|confirmed|min:8',
            'app_name' => 'required',
            'app_url' => 'required|url'
        ]);

        try {
            // Test database connection
            $this->testDatabaseConnection(
                $request->db_host,
                $request->db_name,
                $request->db_user,
                $request->db_password
            );

            // Update .env file
            $this->updateEnvironmentFile($request);

            // Run migrations
            Artisan::call('migrate:fresh', ['--force' => true]);

            // Create admin user
            $this->createAdminUser(
                $request->admin_email,
                $request->admin_password
            );

            // Generate application key
            Artisan::call('key:generate', ['--force' => true]);

            // Create storage link
            Artisan::call('storage:link');

            // Mark as installed
            file_put_contents(storage_path('installed'), 'Installation completed on ' . date('Y-m-d H:i:s'));

            // Clear cache
            Artisan::call('optimize:clear');

            return redirect('/login')->with('success', 'Installation completed successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Installation failed: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Check system requirements
     */
    private function checkRequirements()
    {
        return [
            [
                'name' => 'PHP Version',
                'current' => PHP_VERSION,
                'required' => '8.1.0',
                'met' => version_compare(PHP_VERSION, '8.1.0', '>=')
            ],
            [
                'name' => 'BCMath Extension',
                'current' => extension_loaded('bcmath') ? 'Enabled' : 'Not Enabled',
                'required' => 'Enabled',
                'met' => extension_loaded('bcmath')
            ],
            [
                'name' => 'Ctype Extension',
                'current' => extension_loaded('ctype') ? 'Enabled' : 'Not Enabled',
                'required' => 'Enabled',
                'met' => extension_loaded('ctype')
            ],
            [
                'name' => 'JSON Extension',
                'current' => extension_loaded('json') ? 'Enabled' : 'Not Enabled',
                'required' => 'Enabled',
                'met' => extension_loaded('json')
            ],
            [
                'name' => 'Mbstring Extension',
                'current' => extension_loaded('mbstring') ? 'Enabled' : 'Not Enabled',
                'required' => 'Enabled',
                'met' => extension_loaded('mbstring')
            ],
            [
                'name' => 'OpenSSL Extension',
                'current' => extension_loaded('openssl') ? 'Enabled' : 'Not Enabled',
                'required' => 'Enabled',
                'met' => extension_loaded('openssl')
            ],
            [
                'name' => 'PDO Extension',
                'current' => extension_loaded('pdo') ? 'Enabled' : 'Not Enabled',
                'required' => 'Enabled',
                'met' => extension_loaded('pdo')
            ],
            [
                'name' => 'Storage Directory Writable',
                'current' => is_writable(storage_path()) ? 'Writable' : 'Not Writable',
                'required' => 'Writable',
                'met' => is_writable(storage_path())
            ],
            [
                'name' => 'Cache Directory Writable',
                'current' => is_writable(base_path('bootstrap/cache')) ? 'Writable' : 'Not Writable',
                'required' => 'Writable',
                'met' => is_writable(base_path('bootstrap/cache'))
            ]
        ];
    }

    /**
     * Test database connection
     */
    private function testDatabaseConnection($host, $database, $username, $password)
    {
        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$database",
                $username,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            return true;
        } catch (\Exception $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Update environment file
     */
    private function updateEnvironmentFile(Request $request)
    {
        $envFile = base_path('.env');
        $envExample = base_path('.env.example');

        if (!file_exists($envFile)) {
            if (!copy($envExample, $envFile)) {
                throw new \Exception('Failed to create .env file');
            }
        }

        $env = file_get_contents($envFile);

        $env = preg_replace('/DB_HOST=.*/', 'DB_HOST=' . $request->db_host, $env);
        $env = preg_replace('/DB_DATABASE=.*/', 'DB_DATABASE=' . $request->db_name, $env);
        $env = preg_replace('/DB_USERNAME=.*/', 'DB_USERNAME=' . $request->db_user, $env);
        $env = preg_replace('/DB_PASSWORD=.*/', 'DB_PASSWORD=' . $request->db_password, $env);
        $env = preg_replace('/APP_NAME=.*/', 'APP_NAME="' . $request->app_name . '"', $env);
        $env = preg_replace('/APP_URL=.*/', 'APP_URL=' . $request->app_url, $env);
        $env = preg_replace('/APP_ENV=.*/', 'APP_ENV=production', $env);
        $env = preg_replace('/APP_DEBUG=.*/', 'APP_DEBUG=false', $env);

        if (!file_put_contents($envFile, $env)) {
            throw new \Exception('Failed to write .env file');
        }
    }

    /**
     * Create admin user
     */
    private function createAdminUser($email, $password)
    {
        return User::create([
            'name' => 'Administrator',
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);
    }
}

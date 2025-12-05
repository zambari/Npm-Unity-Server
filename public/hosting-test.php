<?php
/**
 * Hosting Capabilities Test Script
 * 
 * Upload this file to your hosting and access it via browser to see
 * what PHP functions and capabilities are available on your server.
 * 
 * SECURITY NOTE: Delete this file after testing!
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Hosting Capabilities Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; border-left: 4px solid #4CAF50; padding-left: 10px; }
        .test-section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px; }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        .info { color: #2196F3; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .section-result { margin: 5px 0; padding: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 PHP Hosting Capabilities Test</h1>
        
        <?php
        $results = [];
        
        // Helper function to test if a function exists
        function testFunction($name, $description = '') {
            $exists = function_exists($name);
            return [
                'name' => $name,
                'description' => $description,
                'available' => $exists,
                'status' => $exists ? 'success' : 'error'
            ];
        }
        
        // Helper function to test if an extension is loaded
        function testExtension($name, $description = '') {
            $loaded = extension_loaded($name);
            return [
                'name' => $name,
                'description' => $description,
                'available' => $loaded,
                'status' => $loaded ? 'success' : 'error'
            ];
        }
        
        // Helper function to safely test a function call
        function safeTest($name, $callback, $description = '') {
            try {
                $result = $callback();
                return [
                    'name' => $name,
                    'description' => $description,
                    'available' => true,
                    'status' => 'success',
                    'details' => $result
                ];
            } catch (Exception $e) {
                return [
                    'name' => $name,
                    'description' => $description,
                    'available' => false,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            } catch (Error $e) {
                return [
                    'name' => $name,
                    'description' => $description,
                    'available' => false,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // PHP Version and Basic Info
        echo '<h2>📋 PHP Information</h2>';
        echo '<div class="test-section">';
        echo '<p><strong>PHP Version:</strong> <span class="info">' . phpversion() . '</span></p>';
        echo '<p><strong>Server API:</strong> <span class="info">' . php_sapi_name() . '</span></p>';
        echo '<p><strong>Operating System:</strong> <span class="info">' . PHP_OS . '</span></p>';
        echo '<p><strong>Document Root:</strong> <span class="code">' . $_SERVER['DOCUMENT_ROOT'] . '</span></p>';
        echo '<p><strong>Script Path:</strong> <span class="code">' . __FILE__ . '</span></p>';
        echo '</div>';
        
        // PHP Configuration
        echo '<h2>⚙️ PHP Configuration</h2>';
        echo '<div class="test-section">';
        $configs = [
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_input_vars' => ini_get('max_input_vars'),
            'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Yes' : 'No',
            'allow_url_include' => ini_get('allow_url_include') ? 'Yes' : 'No',
            'disable_functions' => ini_get('disable_functions') ?: 'None',
            'disable_classes' => ini_get('disable_classes') ?: 'None',
        ];
        echo '<table>';
        echo '<tr><th>Setting</th><th>Value</th></tr>';
        foreach ($configs as $key => $value) {
            echo '<tr><td>' . $key . '</td><td>' . htmlspecialchars($value) . '</td></tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // File Operations
        echo '<h2>📁 File Operations</h2>';
        echo '<div class="test-section">';
        $fileTests = [];
        
        // Test write permission
        $testFile = __DIR__ . '/test_write_' . time() . '.txt';
        $fileTests[] = safeTest('File Write', function() use ($testFile) {
            $result = @file_put_contents($testFile, 'test');
            if ($result === false) {
                throw new Exception('Cannot write to file');
            }
            return "Wrote {$result} bytes";
        }, 'Write to file');
        
        // Test read permission
        if (file_exists($testFile)) {
            $fileTests[] = safeTest('File Read', function() use ($testFile) {
                $content = @file_get_contents($testFile);
                if ($content === false) {
                    throw new Exception('Cannot read from file');
                }
                return "Read " . strlen($content) . " bytes";
            }, 'Read from file');
            
            // Test delete
            $fileTests[] = safeTest('File Delete', function() use ($testFile) {
                if (!@unlink($testFile)) {
                    throw new Exception('Cannot delete file');
                }
                return "File deleted successfully";
            }, 'Delete file');
        }
        
        // Test directory creation
        $testDir = __DIR__ . '/test_dir_' . time();
        $fileTests[] = safeTest('Directory Create', function() use ($testDir) {
            if (!@mkdir($testDir, 0755, true)) {
                throw new Exception('Cannot create directory');
            }
            return "Directory created";
        }, 'Create directory');
        
        // Test directory deletion
        if (is_dir($testDir)) {
            $fileTests[] = safeTest('Directory Delete', function() use ($testDir) {
                if (!@rmdir($testDir)) {
                    throw new Exception('Cannot delete directory');
                }
                return "Directory deleted";
            }, 'Delete directory');
        }
        
        // Display file operation results
        echo '<table>';
        echo '<tr><th>Operation</th><th>Status</th><th>Details</th></tr>';
        foreach ($fileTests as $test) {
            $statusClass = $test['status'];
            $statusText = $test['available'] ? '✓ Available' : '✗ Not Available';
            $details = isset($test['details']) ? $test['details'] : (isset($test['error']) ? $test['error'] : '');
            echo '<tr>';
            echo '<td>' . htmlspecialchars($test['name']) . '</td>';
            echo '<td><span class="' . $statusClass . '">' . $statusText . '</span></td>';
            echo '<td>' . htmlspecialchars($details) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // Archive Operations
        echo '<h2>📦 Archive Operations</h2>';
        echo '<div class="test-section">';
        $archiveTests = [];
        
        // Test ZipArchive
        $archiveTests[] = safeTest('ZipArchive Class', function() {
            if (!class_exists('ZipArchive')) {
                throw new Exception('ZipArchive class not available');
            }
            $zip = new ZipArchive();
            $testZip = __DIR__ . '/test_' . time() . '.zip';
            $result = $zip->open($testZip, ZipArchive::CREATE);
            if ($result !== TRUE) {
                throw new Exception('Cannot create zip file');
            }
            $zip->addFromString('test.txt', 'test content');
            $zip->close();
            @unlink($testZip);
            return "Can create and write zip files";
        }, 'Create ZIP archive');
        
        // Test ZipArchive extract
        $archiveTests[] = safeTest('ZipArchive Extract', function() {
            if (!class_exists('ZipArchive')) {
                throw new Exception('ZipArchive class not available');
            }
            $zip = new ZipArchive();
            $testZip = __DIR__ . '/test_extract_' . time() . '.zip';
            $extractDir = __DIR__ . '/test_extract_' . time();
            
            // Create test zip
            $zip->open($testZip, ZipArchive::CREATE);
            $zip->addFromString('test.txt', 'test content');
            $zip->close();
            
            // Extract
            $zip->open($testZip);
            if (!$zip->extractTo($extractDir)) {
                throw new Exception('Cannot extract zip file');
            }
            $zip->close();
            
            // Cleanup
            @unlink($testZip);
            if (file_exists($extractDir . '/test.txt')) {
                @unlink($extractDir . '/test.txt');
            }
            @rmdir($extractDir);
            
            return "Can extract zip files";
        }, 'Extract ZIP archive');
        
        // Test ZipArchive read
        $archiveTests[] = safeTest('ZipArchive Read', function() {
            if (!class_exists('ZipArchive')) {
                throw new Exception('ZipArchive class not available');
            }
            $zip = new ZipArchive();
            $testZip = __DIR__ . '/test_read_' . time() . '.zip';
            
            // Create test zip
            $zip->open($testZip, ZipArchive::CREATE);
            $zip->addFromString('test.txt', 'test content');
            $zip->close();
            
            // Read
            $zip->open($testZip);
            $content = $zip->getFromName('test.txt');
            if ($content === false) {
                throw new Exception('Cannot read from zip file');
            }
            $zip->close();
            
            @unlink($testZip);
            return "Can read from zip files";
        }, 'Read from ZIP archive');
        
        // Test tar.gz (using PharData if available)
        $archiveTests[] = safeTest('PharData (TAR/GZ)', function() {
            if (!class_exists('PharData')) {
                throw new Exception('PharData class not available');
            }
            $testTar = __DIR__ . '/test_' . time() . '.tar';
            $tar = new PharData($testTar);
            $tar->addFromString('test.txt', 'test content');
            @unlink($testTar);
            return "Can create TAR archives";
        }, 'Create TAR archive');
        
        // Test tar.gz compression
        $archiveTests[] = safeTest('TAR.GZ Compression', function() {
            if (!class_exists('PharData')) {
                throw new Exception('PharData class not available');
            }
            $testTar = __DIR__ . '/test_' . time() . '.tar';
            $testTarGz = $testTar . '.gz';
            $tar = new PharData($testTar);
            $tar->addFromString('test.txt', 'test content');
            $tar->compress(Phar::GZ);
            if (!file_exists($testTarGz)) {
                throw new Exception('Cannot create compressed tar.gz');
            }
            @unlink($testTar);
            @unlink($testTarGz);
            return "Can create TAR.GZ archives";
        }, 'Create TAR.GZ archive');
        
        // Display archive results
        echo '<table>';
        echo '<tr><th>Operation</th><th>Status</th><th>Details</th></tr>';
        foreach ($archiveTests as $test) {
            $statusClass = $test['status'];
            $statusText = $test['available'] ? '✓ Available' : '✗ Not Available';
            $details = isset($test['details']) ? $test['details'] : (isset($test['error']) ? $test['error'] : '');
            echo '<tr>';
            echo '<td>' . htmlspecialchars($test['name']) . '</td>';
            echo '<td><span class="' . $statusClass . '">' . $statusText . '</span></td>';
            echo '<td>' . htmlspecialchars($details) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // System Commands
        echo '<h2>💻 System Commands</h2>';
        echo '<div class="test-section">';
        $systemTests = [];
        
        $systemTests[] = safeTest('exec()', function() {
            if (!function_exists('exec')) {
                throw new Exception('exec() function is disabled');
            }
            $output = [];
            $return = 0;
            @exec('echo test', $output, $return);
            if ($return !== 0 && empty($output)) {
                throw new Exception('exec() cannot execute commands');
            }
            return "Can execute system commands";
        }, 'Execute system commands');
        
        $systemTests[] = safeTest('shell_exec()', function() {
            if (!function_exists('shell_exec')) {
                throw new Exception('shell_exec() function is disabled');
            }
            $result = @shell_exec('echo test');
            if ($result === null) {
                throw new Exception('shell_exec() cannot execute commands');
            }
            return "Can execute shell commands";
        }, 'Execute shell commands');
        
        $systemTests[] = safeTest('system()', function() {
            if (!function_exists('system')) {
                throw new Exception('system() function is disabled');
            }
            $result = @system('echo test', $return);
            if ($result === false) {
                throw new Exception('system() cannot execute commands');
            }
            return "Can execute system() commands";
        }, 'Execute system() commands');
        
        $systemTests[] = safeTest('passthru()', function() {
            if (!function_exists('passthru')) {
                throw new Exception('passthru() function is disabled');
            }
            ob_start();
            $result = @passthru('echo test', $return);
            ob_end_clean();
            if ($result === false) {
                throw new Exception('passthru() cannot execute commands');
            }
            return "Can execute passthru() commands";
        }, 'Execute passthru() commands');
        
        echo '<table>';
        echo '<tr><th>Function</th><th>Status</th><th>Details</th></tr>';
        foreach ($systemTests as $test) {
            $statusClass = $test['status'];
            $statusText = $test['available'] ? '✓ Available' : '✗ Not Available';
            $details = isset($test['details']) ? $test['details'] : (isset($test['error']) ? $test['error'] : '');
            echo '<tr>';
            echo '<td>' . htmlspecialchars($test['name']) . '</td>';
            echo '<td><span class="' . $statusClass . '">' . $statusText . '</span></td>';
            echo '<td>' . htmlspecialchars($details) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // PHP Extensions
        echo '<h2>🔌 PHP Extensions</h2>';
        echo '<div class="test-section">';
        $extensions = [
            ['name' => 'zip', 'desc' => 'ZIP archive support'],
            ['name' => 'zlib', 'desc' => 'Zlib compression'],
            ['name' => 'bz2', 'desc' => 'Bzip2 compression'],
            ['name' => 'phar', 'desc' => 'Phar archive support (for TAR)'],
            ['name' => 'curl', 'desc' => 'cURL for HTTP requests'],
            ['name' => 'openssl', 'desc' => 'OpenSSL for encryption'],
            ['name' => 'json', 'desc' => 'JSON support'],
            ['name' => 'xml', 'desc' => 'XML support'],
            ['name' => 'gd', 'desc' => 'GD image library'],
            ['name' => 'imagick', 'desc' => 'ImageMagick'],
            ['name' => 'mysqli', 'desc' => 'MySQLi database'],
            ['name' => 'pdo', 'desc' => 'PDO database abstraction'],
            ['name' => 'sqlite3', 'desc' => 'SQLite3 database'],
            ['name' => 'mbstring', 'desc' => 'Multibyte string support'],
            ['name' => 'intl', 'desc' => 'Internationalization'],
            ['name' => 'ftp', 'desc' => 'FTP support'],
            ['name' => 'sockets', 'desc' => 'Socket support'],
        ];
        
        echo '<table>';
        echo '<tr><th>Extension</th><th>Description</th><th>Status</th></tr>';
        foreach ($extensions as $ext) {
            $loaded = extension_loaded($ext['name']);
            $statusClass = $loaded ? 'success' : 'error';
            $statusText = $loaded ? '✓ Loaded' : '✗ Not Loaded';
            echo '<tr>';
            echo '<td><span class="code">' . htmlspecialchars($ext['name']) . '</span></td>';
            echo '<td>' . htmlspecialchars($ext['desc']) . '</td>';
            echo '<td><span class="' . $statusClass . '">' . $statusText . '</span></td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // Important Functions
        echo '<h2>⚡ Important Functions</h2>';
        echo '<div class="test-section">';
        $functions = [
            ['name' => 'file_get_contents', 'desc' => 'Read file contents'],
            ['name' => 'file_put_contents', 'desc' => 'Write file contents'],
            ['name' => 'fopen', 'desc' => 'Open file'],
            ['name' => 'mkdir', 'desc' => 'Create directory'],
            ['name' => 'rmdir', 'desc' => 'Remove directory'],
            ['name' => 'unlink', 'desc' => 'Delete file'],
            ['name' => 'copy', 'desc' => 'Copy file'],
            ['name' => 'move_uploaded_file', 'desc' => 'Move uploaded file'],
            ['name' => 'chmod', 'desc' => 'Change file permissions'],
            ['name' => 'chown', 'desc' => 'Change file owner'],
            ['name' => 'symlink', 'desc' => 'Create symbolic link'],
            ['name' => 'readlink', 'desc' => 'Read symbolic link'],
            ['name' => 'realpath', 'desc' => 'Get absolute path'],
            ['name' => 'glob', 'desc' => 'Find files matching pattern'],
            ['name' => 'scandir', 'desc' => 'List directory contents'],
        ];
        
        echo '<table>';
        echo '<tr><th>Function</th><th>Description</th><th>Status</th></tr>';
        foreach ($functions as $func) {
            $exists = function_exists($func['name']);
            $statusClass = $exists ? 'success' : 'error';
            $statusText = $exists ? '✓ Available' : '✗ Not Available';
            echo '<tr>';
            echo '<td><span class="code">' . htmlspecialchars($func['name']) . '</span></td>';
            echo '<td>' . htmlspecialchars($func['desc']) . '</td>';
            echo '<td><span class="' . $statusClass . '">' . $statusText . '</span></td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // Permissions Test
        echo '<h2>🔐 Directory Permissions</h2>';
        echo '<div class="test-section">';
        $dirs = [
            __DIR__,
            __DIR__ . '/..',
            dirname(__DIR__),
        ];
        
        echo '<table>';
        echo '<tr><th>Directory</th><th>Readable</th><th>Writable</th><th>Executable</th><th>Permissions</th></tr>';
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $readable = is_readable($dir) ? '✓' : '✗';
                $writable = is_writable($dir) ? '✓' : '✗';
                $executable = is_executable($dir) ? '✓' : '✗';
                $perms = substr(sprintf('%o', fileperms($dir)), -4);
                echo '<tr>';
                echo '<td><span class="code">' . htmlspecialchars($dir) . '</span></td>';
                echo '<td>' . $readable . '</td>';
                echo '<td>' . $writable . '</td>';
                echo '<td>' . $executable . '</td>';
                echo '<td>' . $perms . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
        echo '</div>';
        
        // Summary
        echo '<h2>📊 Summary</h2>';
        echo '<div class="test-section">';
        echo '<p><strong>⚠️ Security Reminder:</strong> Please delete this file (<span class="code">' . basename(__FILE__) . '</span>) after testing!</p>';
        echo '<p>This script has tested various PHP capabilities on your hosting environment.</p>';
        echo '<p>Review the results above to determine what operations are available for your application.</p>';
        echo '</div>';
        ?>
    </div>
</body>
</html>


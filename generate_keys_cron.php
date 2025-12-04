<?php
// cron_key_checker.php
require('fpdf.php');

// Set no time limit for execution
set_time_limit(0);

// Database configuration (if needed for logging)
$db_host = 'localhost';
$db_name = 'your_database_name';
$db_user = 'your_database_user';
$db_pass = 'your_database_password';

function generateRandomKey($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Times', 'B', 16);
        $this->Cell(0, 10, 'BRGYCare Confirmation Keys - ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Times', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }

    function AddPurokKeys($purok, $keys) {
        $this->AddPage();
        $this->SetFont('Times', 'B', 14);
        $this->Cell(0, 10, "Purok {$purok}", 0, 1);
        $this->SetFont('Times', '', 10);
        
        $this->SetFillColor(200, 200, 200);
        $this->Cell(80, 8, 'Confirmation Key', 1, 0, 'C', 1);
        $this->Cell(25, 8, 'Status', 1, 1, 'C', 1);
        
        $count = 0;
        foreach ($keys as $key => $data) {
            $this->Cell(80, 8, $key, 1, 0);
            $this->Cell(25, 8, $data['used'] ? 'Used' : 'Unused', 1, 1);
            $count++;
            // Prevent memory issues with large datasets
            if ($count % 50 === 0) {
                $this->CheckPageBreak(20);
            }
        }
    }
    
    function CheckPageBreak($height) {
        if ($this->GetY() + $height > $this->PageBreakTrigger) {
            $this->AddPage();
        }
    }
}

// Ensure keys and logs directories exist
if (!is_dir('keys')) {
    mkdir('keys', 0777, true);
}
if (!is_dir('logs')) {
    mkdir('logs', 0777, true);
}
if (!is_dir('pdf_backups')) {
    mkdir('pdf_backups', 0777, true);
}

// Log file with timestamp
$log_file = 'logs/key_checker_' . date('Y-m-d') . '.log';

function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // Also output to console if running via CLI
    if (php_sapi_name() === 'cli') {
        echo $log_entry;
    }
}

function checkPurokKeys($purok, $keys_per_purok = 1000) {
    $filename = "keys/{$purok}_confirmation_key.json";
    $regenerated = false;
    
    // Check if file exists and get its status
    if (file_exists($filename)) {
        $keys = json_decode(file_get_contents($filename), true);
        if ($keys) {
            $used_count = 0;
            $total_count = count($keys);
            
            foreach ($keys as $data) {
                if ($data['used']) {
                    $used_count++;
                }
            }
            
            $usage_percentage = ($used_count / $total_count) * 100;
            
            // Regenerate if 90% or more keys are used
            if ($usage_percentage >= 90) {
                log_message("Purok {$purok}: {$usage_percentage}% used ({$used_count}/{$total_count}) - Regenerating keys");
                $regenerated = regeneratePurokKeys($purok, $keys_per_purok);
            } else {
                log_message("Purok {$purok}: {$usage_percentage}% used ({$used_count}/{$total_count}) - No action needed");
            }
        } else {
            log_message("Purok {$purok}: Invalid JSON file - Regenerating keys");
            $regenerated = regeneratePurokKeys($purok, $keys_per_purok);
        }
    } else {
        log_message("Purok {$purok}: No key file found - Generating new keys");
        $regenerated = regeneratePurokKeys($purok, $keys_per_purok);
    }
    
    return $regenerated;
}

function regeneratePurokKeys($purok, $keys_per_purok) {
    $keys = [];
    $filename = "keys/{$purok}_confirmation_key.json";
    
    // Backup old file if it exists
    if (file_exists($filename)) {
        $backup_dir = "keys/backups/";
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        $backup_file = $backup_dir . $purok . '_backup_' . date('Y-m-d_His') . '.json';
        copy($filename, $backup_file);
    }
    
    // Generate new keys
    $attempts = 0;
    $max_attempts = $keys_per_purok * 2; // Prevent infinite loop
    
    while (count($keys) < $keys_per_purok && $attempts < $max_attempts) {
        $unique_key = "SMCT-{$purok}-" . generateRandomKey();
        if (!isset($keys[$unique_key])) {
            $keys[$unique_key] = ['used' => false, 'created_at' => date('Y-m-d H:i:s')];
        }
        $attempts++;
    }
    
    if (count($keys) === $keys_per_purok) {
        file_put_contents($filename, json_encode($keys, JSON_PRETTY_PRINT));
        log_message("Purok {$purok}: Successfully generated {$keys_per_purok} new keys");
        return true;
    } else {
        log_message("Purok {$purok}: ERROR - Only generated " . count($keys) . " out of {$keys_per_purok} keys");
        return false;
    }
}

function generateMasterPDF($puroks) {
    $pdf = new PDF();
    $pdf->SetTitle('BRGYCare Confirmation Keys - ' . date('Y-m-d H:i:s'));
    
    $total_keys = 0;
    $total_used = 0;
    
    foreach ($puroks as $purok) {
        $filename = "keys/{$purok}_confirmation_key.json";
        if (file_exists($filename)) {
            $keys = json_decode(file_get_contents($filename), true);
            if ($keys) {
                $used_count = 0;
                foreach ($keys as $data) {
                    if ($data['used']) {
                        $used_count++;
                    }
                }
                $total_keys += count($keys);
                $total_used += $used_count;
                
                $pdf->AddPurokKeys($purok, $keys);
            }
        }
    }
    
    // Add summary page
    $pdf->AddPage();
    $pdf->SetFont('Times', 'B', 16);
    $pdf->Cell(0, 10, 'Summary Report', 0, 1, 'C');
    $pdf->Ln(10);
    
    $pdf->SetFont('Times', '', 12);
    $pdf->Cell(0, 10, "Total Keys: {$total_keys}", 0, 1);
    $pdf->Cell(0, 10, "Total Used: {$total_used}", 0, 1);
    $pdf->Cell(0, 10, "Total Available: " . ($total_keys - $total_used), 0, 1);
    $pdf->Cell(0, 10, "Generated: " . date('Y-m-d H:i:s'), 0, 1);
    
    $pdf_filename = 'pdf_backups/BRGYCare_keys_' . date('Y-m-d_His') . '.pdf';
    $pdf->Output('F', $pdf_filename);
    
    return [
        'filename' => $pdf_filename,
        'total_keys' => $total_keys,
        'total_used' => $total_used,
        'total_available' => $total_keys - $total_used
    ];
}

// Main execution
function main() {
    log_message("=== Starting Hourly Key Check ===");
    
    $puroks = ['P1', 'P2', 'P3', 'P4A', 'P4B', 'P5', 'P6', 'P7'];
    $keys_per_purok = 1000;
    $regenerated_puroks = [];
    
    // Check each purok
    foreach ($puroks as $purok) {
        if (checkPurokKeys($purok, $keys_per_purok)) {
            $regenerated_puroks[] = $purok;
        }
    }
    
    // Generate PDF report if any puroks were regenerated
    if (!empty($regenerated_puroks)) {
        $pdf_result = generateMasterPDF($puroks);
        log_message("PDF generated: {$pdf_result['filename']}");
        log_message("Summary - Total: {$pdf_result['total_keys']}, Used: {$pdf_result['total_used']}, Available: {$pdf_result['total_available']}");
    }
    
    // Cleanup old backup files (keep only last 7 days)
    cleanupOldBackups();
    
    log_message("=== Hourly Key Check Completed ===");
    log_message("Puroks regenerated: " . (empty($regenerated_puroks) ? 'None' : implode(', ', $regenerated_puroks)));
    
    return $regenerated_puroks;
}

function cleanupOldBackups() {
    $backup_dirs = ['keys/backups/', 'pdf_backups/'];
    $days_to_keep = 7;
    $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);
    
    foreach ($backup_dirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoff_time) {
                    unlink($file);
                    log_message("Cleaned up old backup: $file");
                }
            }
        }
    }
}

// Run the main function
$regenerated = main();

// For web access, show results
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Key Checker Results</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .success { color: #28a745; }
            .info { color: #17a2b8; }
            .log { background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 20px; font-family: monospace; white-space: pre-wrap; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>BRGYCare Key Checker</h1>
            <p class='success'>Hourly key check completed successfully!</p>";
    
    if (!empty($regenerated)) {
        echo "<p><strong>Regenerated keys for:</strong> " . implode(', ', $regenerated) . "</p>";
    } else {
        echo "<p class='info'>No keys needed regeneration at this time.</p>";
    }
    
    echo "<p><strong>Last checked:</strong> " . date('Y-m-d H:i:s') . "</p>";
    
    // Show last few log entries
    $log_file = 'logs/key_checker_' . date('Y-m-d') . '.log';
    if (file_exists($log_file)) {
        $logs = file_get_contents($log_file);
        $log_lines = explode("\n", $logs);
        $recent_logs = array_slice($log_lines, -10); // Last 10 lines
        echo "<div class='log'>" . implode("\n", $recent_logs) . "</div>";
    }
    
    echo "</div></body></html>";
}
?>
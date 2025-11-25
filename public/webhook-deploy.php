<?php
/**
 * Social Media Platform - Webhook Deployment Handler
 * 
 * This script handles automated deployments triggered by Git webhooks
 * Place this file in the public directory and configure your Git provider
 * to send webhooks to this endpoint.
 * 
 * Usage: Configure your Git provider to POST to this script's URL
 */

// Configuration
$config = [
    'secret' => '5co938xI1y9oY8fXX1', // Change this to a secure secret
    'branch' => 'main', // Branch to deploy
    'environment' => 'production', // Environment to deploy to
    'project_path' => '/var/www/vhosts/social.add-digital.co.uk/httpdocs', // Absolute path to project root (not backend)
    'allowed_ips' => [
        // GitHub webhook IPs
        '192.30.252.0/22',
        '185.199.108.0/22',
        '140.82.112.0/20',
        // GitLab webhook IPs
        '35.231.145.151',
        '35.243.140.225',
        // Add your Git provider's IPs here
    ],
    'log_file' => '/var/www/vhosts/social.add-digital.co.uk/httpdocs/storage/logs/webhook-deploy.log',
    'max_log_size' => 10485760, // 10MB
];

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

/**
 * Log function
 */
function logMessage($message, $level = 'INFO') {
    global $config;
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Ensure log directory exists
    $logDir = dirname($config['log_file']);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Rotate log if too large
    if (file_exists($config['log_file']) && filesize($config['log_file']) > $config['max_log_size']) {
        rename($config['log_file'], $config['log_file'] . '.old');
    }
    
    file_put_contents($config['log_file'], $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Send JSON response
 */
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Verify webhook signature
 */
function verifySignature($payload, $signature, $secret) {
    // GitHub signature format: sha256=hash
    if (strpos($signature, 'sha256=') === 0) {
        $hash = hash_hmac('sha256', $payload, $secret);
        return hash_equals('sha256=' . $hash, $signature);
    }
    
    // GitLab signature format: hash
    $hash = hash_hmac('sha256', $payload, $secret);
    return hash_equals($hash, $signature);
}

/**
 * Check if IP is allowed
 */
function isIpAllowed($ip, $allowedRanges) {
    foreach ($allowedRanges as $range) {
        if (strpos($range, '/') !== false) {
            // CIDR notation
            list($subnet, $mask) = explode('/', $range);
            if ((ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet)) {
                return true;
            }
        } else {
            // Single IP
            if ($ip === $range) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Execute deployment
 */
function executeDeploy($branch, $environment, $projectPath) {
    // Deploy script is in the project root deploy folder
    $deployScript = $projectPath . '/deploy/plesk-deploy.sh';
    
    if (!file_exists($deployScript)) {
        throw new Exception("Deployment script not found: $deployScript");
    }
    
    // Change to project directory (project root, not backend)
    chdir($projectPath);
    
    // Plesk-specific: Set proper environment variables
    $envVars = [
        'PATH' => '/opt/plesk/php/8.3/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
        'HOME' => '/var/www/vhosts/social.add-digital.co.uk',
        'USER' => 'social.add-digital.co.uk',
        'SHELL' => '/bin/bash'
    ];
    
    $envString = '';
    foreach ($envVars as $key => $value) {
        $envString .= "export $key='$value'; ";
    }
    
    // Execute deployment script with proper environment
    $command = "$envString bash $deployScript $environment $branch 2>&1";
    $output = [];
    $returnCode = 0;
    
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception("Deployment failed with exit code $returnCode: " . implode("\n", $output));
    }
    
    return implode("\n", $output);
}

// Main execution
try {
    logMessage("Webhook deployment request received from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logMessage("Invalid request method: " . $_SERVER['REQUEST_METHOD'], 'ERROR');
        sendResponse(['error' => 'Method not allowed'], 405);
    }
    
    // Check IP whitelist
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($config['allowed_ips']) && !isIpAllowed($clientIp, $config['allowed_ips'])) {
        logMessage("IP not allowed: $clientIp", 'ERROR');
        sendResponse(['error' => 'IP not allowed'], 403);
    }
    
    // Get payload
    $payload = file_get_contents('php://input');
    if (empty($payload)) {
        logMessage("Empty payload received", 'ERROR');
        sendResponse(['error' => 'Empty payload'], 400);
    }
    
    // Verify signature if secret is configured
    if (!empty($config['secret'])) {
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? $_SERVER['HTTP_X_GITLAB_TOKEN'] ?? '';
        
        if (empty($signature)) {
            logMessage("Missing signature header", 'ERROR');
            sendResponse(['error' => 'Missing signature'], 401);
        }
        
        if (!verifySignature($payload, $signature, $config['secret'])) {
            logMessage("Invalid signature", 'ERROR');
            sendResponse(['error' => 'Invalid signature'], 401);
        }
    }
    
    // Parse payload
    $data = json_decode($payload, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("Invalid JSON payload: " . json_last_error_msg(), 'ERROR');
        sendResponse(['error' => 'Invalid JSON'], 400);
    }
    
    // Determine Git provider and extract branch
    $branch = null;
    $commitHash = null;
    $commitMessage = null;
    
    // GitHub webhook
    if (isset($data['ref'])) {
        $branch = str_replace('refs/heads/', '', $data['ref']);
        $commitHash = $data['head_commit']['id'] ?? null;
        $commitMessage = $data['head_commit']['message'] ?? null;
    }
    // GitLab webhook
    elseif (isset($data['object_kind']) && $data['object_kind'] === 'push') {
        $branch = str_replace('refs/heads/', '', $data['ref']);
        $commitHash = $data['checkout_sha'] ?? null;
        $commitMessage = $data['commits'][0]['message'] ?? null;
    }
    else {
        logMessage("Unsupported webhook format", 'ERROR');
        sendResponse(['error' => 'Unsupported webhook format'], 400);
    }
    
    // Check if this is the branch we want to deploy
    if ($branch !== $config['branch']) {
        logMessage("Ignoring push to branch: $branch (configured: {$config['branch']})");
        sendResponse(['message' => 'Branch ignored', 'branch' => $branch]);
    }
    
    logMessage("Starting deployment for branch: $branch, commit: $commitHash");
    
    // Check if deployment is already running
    $lockFile = '/tmp/webhook-deploy.lock';
    if (file_exists($lockFile)) {
        $pid = file_get_contents($lockFile);
        if (function_exists('posix_kill') && posix_kill($pid, 0)) {
            logMessage("Deployment already running (PID: $pid)", 'WARNING');
            sendResponse(['error' => 'Deployment already running'], 409);
        } else {
            // Remove stale lock file
            unlink($lockFile);
        }
    }
    
    // Create lock file
    file_put_contents($lockFile, getmypid());
    
    // Execute deployment in background
    $deploymentId = uniqid('deploy_');
    $logFile = dirname($config['log_file']) . "/webhook-deploy-$deploymentId.log";
    
    // For Plesk shared hosting, we'll run deployment synchronously
    // as fork/pcntl functions may not be available
    try {
        $output = executeDeploy($config['branch'], $config['environment'], $config['project_path']);
        file_put_contents($logFile, $output);
        logMessage("Deployment completed successfully (ID: $deploymentId)");
        
        sendResponse([
            'message' => 'Deployment completed successfully',
            'deployment_id' => $deploymentId,
            'branch' => $branch,
            'commit' => $commitHash,
            'environment' => $config['environment'],
            'log_file' => $logFile,
            'status' => 'success'
        ]);
        
    } catch (Exception $e) {
        file_put_contents($logFile, "Deployment failed: " . $e->getMessage());
        logMessage("Deployment failed (ID: $deploymentId): " . $e->getMessage(), 'ERROR');
        
        sendResponse([
            'message' => 'Deployment failed',
            'deployment_id' => $deploymentId,
            'branch' => $branch,
            'commit' => $commitHash,
            'environment' => $config['environment'],
            'error' => $e->getMessage(),
            'log_file' => $logFile,
            'status' => 'failed'
        ], 500);
    } finally {
        // Clean up lock file
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }
    
} catch (Exception $e) {
    logMessage("Webhook deployment error: " . $e->getMessage(), 'ERROR');
    sendResponse(['error' => $e->getMessage()], 500);
}
?>
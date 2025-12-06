<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class VirusScanner
{
    private $clamavEnabled = false;
    private $maxFileSize = 50 * 1024 * 1024; // 50MB

    public function __construct()
    {
        // Check if ClamAV is available
        $this->clamavEnabled = $this->isClamAvAvailable();
    }

    /**
     * Scan file for viruses
     * 
     * @param string $filePath - actual file path to scan for viruses
     * @param string|null $originalName - original filename for extension validation (defaults to filePath)
     * @return array ['safe' => bool, 'message' => string, 'threats' => array]
     */
    public function scanFile($filePath, $originalName = null)
    {
        Log::info('=== VIRUS SCAN START ===', [
            'filePath' => $filePath, 
            'originalName' => $originalName,
            'file_exists' => file_exists($filePath)
        ]);
        
        // Use original name for extension check if provided, otherwise use filePath
        $nameForExtCheck = $originalName ?? $filePath;
        
        // Basic validation
        if (!file_exists($filePath)) {
            return [
                'safe' => false,
                'message' => 'File tidak ditemukan',
                'threats' => []
            ];
        }

        $fileSize = filesize($filePath);
        if ($fileSize > $this->maxFileSize) {
            return [
                'safe' => false,
                'message' => 'Ukuran file melebihi batas maksimal (' . ($this->maxFileSize / 1024 / 1024) . 'MB)',
                'threats' => []
            ];
        }

        if ($fileSize === 0) {
            return [
                'safe' => false,
                'message' => 'File kosong',
                'threats' => []
            ];
        }

        // Check file extension (whitelist approach) - use original name or filename
        $extensionCheck = $this->isAllowedExtension($nameForExtCheck);
        
        Log::info('Extension check result', [
            'nameForExtCheck' => $nameForExtCheck,
            'is_allowed' => $extensionCheck
        ]);
        
        if (!$extensionCheck) {
            $detectedExt = pathinfo($nameForExtCheck, PATHINFO_EXTENSION);
            Log::warning('File extension not allowed', [
                'filePath' => $filePath, 
                'nameForExtCheck' => $nameForExtCheck,
                'detected_extension' => $detectedExt
            ]);
            return [
                'safe' => false,
                'message' => "Tipe file tidak diizinkan (.$detectedExt). Format yang diizinkan: .txt, .pdf, .docx, .doc",
                'threats' => []
            ];
        }

        // Try ClamAV scan first if available
        if ($this->clamavEnabled) {
            return $this->scanWithClamAv($filePath);
        }

        // Fallback: basic heuristic checks
        return $this->basicSecurityCheck($filePath);
    }

    /**
     * Scan text content for virus patterns
     * 
     * @param string $text
     * @return array ['safe' => bool, 'message' => string, 'threats' => array]
     */
    public function scanText($text)
    {
        if (empty($text)) {
            return [
                'safe' => true,
                'message' => 'Text kosong, tidak ada ancaman terdeteksi',
                'threats' => []
            ];
        }

        $threats = [];

        // Log the scan attempt
        Log::debug('Scanning text for virus patterns', [
            'text_length' => strlen($text),
            'has_x5o' => strpos($text, 'X5O!P%@AP[4') !== false,
            'has_eicar' => strpos($text, 'EICAR') !== false
        ]);

        // Check for EICAR test string (standard antivirus test)
        if (strpos($text, 'X5O!P%@AP[4') !== false || strpos($text, 'EICAR') !== false) {
            $threats[] = 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE';
            Log::info('EICAR test file detected');
        }

        // Check for PE executable headers (MZ)
        if (strpos($text, 'MZ') === 0) {
            $threats[] = 'PE executable header detected';
        }

        // Check for common malware signatures in text
        if (preg_match('/eval\s*\(|system\s*\(|exec\s*\(|passthru\s*\(|shell_exec\s*\(|proc_open\s*\(/i', $text)) {
            $threats[] = 'Suspicious PHP code detected';
        }

        if (preg_match('/<script[^>]*>.*?<\/script>/is', $text)) {
            $threats[] = 'JavaScript code detected';
        }

        if (empty($threats)) {
            return [
                'safe' => true,
                'message' => 'Text aman: tidak ada pola ancaman terdeteksi',
                'threats' => []
            ];
        } else {
            Log::warning('Virus pattern detected in text', ['threats' => $threats]);
            return [
                'safe' => false,
                'message' => 'Text terindikasi mencurigakan: ' . implode(', ', $threats),
                'threats' => $threats
            ];
        }
    }

    /**
     * Check if ClamAV is available
     */
    private function isClamAvAvailable()
    {
        try {
            $output = @shell_exec('clamdscan --version 2>&1');
            if ($output && strpos($output, 'ClamAV') !== false) {
                Log::info('ClamAV daemon found and available');
                return true;
            }
        } catch (\Exception $e) {
            Log::debug('ClamAV daemon check failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Scan using ClamAV daemon (clamdscan)
     */
    private function scanWithClamAv($filePath)
    {
        try {
            $cmd = 'clamdscan ' . escapeshellarg($filePath) . ' 2>&1';
            $output = @shell_exec($cmd);
            $returnCode = 0;
            $lastLine = trim(exec($cmd, $output, $returnCode));

            Log::debug('ClamAV scan result', [
                'file' => basename($filePath),
                'return_code' => $returnCode,
                'output' => $lastLine
            ]);

            // ClamAV return codes:
            // 0 = clean
            // 1 = virus found
            // 2 = error

            if ($returnCode === 0) {
                return [
                    'safe' => true,
                    'message' => 'File aman: tidak ada virus terdeteksi (ClamAV)',
                    'threats' => []
                ];
            } elseif ($returnCode === 1) {
                // Extract threat name from output
                $threats = $this->extractThreats($lastLine);
                return [
                    'safe' => false,
                    'message' => 'File terindikasi mengandung virus/malware',
                    'threats' => $threats
                ];
            } else {
                Log::warning('ClamAV error during scan', ['return_code' => $returnCode, 'output' => $lastLine]);
                // Fallback to basic check on error
                return $this->basicSecurityCheck($filePath);
            }

        } catch (\Exception $e) {
            Log::warning('ClamAV scan exception', ['error' => $e->getMessage()]);
            return $this->basicSecurityCheck($filePath);
        }
    }

    /**
     * Extract threat names from ClamAV output
     */
    private function extractThreats($output)
    {
        $threats = [];
        // ClamAV format: filename: THREAT_NAME FOUND
        if (preg_match('/:\s*(.+?)\s+FOUND/', $output, $matches)) {
            $threats[] = $matches[1];
        }
        return $threats;
    }

    /**
     * Basic security checks (heuristic)
     */
    private function basicSecurityCheck($filePath)
    {
        $fileName = basename($filePath);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Read first few bytes to check for suspicious patterns
        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            return [
                'safe' => false,
                'message' => 'Tidak dapat membaca file',
                'threats' => []
            ];
        }

        $header = fread($handle, 512);
        fclose($handle);

        $threats = [];

        // Check for EICAR test string (standard antivirus test)
        $content = file_get_contents($filePath);
        if (strpos($content, 'X5O!P%@AP[4') !== false || strpos($content, 'EICAR') !== false) {
            $threats[] = 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE';
        }

        // Check for executable headers in non-executable files
        if ($ext !== 'exe' && $ext !== 'dll') {
            if (strpos($header, 'MZ') === 0) { // PE executable
                $threats[] = 'Possible executable content in ' . $ext . ' file';
            }
        }

        // Check for suspicious macro content in Office files
        if (in_array($ext, ['doc', 'docx', 'xls', 'xlsx'])) {
            $content = file_get_contents($filePath);
            if (preg_match('/vbscript|autoexec|shellexecute|powershell|cmd\.exe/i', $content)) {
                $threats[] = 'Suspicious macro/script detected in ' . strtoupper($ext) . ' file';
            }
        }

        // Check for script injection in PDFs
        if ($ext === 'pdf') {
            $content = @file_get_contents($filePath, false, null, 0, 1024);
            if (preg_match('/\/JavaScript|\/EmbeddedFile|\/ObjStm/i', $content)) {
                $threats[] = 'Suspicious embedded content detected in PDF';
            }
        }

        if (empty($threats)) {
            Log::info('Basic security check passed', ['file' => $fileName]);
            return [
                'safe' => true,
                'message' => 'File aman: lulus pemeriksaan keamanan dasar',
                'threats' => []
            ];
        } else {
            Log::warning('Suspicious patterns detected', ['file' => $fileName, 'threats' => $threats]);
            return [
                'safe' => false,
                'message' => 'File terindikasi mencurigakan: ' . implode(', ', $threats),
                'threats' => $threats
            ];
        }
    }

    /**
     * Check if file extension is allowed
     */
    private function isAllowedExtension($fileName)
    {
        $allowedExtensions = ['txt', 'pdf', 'docx', 'doc'];
        
        // Extract extension - handle various formats
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Clean extension from any non-alphanumeric characters
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        
        // Additional check: try to get extension using different method if first one fails
        if (empty($ext) && strpos($fileName, '.') !== false) {
            $parts = explode('.', $fileName);
            $ext = strtolower(end($parts));
            $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        }
        
        Log::info('=== EXTENSION CHECK ===', [
            'fileName' => $fileName,
            'extracted_extension' => $ext,
            'allowed_extensions' => $allowedExtensions,
            'is_in_array' => in_array($ext, $allowedExtensions)
        ]);
        
        return in_array($ext, $allowedExtensions);
    }

    /**
     * Get available scanning tools info
     */
    public function getInfo()
    {
        return [
            'virus_scanning_enabled' => $this->clamavEnabled,
            'scanner_type' => $this->clamavEnabled ? 'ClamAV' : 'Heuristic',
            'max_file_size' => $this->maxFileSize,
            'supported_formats' => ['txt', 'pdf', 'docx', 'doc']
        ];
    }
}
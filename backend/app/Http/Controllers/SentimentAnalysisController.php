<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Services\VirusScanner;

class SentimentAnalysisController extends Controller
{
    // Health check endpoint
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Laravel API is running',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    // Virus scanner status endpoint
    public function scannerStatus()
    {
        $virusScanner = new VirusScanner();
        $info = $virusScanner->getInfo();

        return response()->json([
            'status' => 'ok',
            'virus_scanning' => $info,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    // Fungsi utama API
    public function analyze(Request $request)
    {
        // Health check dengan parameter khusus
        if ($request->input('text') === 'health-check') {
            return response()->json([
                'status' => 'ok',
                'message' => 'Laravel API is running',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }

        // Handle file upload atau text input
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $text = $this->extractTextFromFile($file);

            Log::channel('user_activity')->info('User uploaded a file', [
                'filename' => $file->getClientOriginalName(),
                'filesize' => $file->getSize(),
                'ip' => $request->ip(),
                'time' => now()->toDateTimeString(),
            ]);

        } else {
            $text = $request->input('text');

            // Check if input is a URL FIRST before virus scanning
            if ($text && $this->isValidUrl($text)) {
                Log::channel('user_activity')->info('User submitted a URL', [
                    'url' => $text,
                    'ip' => $request->ip(),
                    'time' => now()->toDateTimeString(),
                ]);
                
                // Extract content from URL
                $originalUrl = $text;
                $text = $this->extractTextFromUrl($text);
                
                // TAMBAHAN: Log untuk debugging
                Log::info('URL text extraction result', [
                    'url' => $originalUrl,
                    'extracted_length' => strlen($text),
                    'word_count' => str_word_count($text),
                    'preview' => substr($text, 0, 200)
                ]);
                
            } else {
                // Scan text content for virus patterns (for testing/validation)
                if ($text) {
                    $virusScanner = new VirusScanner();
                    $scanResult = $virusScanner->scanText($text);
                    
                    if (!$scanResult['safe']) {
                        Log::warning('Virus pattern detected in text input', [
                            'message' => $scanResult['message'],
                            'threats' => $scanResult['threats']
                        ]);
                        
                        return response()->json([
                            'success' => false,
                            'error' => 'Text tidak aman: ' . $scanResult['message']
                        ], 400);
                    }
                }
                
                Log::channel('user_activity')->info('User submitted text input', [
                    'length' => strlen($text ?? ''),
                    'ip' => $request->ip(),
                    'time' => now()->toDateTimeString(),
                ]);
            }
        }

        if (!$text || strlen(trim($text)) < 10) {
            return response()->json([
                'success' => false,
                'error' => 'Text, URL, or file is required and must contain at least 10 characters'
            ], 400);
        }

        
        // Analisis Keterbacaan (Flesch Reading Ease)
        $readabilityResult = $this->analyzeReadability($text);
        
        // Analisis Sentimen
        $sentimentResult = $this->analyzeSentiment($text);

        // Match sample response: truncate text to 500 chars with ellipsis
        $displayText = strlen($text) > 500 ? substr($text, 0, 500).'...' : $text;

        // return response()->json([
        //     'success' => true,
        //     'text' => $displayText,
        //     'sentiment' => $sentimentResult['sentiment'],
        //     'sentiment_score' => $sentimentResult['score'],
        //     'sentiment_details' => $sentimentResult['details'],
        //     'readability' => $readability,
        //     'readability_category' => $this->getReadabilityCategory($readability),
        //     'word_count' => str_word_count($text),
        //     'sentence_count' => count(preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY))
        // ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Analisis Sentimen
        $sentimentResult = $this->analyzeSentiment($text);

        // Analisis Keterbacaan (Flesch Reading Ease)
        $readabilityResult = $this->analyzeReadability($text);

        // Match sample response: truncate text to 500 chars with ellipsis
        $displayText = strlen($text) > 500 ? substr($text, 0, 500).'...' : $text;

        return response()->json([
            'success' => true,
            'text' => $displayText,

            // Data Sentimen
            'sentiment' => $sentimentResult['sentiment'],
            'sentiment_score' => $sentimentResult['score'],
            'sentiment_scores' => $sentimentResult['sentiment_scores'] ?? [],
            'sentiment_details' => $sentimentResult['details'],

            // Data Keterbacaan
            'readability' => $readabilityResult['score'],
            'readability_category' => $this->getReadabilityCategory($readabilityResult['score']),
            'word_count' => $readabilityResult['word_count'],
            'sentence_count' => $readabilityResult['sentence_count'],
            
            // Statistik Detail Flesch
            'statistics' => [
                'syllable_count' => $readabilityResult['syllable_count'],
                'avg_word_length' => $readabilityResult['avg_word_length'],
                'avg_sentence_length' => $readabilityResult['avg_sentence_length'],
                'complex_word_count' => $readabilityResult['complex_word_count']
            ],

            // Data Tabel
            'entitas_terdeteksi' => $sentimentResult['entitas'] ?? [],
            'tema_terdeteksi' => $sentimentResult['tema'] ?? [],
            'keywords_terdeteksi' => $sentimentResult['keywords'] ?? []
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function extractTextFromFile($file)
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filePath = $file->getRealPath();
        $fileName = $file->getClientOriginalName();

        Log::info('=== FILE UPLOAD RECEIVED ===', [
            'fileName' => $fileName,
            'extension' => $extension,
            'filePath' => $filePath,
            'mimeType' => $file->getMimeType(),
            'fileExists' => file_exists($filePath),
            'fileSize' => $file->getSize()
        ]);

        // Validate file exists and is readable
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \Exception("File tidak dapat diakses atau tidak ditemukan: {$fileName}");
        }

        // Validate file size (max 50MB)
        $maxSize = 50 * 1024 * 1024;
        if (filesize($filePath) > $maxSize) {
            throw new \Exception("Ukuran file terlalu besar. Maksimal 50MB. File Anda: " . round(filesize($filePath) / (1024 * 1024), 2) . "MB");
        }

        // PENTING: Virus scan HARUS menggunakan originalName untuk validasi ekstensi
        $virusScanner = new VirusScanner();
        
        Log::info('=== CALLING VIRUS SCANNER ===', [
            'filePath' => $filePath,
            'originalName' => $fileName
        ]);
        
        $scanResult = $virusScanner->scanFile($filePath, $fileName);

        Log::info('=== VIRUS SCAN RESULT ===', [
            'safe' => $scanResult['safe'],
            'message' => $scanResult['message'],
            'threats' => $scanResult['threats']
        ]);

        if (!$scanResult['safe']) {
            Log::warning('Virus detected in uploaded file', [
                'filename' => $fileName,
                'message' => $scanResult['message'],
                'threats' => $scanResult['threats']
            ]);

            throw new \Exception(
                'File tidak aman: ' . $scanResult['message'] . 
                (!empty($scanResult['threats']) ? ' (' . implode(', ', $scanResult['threats']) . ')' : '')
            );
        }

        Log::info('File passed virus scan', ['filename' => $fileName, 'scanner' => $virusScanner->getInfo()['scanner_type']]);

        // Process based on extension
        if ($extension === 'txt') {
            return $this->extractTextFromTxt($filePath);
        } elseif ($extension === 'pdf') {
            return $this->extractTextFromPdf($filePath, $fileName);
        } elseif ($extension === 'docx') {
            return $this->extractTextFromDocx($filePath, $fileName);
        } elseif ($extension === 'doc') {
            return $this->extractTextFromDoc($filePath, $fileName);
        }

        throw new \Exception("Format file tidak didukung: .{$extension}. Format yang didukung: .txt, .pdf, .docx, .doc");
    }

    private function isValidUrl($text)
    {
        // Check if text looks like a URL
        return filter_var($text, FILTER_VALIDATE_URL) !== false;
    }

    private function extractTextFromUrl($url)
    {
        try {
            // Validate URL
            if (!$this->isValidUrl($url)) {
                throw new \Exception("URL tidak valid: {$url}");
            }

            Log::info("Fetching content from URL", ['url' => $url]);

            // Fetch URL content using Guzzle/Http
            $response = Http::timeout(30) // Increase timeout
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Connection' => 'keep-alive',
                ])
                ->get($url);

            if (!$response->successful()) {
                throw new \Exception("Gagal mengakses URL. Status: " . $response->status());
            }

            $html = $response->body();

            if (empty($html)) {
                throw new \Exception("URL mengembalikan konten kosong.");
            }

            Log::info("HTML fetched successfully", [
                'url' => $url,
                'html_length' => strlen($html)
            ]);

            // Extract text content from HTML
            $text = $this->extractTextFromHtml($html);

            if (empty($text) || strlen(trim($text)) < 50) {
                Log::warning("Extracted text too short, trying aggressive extraction", [
                    'url' => $url,
                    'text_length' => strlen($text)
                ]);
                
                // Try more aggressive extraction
                $text = $this->extractTextAggressively($html);
            }

            if (empty($text) || strlen(trim($text)) < 50) {
                throw new \Exception("Tidak ada teks yang dapat diekstrak dari URL (terlalu pendek).");
            }

            Log::info("Successfully extracted content from URL", [
                'url' => $url,
                'text_length' => strlen($text),
                'word_count' => str_word_count($text)
            ]);

            return $text;

        } catch (\Exception $e) {
            Log::error("URL content extraction failed", [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \Exception("Gagal mengekstrak konten dari URL: " . $e->getMessage());
        }
    }

    private function extractTextFromHtml($html)
    {
        try {
            // Load HTML content
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            
            // Add UTF-8 encoding declaration
            $htmlWithEncoding = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
            @$dom->loadHTML($htmlWithEncoding);
            libxml_clear_errors();

            // Remove script and style elements
            $xpath = new \DOMXPath($dom);
            
            // Remove noise elements
            $noiseSelectors = [
                '//script',
                '//style',
                '//meta',
                '//link',
                '//noscript',
                '//nav',
                '//footer',
                '//header',
                '//aside',
                '//button',
                '//form',
                '//iframe',
                '//*[contains(@class, "ad")]',
                '//*[contains(@class, "advertisement")]',
                '//*[contains(@class, "sidebar")]',
                '//*[contains(@class, "widget")]',
                '//*[contains(@class, "banner")]',
                '//*[contains(@class, "menu")]',
                '//*[contains(@class, "navigation")]',
                '//*[contains(@class, "comment")]',
                '//*[contains(@id, "comment")]',
            ];

            foreach ($noiseSelectors as $selector) {
                foreach ($xpath->query($selector) as $node) {
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }

            // Find main content with priority selectors
            $contentSelectors = [
                '//article',
                '//main',
                '//*[contains(@class, "article-body")]',
                '//*[contains(@class, "article-content")]',
                '//*[contains(@class, "post-content")]',
                '//*[contains(@class, "entry-content")]',
                '//*[contains(@class, "content-body")]',
                '//*[contains(@class, "story-body")]',
                '//*[contains(@class, "article__body")]',
                '//*[@itemprop="articleBody"]',
                '//*[@id="article-body"]',
                '//*[@id="content"]',
                '//*[@id="main"]',
                '//*[@role="main"]',
            ];

            $mainContent = null;
            foreach ($contentSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes->length > 0) {
                    $mainContent = $nodes->item(0);
                    Log::info("Found main content using selector: $selector");
                    break;
                }
            }

            if (!$mainContent) {
                // Fallback to body
                $bodies = $xpath->query('//body');
                $mainContent = $bodies->length > 0 ? $bodies->item(0) : $dom->documentElement;
                Log::info("Using body as main content");
            }

            // Extract text from main content
            $text = $this->getCleanText($mainContent);

            // Additional cleanup
            $text = $this->cleanExtractedText($text);

            $wordCount = str_word_count($text);
            Log::info("Text extraction complete", [
                'word_count' => $wordCount,
                'char_count' => strlen($text)
            ]);

            return $text;

        } catch (\Exception $e) {
            Log::warning("HTML parsing exception", ['error' => $e->getMessage()]);
            
            // Fallback: aggressive strip_tags
            return $this->extractTextAggressively($html);
        }
    }

    private function extractTextAggressively($html)
    {
        // Remove all HTML tags
        $text = strip_tags($html);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        Log::info("Aggressive extraction complete", [
            'length' => strlen($text),
            'word_count' => str_word_count($text)
        ]);
        
        return $text;
    }

    private function getCleanText($element)
    {
        if (!$element) return '';
        
        $text = '';
        
        foreach ($element->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $nodeText = trim($node->textContent);
                if (!empty($nodeText)) {
                    $text .= $nodeText . ' ';
                }
            } elseif ($node->nodeType === XML_ELEMENT_NODE) {
                // Skip certain elements
                if (in_array($node->nodeName, ['script', 'style', 'nav', 'footer', 'aside'])) {
                    continue;
                }
                $text .= $this->getCleanText($node) . ' ';
            }
        }
        
        return trim($text);
    }

    private function cleanExtractedText($text)
    {
        // Remove multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove common noise patterns
        $noisePatterns = [
            '/\bShare\s+this\s+story\b/i',
            '/\bCopy\s+link\b/i',
            '/\bMore\s+from\b/i',
            '/\bSubscribe\b/i',
            '/\bFollow\s+us\b/i',
            '/\bRelated\s+Articles?\b/i',
            '/\bAdvertisement\b/i',
            '/\bSponsored\b/i',
            '/\bRead\s+more\b/i',
            '/\bClick\s+here\b/i',
        ];
        
        foreach ($noisePatterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }
        
        // Final cleanup
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function extractTextAlternative(\DOMDocument $dom, \DOMXPath $xpath)
    {
        // Extract from all paragraphs and divs that likely contain article content
        $content = [];
        
        // Get all p tags
        foreach ($xpath->query('//p') as $p) {
            $text = trim($p->textContent);
            if (strlen($text) > 20) {
                $content[] = $text;
            }
        }
        
        // Get all li tags that are not in navigation
        foreach ($xpath->query('//li') as $li) {
            $text = trim($li->textContent);
            if (strlen($text) > 20 && !$this->isInNav($li)) {
                $content[] = $text;
            }
        }
        
        // Get all divs with substantial content
        foreach ($xpath->query('//div') as $div) {
            $text = trim($div->textContent);
            if (strlen($text) > 100 && strlen($text) < 5000) {
                // Check if it doesn't look like navigation/footer
                if (!$this->isNoise($div)) {
                    $content[] = $text;
                }
            }
        }
        
        $result = implode(' ', $content);
        $result = preg_replace('/\s+/', ' ', $result);
        return trim($result);
    }

    private function isInNav(\DOMElement $element)
    {
        $parent = $element->parentNode;
        for ($i = 0; $i < 5 && $parent; $i++) {
            if ($parent->nodeName === 'nav' || 
                ($parent->nodeName === 'div' && strpos($parent->getAttribute('class'), 'nav') !== false)) {
                return true;
            }
            $parent = $parent->parentNode;
        }
        return false;
    }

    private function isNoise(\DOMElement $element)
    {
        $class = $element->getAttribute('class');
        $id = $element->getAttribute('id');
        
        $noiseKeywords = ['nav', 'footer', 'sidebar', 'ad', 'comment', 'widget', 'banner', 'header'];
        
        foreach ($noiseKeywords as $keyword) {
            if (stripos($class, $keyword) !== false || stripos($id, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function getElementText($element)
    {
        $text = '';
        
        if ($element->nodeType == XML_TEXT_NODE) {
            $text = trim($element->textContent);
        } else {
            foreach ($element->childNodes as $node) {
                $nodeText = $this->getElementText($node);
                if (!empty($nodeText)) {
                    $text .= $nodeText . ' ';
                }
            }
        }
        
        return trim($text);
    }

    private function extractTextFromTxt($filePath)
    {
        $text = @file_get_contents($filePath);
        if ($text === false) {
            throw new \Exception("Gagal membaca file TXT.");
        }
        $text = trim($text);
        if (empty($text)) {
            throw new \Exception("File TXT kosong atau tidak memiliki konten.");
        }
        return $text;
    }

    private function extractTextFromPdf($filePath, $fileName)
    {
        // Try pdftotext first (most reliable)
        if (shell_exec('which pdftotext') || shell_exec('where pdftotext 2>nul')) {
            $output = @shell_exec("pdftotext " . escapeshellarg($filePath) . " -");
            if ($output && strlen(trim($output)) > 0) {
                $output = trim($output);
                if (strlen($output) > 0) {
                    Log::info("PDF extracted successfully using pdftotext", ['file' => $fileName, 'size' => strlen($output)]);
                    return $output;
                }
            }
            Log::warning("pdftotext returned empty output", ['file' => $fileName]);
        }

        // Try pdfgrep as fallback
        if (shell_exec('which pdfgrep') || shell_exec('where pdfgrep 2>nul')) {
            $output = @shell_exec("pdfgrep -a . " . escapeshellarg($filePath));
            if ($output && strlen(trim($output)) > 0) {
                $output = trim($output);
                if (strlen($output) > 0) {
                    Log::info("PDF extracted successfully using pdfgrep", ['file' => $fileName, 'size' => strlen($output)]);
                    return $output;
                }
            }
        }

        // Try pdftk + pdftotext combination
        if (shell_exec('which pdftk') || shell_exec('where pdftk 2>nul')) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'pdf_');
            @shell_exec("pdftk " . escapeshellarg($filePath) . " cat output " . escapeshellarg($tmpFile));
            if (file_exists($tmpFile) && filesize($tmpFile) > 0) {
                $output = @shell_exec("pdftotext " . escapeshellarg($tmpFile) . " -");
                @unlink($tmpFile);
                if ($output && strlen(trim($output)) > 0) {
                    return trim($output);
                }
            }
            @unlink($tmpFile);
        }

        // Final fallback: informative error
        Log::error('PDF extraction failed for all methods', [
            'file' => $fileName,
            'path' => $filePath,
            'pdftotext_available' => (bool)(shell_exec('which pdftotext') || shell_exec('where pdftotext 2>nul')),
            'pdfgrep_available' => (bool)(shell_exec('which pdfgrep') || shell_exec('where pdfgrep 2>nul')),
            'pdftk_available' => (bool)(shell_exec('which pdftk') || shell_exec('where pdftk 2>nul'))
        ]);

        throw new \Exception(
            'Gagal mengekstrak teks dari PDF. Pastikan pdftotext atau utilitas PDF lainnya terinstal di sistem. ' .
            'Untuk Linux/Ubuntu: sudo apt-get install poppler-utils. ' .
            'Untuk Windows: install Xpdf tools atau gunakan WSL. ' .
            'Alternatif: konversi PDF ke TXT terlebih dahulu.'
        );
    }

    private function extractTextFromDocx($filePath, $fileName)
    {
        $xmlContent = null;

        // Strategy 1: PHP ZipArchive (most reliable if available)
        if (class_exists('ZipArchive')) {
            try {
                $zip = new \ZipArchive();
                $openResult = $zip->open($filePath);
                
                if ($openResult === true) {
                    $xmlContent = @$zip->getFromName('word/document.xml');
                    $zip->close();
                    
                    if ($xmlContent !== false && strlen(trim($xmlContent)) > 0) {
                        Log::info("DOCX extracted using ZipArchive", ['file' => $fileName]);
                        return $this->parseDocxXml($xmlContent, $fileName);
                    }
                } else {
                    Log::warning("ZipArchive failed to open DOCX", [
                        'file' => $fileName,
                        'error' => $openResult === false ? 'generic error' : $openResult
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning("ZipArchive exception", ['file' => $fileName, 'error' => $e->getMessage()]);
            }
        } else {
            Log::info("ZipArchive class not available, trying system commands", ['file' => $fileName]);
        }

        // Strategy 2: unzip command (works on Linux/Mac/Windows WSL)
        if (!$xmlContent) {
            $unzipCheck = shell_exec('which unzip 2>/dev/null') ?? shell_exec('where unzip 2>&1');
            if ($unzipCheck && strpos($unzipCheck, 'not found') === false && strpos($unzipCheck, 'could not be found') === false) {
                try {
                    $cmd = "unzip -p " . escapeshellarg($filePath) . " word/document.xml 2>/dev/null";
                    $output = @shell_exec($cmd);
                    
                    if ($output && strlen(trim($output)) > 0) {
                        Log::info("DOCX extracted using unzip", ['file' => $fileName]);
                        $xmlContent = $output;
                        return $this->parseDocxXml($xmlContent, $fileName);
                    }
                } catch (\Exception $e) {
                    Log::warning("unzip extraction failed", ['file' => $fileName, 'error' => $e->getMessage()]);
                }
            }
        }

        // Strategy 3: 7z command (Windows common paths + system PATH)
        if (!$xmlContent) {
            $sevenZipPaths = [
                '"C:\\Program Files\\7-Zip\\7z.exe"',
                '"C:\\Program Files (x86)\\7-Zip\\7z.exe"',
                '7z'  // Try system PATH
            ];
            
            foreach ($sevenZipPaths as $sevenZipPath) {
                try {
                    // Test if 7z is available
                    $testCmd = $sevenZipPath . ' --version';
                    $testOutput = @shell_exec($testCmd . ' 2>&1');
                    
                    if ($testOutput && strlen(trim($testOutput)) > 0) {
                        // 7z found, extract the file
                        $cmd = $sevenZipPath . ' x -so ' . escapeshellarg($filePath) . ' word/document.xml 2>nul';
                        $output = @shell_exec($cmd);
                        
                        if ($output && strlen(trim($output)) > 0) {
                            Log::info("DOCX extracted using 7z", ['file' => $fileName, 'path' => $sevenZipPath]);
                            $xmlContent = $output;
                            return $this->parseDocxXml($xmlContent, $fileName);
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug("7z at $sevenZipPath failed", ['error' => $e->getMessage()]);
                    continue;
                }
            }
        }

        // All strategies failed
        $zipArchiveAvailable = class_exists('ZipArchive');
        $unzipAvailable = (bool)(shell_exec('which unzip 2>/dev/null') ?? shell_exec('where unzip 2>&1'));
        $sevenZAvailable = false;
        
        foreach (['"C:\\Program Files\\7-Zip\\7z.exe"', '"C:\\Program Files (x86)\\7-Zip\\7z.exe"', '7z'] as $path) {
            if (@shell_exec($path . ' --version 2>&1')) {
                $sevenZAvailable = true;
                break;
            }
        }
        
        Log::error('DOCX extraction failed for all strategies', [
            'file' => $fileName,
            'path' => $filePath,
            'ZipArchive_available' => $zipArchiveAvailable,
            'unzip_available' => $unzipAvailable,
            '7z_available' => $sevenZAvailable
        ]);

        throw new \Exception(
            'Gagal mengekstrak teks dari DOCX. Beberapa kemungkinan penyebab: ' .
            '1) Enable PHP "zip" extension (php-zip). ' .
            '2) Install sistem utilities: unzip atau 7z. ' .
            'Untuk Linux/Ubuntu: sudo apt-get install unzip p7zip-full. ' .
            'Untuk Windows: Install 7-Zip atau gunakan WSL. ' .
            'Alternatif: konversi DOCX ke TXT terlebih dahulu.'
        );
    }

    private function parseDocxXml($xmlContent, $fileName)
    {
        if (empty($xmlContent)) {
            throw new \Exception("Konten XML DOCX kosong atau tidak dapat dibaca.");
        }

        try {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            
            $loadResult = @$dom->loadXML($xmlContent);
            $errors = libxml_get_errors();
            libxml_clear_errors();
            
            if (!$loadResult) {
                Log::warning("XML parsing had errors", [
                    'file' => $fileName,
                    'errors' => count($errors) > 0 ? $errors[0]->message : 'unknown'
                ]);
                // Continue anyway, we might still extract some text
            }

            $textNodes = $dom->getElementsByTagName('t');
            $text = '';
            $nodeCount = 0;

            foreach ($textNodes as $node) {
                $text .= $node->nodeValue . ' ';
                $nodeCount++;
            }

            // Clean up text
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            if (empty($text)) {
                throw new \Exception("Tidak ada teks yang dapat diekstrak dari DOCX.");
            }

            Log::info("DOCX text extracted successfully", [
                'file' => $fileName,
                'text_length' => strlen($text),
                'text_nodes' => $nodeCount
            ]);

            return $text;

        } catch (\Exception $e) {
            Log::error("XML parsing exception", [
                'file' => $fileName,
                'error' => $e->getMessage()
            ]);

            // Fallback: simple strip_tags
            try {
                $text = strip_tags($xmlContent);
                $text = html_entity_decode($text);
                $text = preg_replace('/\s+/', ' ', $text);
                $text = trim($text);

                if (!empty($text)) {
                    Log::info("DOCX text extracted using fallback strip_tags", ['file' => $fileName]);
                    return $text;
                }
            } catch (\Exception $stripException) {
                Log::error("Fallback strip_tags also failed", ['error' => $stripException->getMessage()]);
            }

            throw new \Exception("Gagal memproses konten XML DOCX: " . $e->getMessage());
        }
    }

    // Perbarui fungsi analyzeSentiment secara keseluruhan
    private function analyzeSentiment($text, $useSenopati = true)
    {
        // Jika memilih untuk menggunakan Senopati
        if ($useSenopati) {
            return $this->analyzeSentimentSenopati($text);
        }

        $apiKey = \Illuminate\Support\Facades\Config::get('app.gemini_api_key') ?? $_ENV['GEMINI_API_KEY'] ?? '';
        $apiUrl = \Illuminate\Support\Facades\Config::get('app.gemini_api_url') ?? $_ENV['GEMINI_API_URL'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent';

        // PROMPT JSON LENGKAP - ENHANCED
        $prompt = "Analisis MENDALAM sentimen, entitas, dan tema dari berita berikut: '{$text}'. " .
                  "Hasilkan output HANYA dalam format JSON valid. Jangan ada teks atau penjelasan lain di luar objek JSON. " .
                  "JSON HARUS memiliki kunci-kunci berikut: 'sentiment', 'score' (-1.0 hingga +1.0), 'sentiment_scores', 'details', 'entitas', 'keywords', dan 'tema'. " .
                  "PENTING: Deteksi MINIMAL 5-10 entitas, 5-10 keywords, dan 5-10 tema (jangan hanya 2-3). Prioritaskan berdasarkan relevansi dan frekuensi kemunculan dalam teks. " .
                  "Untuk 'sentiment_scores': object dengan keys 'positive', 'neutral', 'negative' (masing-masing 0.0-1.0, jumlah total ~1.0). " .
                  "Untuk 'entitas' (Named Entities seperti nama orang, tempat, organisasi, dll): array of objects dengan 'nama', 'magnitudo' (0.0-1.0 berdasarkan frekuensi/pentingnya), 'skor_sentimen' (-1.0 hingga +1.0). " .
                  "Untuk 'keywords' (kata/frasa yang membawa polaritas emosional atau opini): array of objects dengan 'nama', 'magnitudo' (0.0-1.0 berdasarkan beban semantik), 'skor_sentimen' (-1.0 hingga +1.0). " .
                  "Untuk 'tema' (topik/subject utama yang dibicarakan): array of objects dengan 'nama', 'magnitudo' (0.0-1.0 berdasarkan dominasi di teks), 'skor_sentimen' (-1.0 hingga +1.0). " .
                  "Untuk 'score': rata-rata tertimbang dari 'sentiment_scores' dikonversi ke -1.0 hingga +1.0. " .
                  "Untuk 'sentiment': 'positive' jika score > 0.25, 'negative' jika score < -0.25, 'neutral' sebaliknya. " .
                  "Untuk 'details': WAJIB tulis RINGKAS DALAM BAHASA INDONESIA yang menjelaskan alasan di balik penilaian sentimen. JANGAN gunakan bahasa Inggris sama sekali di bagian ini, termasuk jika teks asli berbahasa Inggris. Terjemahkan konsepnya ke Bahasa Indonesia yang baik dan benar. " .
                  "Contoh Skema JSON: { \"sentiment\": \"string\", \"score\": 0.0, \"sentiment_scores\": { \"positive\": 0.0, \"neutral\": 0.0, \"negative\": 0.0 }, \"details\": \"string\", \"entitas\": [ { \"nama\": \"string\", \"magnitudo\": 0.0, \"skor_sentimen\": 0.0 } ], \"keywords\": [ { \"nama\": \"string\", \"magnitudo\": 0.0, \"skor_sentimen\": 0.0 } ], \"tema\": [ { \"nama\": \"string\", \"magnitudo\": 0.0, \"skor_sentimen\": 0.0 } ] }";

        try {
            $response = Http::timeout(30)->post("{$apiUrl}?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if (!$response->successful()) {
                // Tambahkan log ini:
                \Illuminate\Support\Facades\Log::error('GEMINI API CALL FAILED:', [
                    'status' => $response->status(), 
                    'body' => $response->body() // Catat body untuk melihat error dari Gemini
                ]);
                
                return $this->simpleSentimentAnalysis($text, true); 
            }

            if ($response->successful()) {
                $result = $response->json();
                $geminiText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
                
                $geminiData = json_decode($geminiText, true);

                if (json_last_error() !== JSON_ERROR_NONE || !is_array($geminiData)) {
                    // Fallback jika parsing JSON gagal
                    return $this->simpleSentimentAnalysis($text, true); // true = minta data lengkap (entitas/tema kosong)
                }

                return [
                    'sentiment' => $geminiData['sentiment'] ?? 'Neutral',
                    'score' => $geminiData['score'] ?? 0.5,
                    'sentiment_scores' => $geminiData['sentiment_scores'] ?? ['positive' => 0, 'neutral' => 0, 'negative' => 0],
                    'details' => $geminiData['details'] ?? 'Analisis detail tidak tersedia.',
                    'entitas' => $geminiData['entitas'] ?? [],
                    'tema' => $geminiData['tema'] ?? [],
                    'keywords' => $geminiData['keywords'] ?? []
                ];

            } else {
                return $this->simpleSentimentAnalysis($text, true); 
            }
        } catch (\Exception $e) {
            return $this->simpleSentimentAnalysis($text, true);
        }
    }


    // private function parseGeminiResponse($geminiText)
    // {
    //     // Ekstrak sentimen dari response Gemini
    //     if (stripos($geminiText, 'positif') !== false) {
    //         $sentiment = 'Positive';
    //         $score = 0.75;
    //     } elseif (stripos($geminiText, 'negatif') !== false) {
    //         $sentiment = 'Negative';
    //         $score = 0.25;
    //     } else {
    //         $sentiment = 'Neutral';
    //         $score = 0.5;
    //     }

    //     return [
    //         'sentiment' => $sentiment,
    //         'score' => $score,
    //         'details' => $geminiText
    //     ];
    // }

  
    private function simpleSentimentAnalysis($text, $fullData = false)
    {
        // Fallback sederhana jika Gemini error
        $positiveWords = ['baik', 'bagus', 'hebat', 'senang', 'sukses', 'positif', 'maju', 'unggul', 'meningkat', 'berkembang'];
        $negativeWords = ['buruk', 'jelek', 'gagal', 'sedih', 'negatif', 'mundur', 'korupsi', 'menurun', 'rugi'];

        $text = strtolower($text);
        $positiveCount = 0;
        $negativeCount = 0;

        foreach ($positiveWords as $word) {
            $positiveCount += substr_count($text, $word);
        }

        foreach ($negativeWords as $word) {
            $negativeCount += substr_count($text, $word);
        }

        if ($positiveCount > $negativeCount) {
            $result = ['sentiment' => 'Positive', 'score' => 0.7, 'details' => 'Analisis fallback: ditemukan kata positif'];
        } elseif ($negativeCount > $positiveCount) {
            $result = ['sentiment' => 'Negative', 'score' => 0.3, 'details' => 'Analisis fallback: ditemukan kata negatif'];
        } else {
            $result = ['sentiment' => 'Neutral', 'score' => 0.5, 'details' => 'Analisis fallback: netral (jumlah kata positif/negatif seimbang atau tidak ada)'];
        }

        // BAGIAN KRITIS: Menambahkan kunci Entitas, Tema, Keywords, dan sentiment_scores kosong saat diminta data lengkap
        if ($fullData) {
            $result['entitas'] = [];
            $result['tema'] = [];
            $result['keywords'] = [];
            $result['sentiment_scores'] = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        }
        
        return $result;
    }

    private function analyzeReadability($text)
    {
        return $this->fleschReadingEase($text);
    }

    private function fleschReadingEase($text)
    {
        $words = str_word_count($text, 1);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = max(1, count($words));
        $sentenceCount = max(1, count($sentences));
        $syllableCount = 0;
        $totalCharLength = 0;
        $complexWordCount = $this->countComplexWords($text);

        foreach ($words as $word) {
            $syllableCount += $this->countSyllables($word);
            $totalCharLength += strlen($word);
        }

        $avgWordLength = $wordCount > 0 ? round($totalCharLength / $wordCount, 2) : 0;
        $avgSentenceLength = $sentenceCount > 0 ? round($wordCount / $sentenceCount, 2) : 0;

        $score = 206.835
             - (1.015 * ($wordCount / $sentenceCount))
             - (84.6 * ($syllableCount / $wordCount));

        return [
            'score' => round($score, 2),
            'word_count' => $wordCount,
            'sentence_count' => $sentenceCount,
            'syllable_count' => $syllableCount, // BARU
            'avg_word_length' => $avgWordLength, // BARU
            'avg_sentence_length' => $avgSentenceLength, // BARU
            'complex_word_count' => $complexWordCount // BARU
        ];
    }
    // Hapus atau abaikan fungsi analyzeReadability yang lama jika masih ada.

    private function getReadabilityCategory($score)
    {
        if ($score >= 90) return 'Sangat Mudah';
        if ($score >= 80) return 'Mudah';
        if ($score >= 70) return 'Cukup Mudah';
        if ($score >= 60) return 'Standar';
        if ($score >= 50) return 'Cukup Sulit';
        if ($score >= 30) return 'Sulit';
        return 'Sangat Sulit';
    }

    private function countSyllables($word)
    {
        $vowels = ['a', 'e', 'i', 'o', 'u', 'y'];
        $syllables = 0;
        $previousCharIsVowel = false;

        for ($i = 0; $i < strlen($word); $i++) {
            $char = strtolower($word[$i]);
            if (in_array($char, $vowels)) {
                if (!$previousCharIsVowel) {
                    $syllables++;
                }
                $previousCharIsVowel = true;
            } else {
                $previousCharIsVowel = false;
            }
        }

        return max(1, $syllables);
    }

    private function countComplexWords($text, $minSyllables = 3)
    {
        $words = str_word_count($text, 1);
        $count = 0;

        foreach ($words as $word) {
            // Hapus karakter non-alfabet di awal/akhir kata
            $clean = trim($word, "\"'()[]{}.,;:!?-—–\n\r\t ");
            if ($clean === '') continue;

            $syllables = $this->countSyllables($clean);
            if ($syllables >= $minSyllables) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Analisis menggunakan Senopati AI (fallback atau alternatif dari Gemini).
     * 
     * @param string $text
     * @return array
     */
    private function analyzeSentimentSenopati($text)
    {
        $apiUrl = 'https://senopati.its.ac.id/senopati-lokal-dev/generate';

        $prompt = "Analisis MENDALAM sentimen, entitas, dan tema dari berita berikut: '{$text}'. " .
                  "Hasilkan output HANYA dalam format JSON valid. Jangan ada teks atau penjelasan lain di luar objek JSON. " .
                  "JSON HARUS memiliki kunci-kunci berikut: 'sentiment', 'score' (-1.0 hingga +1.0), 'sentiment_scores', 'details', 'entitas', 'keywords', dan 'tema'. " .
                  "PENTING: Deteksi MINIMAL 5-10 entitas, 5-10 keywords, dan 5-10 tema (jangan hanya 2-3). Prioritaskan berdasarkan relevansi dan frekuensi kemunculan dalam teks. " .
                  "Untuk 'sentiment_scores': object dengan keys 'positive', 'neutral', 'negative' (masing-masing 0.0-1.0, jumlah total ~1.0). " .
                  "Untuk 'entitas' (Named Entities seperti nama orang, tempat, organisasi, dll): array of objects dengan 'nama', 'magnitudo' (0.0-1.0 berdasarkan frekuensi/pentingnya), 'skor_sentimen' (-1.0 hingga +1.0). " .
                  "Untuk 'keywords' (kata/frasa yang membawa polaritas emosional atau opini): array of objects dengan 'nama', 'magnitudo' (0.0-1.0 berdasarkan beban semantik), 'skor_sentimen' (-1.0 hingga +1.0). " .
                  "Untuk 'tema' (topik/subject utama yang dibicarakan): array of objects dengan 'nama', 'magnitudo' (0.0-1.0 berdasarkan dominasi di teks), 'skor_sentimen' (-1.0 hingga +1.0). " .
                  "Untuk 'score': rata-rata tertimbang dari 'sentiment_scores' dikonversi ke -1.0 hingga +1.0. " .
                  "Untuk 'sentiment': 'positive' jika score > 0.25, 'negative' jika score < -0.25, 'neutral' sebaliknya. " .
                  "Untuk 'details': WAJIB tulis RINGKAS DALAM BAHASA INDONESIA (minimal 2-3 kalimat) yang menjelaskan alasan di balik penilaian sentimen. JANGAN gunakan bahasa Inggris sama sekali di bagian ini, termasuk jika teks asli berbahasa Inggris. Terjemahkan konsepnya ke Bahasa Indonesia yang baik dan benar. " .
                  "Contoh Skema JSON: { \"sentiment\": \"string\", \"score\": 0.0, \"sentiment_scores\": { \"positive\": 0.0, \"neutral\": 0.0, \"negative\": 0.0 }, \"details\": \"string\", \"entitas\": [ { \"nama\": \"string\", \"magnitudo\": 0.0, \"skor_sentimen\": 0.0 } ], \"keywords\": [ { \"nama\": \"string\", \"magnitudo\": 0.0, \"skor_sentimen\": 0.0 } ], \"tema\": [ { \"nama\": \"string\", \"magnitudo\": 0.0, \"skor_sentimen\": 0.0 } ] }";

        try {
            $payload = [
                'model' => 'qwen2.5:14b',
                'prompt' => $prompt,
                'stream' => false
            ];

            $response = Http::timeout(60)->post($apiUrl, $payload);

            if (!$response->successful()) {
                Log::error('SENOPATI API CALL FAILED:', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return $this->simpleSentimentAnalysis($text, true);
            }

            $result = $response->json();
            $responseText = $result['response'] ?? '';

            // Bersihkan JSON dari markdown code block
            if (strpos($responseText, '```json') !== false) {
                $responseText = preg_replace('/```json/', '', $responseText);
                $responseText = preg_replace('/```/', '', $responseText);
            }

            $analysisData = json_decode(trim($responseText), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($analysisData)) {
                Log::warning('SENOPATI JSON PARSE FAILED:', [
                    'raw_response' => $responseText,
                    'json_error' => json_last_error_msg()
                ]);
                return $this->simpleSentimentAnalysis($text, true);
            }

            return [
                'sentiment' => $analysisData['sentiment'] ?? 'Neutral',
                'score' => $analysisData['score'] ?? 0.5,
                'sentiment_scores' => $analysisData['sentiment_scores'] ?? [
                    'positive' => 0.0,
                    'neutral' => 0.0,
                    'negative' => 0.0
                ],
                'details' => $analysisData['details'] ?? 'Analisis detail tidak tersedia.',
                'entitas' => $analysisData['entitas'] ?? [],
                'tema' => $analysisData['tema'] ?? [],
                'keywords' => $analysisData['keywords'] ?? []
            ];

        } catch (\Exception $e) {
            Log::error('SENOPATI API EXCEPTION:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->simpleSentimentAnalysis($text, true);
        }
    }

    private function extractTextFromDoc($filePath, $fileName)
    {
        try {
            // For .doc files (old MS Word binary format), we'll use strings command or PHP
            // Strategy 1: Try to extract using php-word if available or system command
            
            // Try using `strings` command to extract readable text (cross-platform)
            if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
                $stringsCmd = "strings " . escapeshellarg($filePath);
                $output = @shell_exec($stringsCmd);
                
                if ($output && strlen(trim($output)) > 0) {
                    Log::info("DOC text extracted using strings command", ['file' => $fileName]);
                    // Filter out binary garbage and extract meaningful text
                    $lines = explode("\n", $output);
                    $textLines = [];
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        // Keep lines that are mostly printable ASCII and have reasonable length
                        if (strlen($line) >= 5 && strlen($line) <= 200 && preg_match('/^[\x20-\x7E]+$/', $line)) {
                            $textLines[] = $line;
                        }
                    }
                    
                    if (!empty($textLines)) {
                        return implode(" ", $textLines);
                    }
                }
            }

            // Strategy 2: Read file as binary and extract readable strings manually
            $fileContent = file_get_contents($filePath);
            
            if ($fileContent === false) {
                throw new \Exception("Tidak dapat membaca file .doc");
            }

            // Extract readable text from binary content (simple approach)
            $text = '';
            $readableChars = '';
            
            for ($i = 0; $i < strlen($fileContent); $i++) {
                $byte = ord($fileContent[$i]);
                
                if (($byte >= 32 && $byte <= 126) || $byte === 10 || $byte === 13) {
                    // Printable ASCII or newline/carriage return
                    $readableChars .= chr($byte);
                } else {
                    if (strlen($readableChars) > 4) {
                        $text .= " " . trim($readableChars);
                    }
                    $readableChars = '';
                }
            }
            
            if (strlen($readableChars) > 4) {
                $text .= " " . trim($readableChars);
            }
            
            $text = preg_replace('/\s+/', ' ', trim($text));
            
            if (strlen($text) < 10) {
                throw new \Exception("File .doc tidak dapat diekstrak atau berisi teks yang terlalu sedikit");
            }
            
            Log::info("DOC text extracted by parsing binary", ['file' => $fileName, 'length' => strlen($text)]);
            return $text;
            
        } catch (\Exception $e) {
            Log::warning("Failed to extract text from .doc file", [
                'file' => $fileName,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception("Gagal mengekstrak teks dari file .doc: " . $e->getMessage());
        }
    }
}


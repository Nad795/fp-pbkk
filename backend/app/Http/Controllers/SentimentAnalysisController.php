<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

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

            // Check if input is a URL
            if ($text && $this->isValidUrl($text)) {
                Log::channel('user_activity')->info('User submitted a URL', [
                    'url' => $text,
                    'ip' => $request->ip(),
                    'time' => now()->toDateTimeString(),
                ]);
                
                // Extract content from URL
                $text = $this->extractTextFromUrl($text);
            } else {
                Log::channel('user_activity')->info('User submitted text input', [
                    'length' => strlen($text ?? ''),
                    'ip' => $request->ip(),
                    'time' => now()->toDateTimeString(),
                ]);
            }
        }

        if (!$text) {
            return response()->json([
                'success' => false,
                'error' => 'Text, URL, or file is required'
            ], 400);
        }

        // Analisis Sentimen
        $sentimentResult = $this->analyzeSentiment($text);

        // Analisis Keterbacaan (Flesch Reading Ease)
        $readabilityResult = $this->analyzeReadability($text);

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
            
            // Statistik Detail Flesch (Sesuai FleschStatistics di api.ts)
            'statistics' => [
                'syllable_count' => $readabilityResult['syllable_count'],
                'avg_word_length' => $readabilityResult['avg_word_length'],
                'avg_sentence_length' => $readabilityResult['avg_sentence_length'],
                'complex_word_count' => $readabilityResult['complex_word_count']
            ],

            // Data Tabel (Sesuai EntityThemeData[] di api.ts)
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

        // Validate file exists and is readable
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \Exception("File tidak dapat diakses atau tidak ditemukan: {$fileName}");
        }

        // Validate file size (max 50MB)
        $maxSize = 50 * 1024 * 1024;
        if (filesize($filePath) > $maxSize) {
            throw new \Exception("Ukuran file terlalu besar. Maksimal 50MB. File Anda: " . round(filesize($filePath) / (1024 * 1024), 2) . "MB");
        }

        if ($extension === 'txt') {
            return $this->extractTextFromTxt($filePath);
        } elseif ($extension === 'pdf') {
            return $this->extractTextFromPdf($filePath, $fileName);
        } elseif ($extension === 'docx') {
            return $this->extractTextFromDocx($filePath, $fileName);
        }

        throw new \Exception("Format file tidak didukung: .{$extension}. Format yang didukung: .txt, .pdf, .docx");
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
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ])
                ->get($url);

            if (!$response->successful()) {
                throw new \Exception("Gagal mengakses URL. Status: " . $response->status());
            }

            $html = $response->body();

            if (empty($html)) {
                throw new \Exception("URL mengembalikan konten kosong.");
            }

            // Extract text content from HTML
            $text = $this->extractTextFromHtml($html);

            if (empty($text)) {
                throw new \Exception("Tidak ada teks yang dapat diekstrak dari URL.");
            }

            Log::info("Successfully extracted content from URL", [
                'url' => $url,
                'text_length' => strlen($text)
            ]);

            return $text;

        } catch (\Exception $e) {
            Log::error("URL content extraction failed", [
                'url' => $url,
                'error' => $e->getMessage()
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
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            libxml_clear_errors();

            // Remove script and style elements
            $xpath = new \DOMXPath($dom);
            
            // Remove script tags
            foreach ($xpath->query('//script') as $node) {
                $node->parentNode->removeChild($node);
            }
            
            // Remove style tags
            foreach ($xpath->query('//style') as $node) {
                $node->parentNode->removeChild($node);
            }
            
            // Remove meta, link, noscript, nav, footer (noise elements)
            foreach ($xpath->query('//meta | //link | //noscript | //nav | //footer | //header | //aside | //button | //form') as $node) {
                $node->parentNode->removeChild($node);
            }

            // Remove advertisement and common ad divs
            foreach ($xpath->query('//*[@class and (contains(@class, "ad") or contains(@class, "advertisement") or contains(@class, "sidebar") or contains(@class, "widget") or contains(@class, "banner"))]') as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }

            // Try to find main content - look for article, main, or content tags
            $mainContent = null;
            
            // Priority selectors - aggressive approach to find actual article content
            $selectors = [
                '//article',
                '//main',
                '//*[@class and contains(@class, "article")]',
                '//*[@class and contains(@class, "post-content")]',
                '//*[@class and contains(@class, "entry-content")]',
                '//*[@class and contains(@class, "content")]',
                '//*[@class and contains(@class, "story-body")]',
                '//*[@id="content"]',
                '//*[@id="main"]',
                '//*[@role="main"]',
                '//div[@class and contains(@class, "article-content")]',
                '//section[@class and contains(@class, "content")]',
                '//body'
            ];

            foreach ($selectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes->length > 0) {
                    $mainContent = $nodes->item(0);
                    Log::debug("Found main content using selector: $selector");
                    break;
                }
            }

            // If no main content found, use body
            if (!$mainContent) {
                $bodies = $xpath->query('//body');
                $mainContent = $bodies->length > 0 ? $bodies->item(0) : $dom->documentElement;
            }

            // Extract ALL text from main content - aggressive approach
            $text = $this->getElementText($mainContent);

            if (empty($text)) {
                // Fallback: extract all p tags
                $paragraphs = $xpath->query('//p');
                $text = '';
                foreach ($paragraphs as $p) {
                    $text .= trim($p->textContent) . ' ';
                }
            }

            // Clean up text
            $text = trim($text);
            
            // Replace multiple spaces with single space
            $text = preg_replace('/\s+/', ' ', $text);
            
            // Remove common noise patterns (but more carefully)
            $noisePatterns = [
                '/\bShare this story\b/i',
                '/\bCopy link\b/i',
                '/\bMore from\b/i',
                '/\bSubscribe\b/i',
                '/\bFollow us\b/i',
                '/\bRelated Articles?\b/i',
                '/\bAdvertisement\b/i',
                '/\bSponsored\b/i',
                '/\bBy .{0,50}Updated/i',
                '/\bBy .{0,50}at .{0,50}(UTC|AM|PM)/i',
            ];
            
            foreach ($noisePatterns as $pattern) {
                $text = preg_replace($pattern, '', $text);
            }
            
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            // Ensure we have substantial content
            $wordCount = str_word_count($text);
            Log::info("Extracted HTML text", [
                'word_count' => $wordCount,
                'char_count' => strlen($text),
                'first_100_chars' => substr($text, 0, 100)
            ]);

            if ($wordCount < 50) {
                Log::warning("Extracted text seems too short, trying alternative method");
                // Try alternative: extract all text that's not in small elements
                $text = $this->extractTextAlternative($dom, $xpath);
            }

            return $text;

        } catch (\Exception $e) {
            Log::warning("HTML parsing exception", ['error' => $e->getMessage()]);
            
            // Fallback: aggressive strip_tags
            $text = strip_tags($html);
            $text = html_entity_decode($text);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);
            
            return $text;
        }
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
        if (!$xmlContent && (shell_exec('which unzip') || shell_exec('where unzip 2>nul'))) {
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

        // Strategy 3: 7z command (works on most systems)
        if (!$xmlContent && (shell_exec('which 7z') || shell_exec('where 7z 2>nul'))) {
            try {
                $cmd = "7z x -so " . escapeshellarg($filePath) . " word/document.xml 2>/dev/null";
                $output = @shell_exec($cmd);
                
                if ($output && strlen(trim($output)) > 0) {
                    Log::info("DOCX extracted using 7z", ['file' => $fileName]);
                    $xmlContent = $output;
                    return $this->parseDocxXml($xmlContent, $fileName);
                }
            } catch (\Exception $e) {
                Log::warning("7z extraction failed", ['file' => $fileName, 'error' => $e->getMessage()]);
            }
        }

        // All strategies failed
        Log::error('DOCX extraction failed for all strategies', [
            'file' => $fileName,
            'path' => $filePath,
            'ZipArchive_available' => class_exists('ZipArchive'),
            'unzip_available' => (bool)(shell_exec('which unzip') || shell_exec('where unzip 2>nul')),
            '7z_available' => (bool)(shell_exec('which 7z') || shell_exec('where 7z 2>nul'))
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
}

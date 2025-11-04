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

            Log::channel('user_activity')->info('User submitted text input', [
                'length' => strlen($text ?? ''),
                'ip' => $request->ip(),
                'time' => now()->toDateTimeString(),
            ]);
        }

        if (!$text) {
            return response()->json([
                'success' => false,
                'error' => 'Text or file is required'
            ], 400);
        }

        // Analisis Sentimen (Gemini API)
        $sentimentResult = $this->analyzeSentiment($text);

        // Analisis Keterbacaan (Flesch Reading Ease)
        $readability = $this->analyzeReadability($text);

        // Match sample response: truncate text to 500 chars with ellipsis
        $displayText = strlen($text) > 500 ? substr($text, 0, 500).'...' : $text;

        return response()->json([
            'success' => true,
            'text' => $displayText,
            'sentiment' => $sentimentResult['sentiment'],
            'sentiment_score' => $sentimentResult['score'],
            'sentiment_details' => $sentimentResult['details'],
            'readability' => $readability,
            'readability_category' => $this->getReadabilityCategory($readability),
            'word_count' => str_word_count($text),
            'sentence_count' => count(preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY))
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function extractTextFromFile($file)
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filePath = $file->getRealPath();

        if ($extension === 'txt') {
            return file_get_contents($filePath);
        } elseif ($extension === 'pdf') {
            // Extract text from PDF using pdftotext
            if (shell_exec('which pdftotext')) {
                $output = shell_exec("pdftotext '$filePath' -");
                if ($output && strlen(trim($output)) > 0) {
                    return trim($output);
                }
            }
            throw new \Exception('Unable to extract text from PDF. Please try converting to .txt format first.');
        } elseif ($extension === 'docx') {
            // Extract text from DOCX using ZipArchive
            if (!class_exists('ZipArchive')) {
                throw new \Exception('ZipArchive class not available for DOCX processing.');
            }
            
            $zip = new \ZipArchive;
            if ($zip->open($filePath) !== true) {
                throw new \Exception('Unable to open DOCX file.');
            }
            
            $xmlContent = $zip->getFromName('word/document.xml');
            $zip->close();
            
            if ($xmlContent === false) {
                throw new \Exception('Unable to extract content from DOCX file.');
            }
            
            // Parse XML to extract text
            try {
                $dom = new \DOMDocument();
                $dom->loadXML($xmlContent);
                
                $textNodes = $dom->getElementsByTagName('t');
                $text = '';
                
                foreach ($textNodes as $node) {
                    $text .= $node->nodeValue . ' ';
                }
                
                // Clean up text
                $text = preg_replace('/\s+/', ' ', $text);
                return trim($text);
                
            } catch (\Exception $e) {
                // Fallback: simple strip_tags
                $text = strip_tags($xmlContent);
                $text = html_entity_decode($text);
                $text = preg_replace('/\s+/', ' ', $text);
                return trim($text);
            }
        }

        throw new \Exception('Supported file formats: .txt, .pdf, and .docx');
    }

    private function analyzeSentiment($text)
    {
        $apiKey = \Illuminate\Support\Facades\Config::get('app.gemini_api_key') ?? $_ENV['GEMINI_API_KEY'] ?? '';
        $apiUrl = \Illuminate\Support\Facades\Config::get('app.gemini_api_url') ?? $_ENV['GEMINI_API_URL'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent';

        // Prompt untuk Gemini
        $prompt = "Analisis sentimen dari berita berikut: '{$text}'. " .
                  "Apakah sentimennya positif, negatif, atau netral? " .
                  "Berikan alasannya dan highlight kata-kata penyebabnya. " .
                  "Format jawaban: Sentimen: [Positif/Negatif/Netral], Skor: [0-1], Alasan: [penjelasan], Kata Kunci: [kata-kata penting]";

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

            if ($response->successful()) {
                $result = $response->json();
                $geminiText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

                // Parse response Gemini
                return $this->parseGeminiResponse($geminiText);
            } else {
                // Fallback ke analisis sederhana
                return $this->simpleSentimentAnalysis($text);
            }
        } catch (\Exception $e) {
            // Fallback jika Gemini error
            return $this->simpleSentimentAnalysis($text);
        }
    }

    private function parseGeminiResponse($geminiText)
    {
        // Ekstrak sentimen dari response Gemini
        if (stripos($geminiText, 'positif') !== false) {
            $sentiment = 'Positive';
            $score = 0.75;
        } elseif (stripos($geminiText, 'negatif') !== false) {
            $sentiment = 'Negative';
            $score = 0.25;
        } else {
            $sentiment = 'Neutral';
            $score = 0.5;
        }

        return [
            'sentiment' => $sentiment,
            'score' => $score,
            'details' => $geminiText
        ];
    }

    private function simpleSentimentAnalysis($text)
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
            return ['sentiment' => 'Positive', 'score' => 0.7, 'details' => 'Analisis fallback: ditemukan kata positif'];
        } elseif ($negativeCount > $positiveCount) {
            return ['sentiment' => 'Negative', 'score' => 0.3, 'details' => 'Analisis fallback: ditemukan kata negatif'];
        } else {
            return ['sentiment' => 'Neutral', 'score' => 0.5, 'details' => 'Analisis fallback: netral'];
        }
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

        foreach ($words as $word) {
            $syllableCount += $this->countSyllables($word);
        }

        $score = 206.835
             - (1.015 * ($wordCount / $sentenceCount))
             - (84.6 * ($syllableCount / $wordCount));

        return round($score, 2);
    }

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
}

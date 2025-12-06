# Virus Scanning Test Guide

## Safe Testing Methods

### 1. **EICAR Test String** (Industri Standard)
EICAR (European Institute for Computer Antivirus Research) menyediakan test string yang aman namun akan dideteksi oleh semua antivirus:

```
X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*
```

**Keamanan**: 100% aman - hanya string text, bukan executable
**Deteksi**: Semua antivirus modern akan mendeteksinya sebagai "EICAR-AVTEST"

### 2. **Create EICAR Test File (Windows PowerShell)**

```powershell
# Buat file EICAR test
$eicarString = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*'
$eicarString | Out-File -FilePath "C:\path\to\eicar.txt" -Encoding ASCII -NoNewline

# Atau one-liner
echo 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*' | Out-File "eicar.txt" -Encoding ASCII -NoNewline
```

### 3. **Create EICAR Test in Document Files**

#### PDF dengan EICAR string
```bash
# Di dalam container atau Linux
echo '%PDF-1.4
1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj
3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Resources<<>>>>endobj
xref
0 4
0000000000 65535 f
0000000009 00000 n
0000000058 00000 n
0000000115 00000 n
trailer<</Size 4/Root 1 0 R>>
startxref
190
%%EOF
X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*' > eicar.pdf
```

#### DOCX dengan macro signature
```bash
# Create minimal DOCX dengan suspicious pattern
mkdir -p test_docx/word
echo '<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r>
        <w:t>Test document with suspicious content</w:t>
      </w:r>
    </w:p>
  </w:body>
</w:document>' > test_docx/word/document.xml

# Add vbscript reference (akan dideteksi sebagai suspicious)
echo 'VBScript.Shell' >> test_docx/word/document.xml

cd test_docx && zip -r ../eicar.docx . && cd ..
```

### 4. **Test Cases**

#### Test Case 1: Clean Text File
```bash
# File aman - harus PASS
echo "This is a safe document for testing purposes." > safe_test.txt

curl -X POST http://localhost:8000/api/analyze \
  -F "file=@safe_test.txt"

# Expected: success=true, analisis normal
```

#### Test Case 2: EICAR Test File
```bash
# File test - harus FAIL dengan virus detection
echo 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*' > eicar.txt

curl -X POST http://localhost:8000/api/analyze \
  -F "file=@eicar.txt"

# Expected: success=false, error message: "File terindikasi mengandung virus"
```

#### Test Case 3: Suspicious PDF
```bash
# PDF dengan embedded JavaScript (akan dideteksi heuristic)
# Buat file dengan /JavaScript reference

curl -X POST http://localhost:8000/api/analyze \
  -F "file=@eicar.pdf"

# Expected: success=false, error: "Suspicious embedded content detected"
```

#### Test Case 4: File Terlalu Besar
```bash
# Buat file > 50MB
dd if=/dev/zero of=large_file.txt bs=1M count=51

curl -X POST http://localhost:8000/api/analyze \
  -F "file=@large_file.txt"

# Expected: success=false, error: "Ukuran file terlalu besar"
```

#### Test Case 5: File Extension Tidak Diizinkan
```bash
# Upload file .exe atau format lain
echo "MZ" > test.exe

curl -X POST http://localhost:8000/api/analyze \
  -F "file=@test.exe"

# Expected: success=false, error: "Tipe file tidak diizinkan"
```

### 5. **Automated Test Script**

#### PowerShell Test Suite

```powershell
# test-virus-scanner.ps1

$apiUrl = "http://localhost:8000/api/analyze"
$testDir = ".\virus_test_files"

# Create test directory
New-Item -ItemType Directory -Path $testDir -Force | Out-Null

Write-Host "=== Virus Scanner Test Suite ===" -ForegroundColor Cyan
Write-Host ""

# Test 1: Clean Text
Write-Host "[Test 1] Clean Text File" -ForegroundColor Yellow
$cleanContent = @"
This is a safe document for testing the sentiment analysis API.
The quick brown fox jumps over the lazy dog.
Lorem ipsum dolor sit amet.
"@
$cleanContent | Out-File "$testDir\clean.txt" -Encoding UTF8

$response = curl -X POST $apiUrl -F "file=@$testDir\clean.txt" -s | ConvertFrom-Json
if ($response.success) {
    Write-Host "✓ PASS - File accepted and analyzed" -ForegroundColor Green
} else {
    Write-Host "✗ FAIL - $($response.error)" -ForegroundColor Red
}
Write-Host ""

# Test 2: EICAR Test String
Write-Host "[Test 2] EICAR Antivirus Test File" -ForegroundColor Yellow
$eicarString = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*'
$eicarString | Out-File "$testDir\eicar.txt" -Encoding ASCII -NoNewline

$response = curl -X POST $apiUrl -F "file=@$testDir\eicar.txt" -s | ConvertFrom-Json
if (-not $response.success) {
    Write-Host "✓ PASS - Virus detected: $($response.error)" -ForegroundColor Green
} else {
    Write-Host "✗ FAIL - Virus should have been detected" -ForegroundColor Red
}
Write-Host ""

# Test 3: Large File
Write-Host "[Test 3] File Size Limit (51MB)" -ForegroundColor Yellow
$largeFile = "$testDir\large.txt"
# Create 51MB file
$null = New-Item -ItemType File -Path $largeFile -Force
$stream = [System.IO.File]::Create($largeFile)
$stream.Seek(51 * 1024 * 1024 - 1, [System.IO.SeekOrigin]::Begin)
$stream.WriteByte(0)
$stream.Close()

$response = curl -X POST $apiUrl -F "file=@$largeFile" -s | ConvertFrom-Json
if (-not $response.success -and $response.error -like "*terlalu besar*") {
    Write-Host "✓ PASS - Large file rejected" -ForegroundColor Green
} else {
    Write-Host "✗ FAIL - Should reject large file" -ForegroundColor Red
}
Write-Host ""

# Test 4: Invalid File Type
Write-Host "[Test 4] Invalid File Type (.exe)" -ForegroundColor Yellow
"MZ" | Out-File "$testDir\test.exe" -Encoding ASCII -NoNewline

$response = curl -X POST $apiUrl -F "file=@$testDir\test.exe" -s | ConvertFrom-Json
if (-not $response.success -and $response.error -like "*tidak diizinkan*") {
    Write-Host "✓ PASS - Invalid file type rejected" -ForegroundColor Green
} else {
    Write-Host "✗ FAIL - Should reject .exe files" -ForegroundColor Red
}
Write-Host ""

# Test 5: Empty File
Write-Host "[Test 5] Empty File" -ForegroundColor Yellow
"" | Out-File "$testDir\empty.txt" -Encoding UTF8

$response = curl -X POST $apiUrl -F "file=@$testDir\empty.txt" -s | ConvertFrom-Json
if (-not $response.success -and $response.error -like "*kosong*") {
    Write-Host "✓ PASS - Empty file rejected" -ForegroundColor Green
} else {
    Write-Host "✗ FAIL - Should reject empty file" -ForegroundColor Red
}
Write-Host ""

# Test 6: Check Scanner Status
Write-Host "[Test 6] Scanner Status Check" -ForegroundColor Yellow
$statusUrl = "http://localhost:8000/api/scanner-status"
$response = curl -s -X GET $statusUrl | ConvertFrom-Json
if ($response.status -eq "ok") {
    Write-Host "✓ PASS - Scanner Status:" -ForegroundColor Green
    Write-Host "  - Type: $($response.virus_scanning.scanner_type)" -ForegroundColor Gray
    Write-Host "  - Enabled: $($response.virus_scanning.virus_scanning_enabled)" -ForegroundColor Gray
    Write-Host "  - Max Size: $($response.virus_scanning.max_file_size) bytes" -ForegroundColor Gray
} else {
    Write-Host "✗ FAIL - Cannot access scanner status" -ForegroundColor Red
}
Write-Host ""

Write-Host "=== Test Suite Complete ===" -ForegroundColor Cyan

# Cleanup (optional)
# Remove-Item -Path $testDir -Recurse -Force
```

#### Bash/Linux Test Script

```bash
#!/bin/bash

# test-virus-scanner.sh

API_URL="http://localhost:8000/api/analyze"
TEST_DIR="./virus_test_files"

echo "=== Virus Scanner Test Suite ==="
echo ""

# Create test directory
mkdir -p $TEST_DIR

# Test 1: Clean Text
echo "[Test 1] Clean Text File"
cat > "$TEST_DIR/clean.txt" << 'EOF'
This is a safe document for testing the sentiment analysis API.
The quick brown fox jumps over the lazy dog.
Lorem ipsum dolor sit amet.
EOF

response=$(curl -s -X POST $API_URL -F "file=@$TEST_DIR/clean.txt")
if echo "$response" | grep -q '"success":true'; then
    echo "✓ PASS - File accepted and analyzed"
else
    echo "✗ FAIL - $response"
fi
echo ""

# Test 2: EICAR Test File
echo "[Test 2] EICAR Antivirus Test File"
printf 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*' > "$TEST_DIR/eicar.txt"

response=$(curl -s -X POST $API_URL -F "file=@$TEST_DIR/eicar.txt")
if echo "$response" | grep -q '"success":false'; then
    echo "✓ PASS - Virus detected"
else
    echo "✗ FAIL - Virus should have been detected"
fi
echo ""

# Test 3: File Size Limit
echo "[Test 3] File Size Limit (51MB)"
dd if=/dev/zero of="$TEST_DIR/large.txt" bs=1M count=51 2>/dev/null

response=$(curl -s -X POST $API_URL -F "file=@$TEST_DIR/large.txt")
if echo "$response" | grep -q 'terlalu besar'; then
    echo "✓ PASS - Large file rejected"
else
    echo "✗ FAIL - Should reject large file"
fi
echo ""

# Test 4: Invalid File Type
echo "[Test 4] Invalid File Type (.exe)"
echo "MZ" > "$TEST_DIR/test.exe"

response=$(curl -s -X POST $API_URL -F "file=@$TEST_DIR/test.exe")
if echo "$response" | grep -q 'tidak diizinkan'; then
    echo "✓ PASS - Invalid file type rejected"
else
    echo "✗ FAIL - Should reject .exe files"
fi
echo ""

# Test 5: Scanner Status
echo "[Test 5] Scanner Status Check"
response=$(curl -s -X GET "http://localhost:8000/api/scanner-status")
if echo "$response" | grep -q '"status":"ok"'; then
    echo "✓ PASS - Scanner Status:"
    echo "$response" | grep -o '"scanner_type":"[^"]*"'
    echo "$response" | grep -o '"virus_scanning_enabled":[^,}]*'
else
    echo "✗ FAIL - Cannot access scanner status"
fi
echo ""

echo "=== Test Suite Complete ==="

# Cleanup
# rm -rf $TEST_DIR
```

### 6. **Manual Docker Testing**

```bash
# Akses container
docker exec -it sentiment-backend bash

# Test ClamAV directly
clamdscan --version

# Buat EICAR test file
printf 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*' > /tmp/eicar.txt

# Scan dengan clamdscan
clamdscan /tmp/eicar.txt

# Output should show: "EICAR-AVTEST.Com-EICAR_Test_File FOUND"
```

### 7. **Expected Test Results**

| Test Case | Input | Expected Result | Safety |
|-----------|-------|-----------------|--------|
| Clean Text | safe_test.txt | ✓ Success, analyzed | ✓ Safe |
| EICAR | eicar.txt | ✗ Virus detected | ✓ Safe |
| Suspicious PDF | suspicious.pdf | ✗ Embedded content detected | ✓ Safe |
| Large File (51MB) | large.txt | ✗ Size exceeded | ✓ Safe |
| Invalid Type (.exe) | test.exe | ✗ Type not allowed | ✓ Safe |
| Empty File | empty.txt | ✗ File empty | ✓ Safe |
| Scanner Status | N/A | ✓ Returns status | ✓ Safe |

## ⚠️ Important Safety Notes

1. **EICAR String 100% Safe**
   - Tidak ada code, hanya text string
   - Tidak executable
   - Digunakan oleh industri antivirus untuk testing

2. **Never Create Real Malware**
   - ❌ Jangan download real malware samples
   - ❌ Jangan modify actual malware code
   - ✅ Hanya gunakan EICAR test string

3. **Container Isolation**
   - Testing di dalam container terisolasi
   - Tidak akan affect host system
   - Dapat di-cleanup dengan `docker-compose down`

4. **ClamAV Quarantine**
   - ClamAV tidak auto-delete files
   - Files dipindahkan ke upload temp directory
   - Aman untuk testing

## 🧪 Quick Start Testing

```bash
# 1. Start containers
docker-compose up -d

# 2. Run PowerShell test suite
.\test-virus-scanner.ps1

# 3. Or run individual test
curl -X POST http://localhost:8000/api/analyze \
  -F "file=@eicar.txt" \
  -H "Accept: application/json"

# 4. Check logs
docker logs sentiment-backend | grep -i virus
```

## 📊 Performance Testing

```bash
# Measure scan time
Measure-Command {
    curl -X POST http://localhost:8000/api/analyze \
      -F "file=@large_safe_file.pdf" `
      -o $null
}

# Expected:
# - Small files (<10MB): 100-300ms
# - Medium files (10-50MB): 500-2000ms
# - ClamAV overhead: ~200-400ms
```

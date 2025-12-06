# 🧪 Testing Virus Scanner - Getting Started

## Apa yang sudah disiapkan?

Saya sudah membuat 3 dokumen lengkap untuk testing virus scanner secara aman:

1. **`VIRUS_SCANNER.md`** - Dokumentasi lengkap implementasi
2. **`VIRUS_TESTING_GUIDE.md`** - Panduan testing komprehensif dengan contoh kode
3. **`test-virus-scanner.ps1`** - Script PowerShell otomatis (7 test cases)
4. **`QUICK_TEST_REFERENCE.md`** - Cheat sheet cepat

## ⚡ 3 Cara Testing (dari Tercepat ke Paling Detail)

### Cara 1: Automated Test Suite (⏱️ 2 menit)

```powershell
cd backend
.\test-virus-scanner.ps1
```

✅ Runs 7 test cases automatically
✅ Clean, formatted output
✅ Perfect untuk quick verification

**Output:** Success rate percentage dengan detail setiap test

---

### Cara 2: Manual Testing dengan cURL (⏱️ 5 menit)

**Test 1: Upload file aman**
```bash
# Buat file clean
$content = @"
Ini adalah dokumen test untuk analisis sentimen.
Dokumen ini aman dan tidak mengandung malware.
Testing sentiment analysis dengan virus scanning.
"@
$content | Out-File "safe.txt" -Encoding UTF8

# Upload
curl -X POST http://localhost:8000/api/analyze `
  -F "file=@safe.txt"

# Expected: success = true
```

**Test 2: Virus detection (EICAR)**
```bash
# EICAR = industri standard antivirus test
# 100% aman, hanya string text
$eicar = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*'
$eicar | Out-File "eicar.txt" -Encoding ASCII -NoNewline

# Upload
curl -X POST http://localhost:8000/api/analyze `
  -F "file=@eicar.txt"

# Expected: success = false, error contains "virus"
```

**Test 3: Check scanner status**
```bash
curl http://localhost:8000/api/scanner-status

# Expected: status = "ok", scanner_type = "ClamAV"
```

---

### Cara 3: Docker Direct Testing (⏱️ 3 menit)

```bash
# Akses container bash
docker exec -it sentiment-backend bash

# Test ClamAV availability
clamd --version

# Create EICAR test file
printf 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*' > /tmp/eicar.txt

# Scan dengan clamdscan
clamdscan /tmp/eicar.txt

# Expected output: EICAR-AVTEST.Com-EICAR_Test_File FOUND
```

---

## 🎯 5 Test Cases Paling Penting

| # | Test | Input | Expected Output | Safety |
|---|------|-------|-----------------|--------|
| 1️⃣ | Clean File | safe.txt | ✓ Success, analyzed | ✅ 100% safe |
| 2️⃣ | EICAR Virus | eicar.txt | ✗ Virus detected | ✅ 100% safe |
| 3️⃣ | File Too Large | 51MB file | ✗ Size limit | ✅ 100% safe |
| 4️⃣ | Invalid Type | test.exe | ✗ Type rejected | ✅ 100% safe |
| 5️⃣ | Scanner Status | GET endpoint | ✓ Status OK | ✅ 100% safe |

---

## 🔐 Keamanan Testing

### ✅ EICAR String - Industri Standard
- **Apa itu?** String test yang dikenali oleh semua antivirus
- **Aman?** Ya 100% - hanya text, bukan executable
- **Digunakan?** Oleh semua vendor antivirus sejak 1998
- **Contoh:** `X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*`

### ❌ JANGAN LAKUKAN
- ❌ Download malware real dari internet
- ❌ Modifikasi malware code
- ❌ Upload ke system production tanpa sandbox

### ✅ YANG BOLEH
- ✅ EICAR test string
- ✅ Heuristic test files (PDF dengan /JavaScript pattern)
- ✅ Container-isolated testing (Docker)
- ✅ Script-generated test files

---

## 🚀 Full Testing Workflow

### Step 1: Pastikan Docker Running
```powershell
docker-compose up -d
docker-compose ps  # Verify all containers running
```

### Step 2: Wait for Services
```powershell
# Tunggu ~10 detik untuk ClamAV daemon start
Start-Sleep -Seconds 10
```

### Step 3: Run Tests
```powershell
cd backend
.\test-virus-scanner.ps1 -Verbose
```

### Step 4: Review Results
```
✓ All 7 tests passed = 100%
✗ Some failed = Debug & check logs
```

### Step 5: (Optional) Cleanup
```powershell
.\test-virus-scanner.ps1 -Cleanup  # Remove test files
```

---

## 📊 Expected Results

### Clean File Test
```json
{
  "success": true,
  "sentiment": "neutral",
  "sentiment_score": 0.5,
  "readability": 65.2,
  "word_count": 15,
  "text": "Ini adalah dokumen test untuk analisis sentimen..."
}
```

### EICAR Virus Test
```json
{
  "success": false,
  "error": "File tidak aman: File terindikasi mengandung virus/malware (EICAR-AVTEST.Com-EICAR_Test_File FOUND)"
}
```

### Scanner Status
```json
{
  "status": "ok",
  "virus_scanning": {
    "virus_scanning_enabled": true,
    "scanner_type": "ClamAV",
    "max_file_size": 52428800,
    "supported_formats": ["txt", "pdf", "docx", "doc"]
  },
  "timestamp": "2025-12-06 10:30:45"
}
```

---

## 🐛 Troubleshooting

### Problem: "Cannot reach API"
```bash
# Solution:
docker-compose up -d
docker logs sentiment-backend
```

### Problem: "EICAR not detected"
```bash
# Solution: Check ClamAV daemon
docker exec sentiment-backend clamd --version
docker exec sentiment-backend clamdscan --version

# Update virus definitions
docker exec sentiment-backend freshclam
```

### Problem: Test files not found
```bash
# Solution:
cd backend  # Must be in backend directory
ls -la test-virus-scanner.ps1

# Run with explicit path
powershell -File "$(pwd)\test-virus-scanner.ps1"
```

### Problem: Permission denied
```bash
# Solution:
Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope CurrentUser
.\test-virus-scanner.ps1
```

---

## 📈 Performance Expectations

| File Size | Scan Time | Total Time |
|-----------|-----------|------------|
| 1KB | ~150ms | ~300ms |
| 1MB | ~200ms | ~500ms |
| 10MB | ~400ms | ~1000ms |
| 50MB | ~800ms | ~2000ms |

---

## 🎓 Learning Resources

1. **EICAR Test:**
   - Baca: `QUICK_TEST_REFERENCE.md`
   - Test file: Copy EICAR string

2. **Comprehensive Guide:**
   - Baca: `VIRUS_TESTING_GUIDE.md`
   - Semua test cases dengan penjelasan

3. **Implementation Details:**
   - Baca: `VIRUS_SCANNER.md`
   - Architecture & configuration

4. **Automated Testing:**
   - Run: `test-virus-scanner.ps1`
   - Lihat hasil di console

---

## ✨ Next Steps

1. ✅ Start Docker containers
2. ✅ Run automated test suite
3. ✅ Review results
4. ✅ Check documentation if issues
5. ✅ Integrate into CI/CD (optional)

---

## 📞 Quick Commands Reference

```bash
# Start everything
docker-compose up -d

# Run full test suite
cd backend && .\test-virus-scanner.ps1

# Check scanner status
curl http://localhost:8000/api/scanner-status

# View container logs
docker logs sentiment-backend -f

# Stop everything
docker-compose down

# Clean everything
docker-compose down -v
```

---

**Siap? Langsung jalankan:**

```powershell
cd backend
.\test-virus-scanner.ps1 -Verbose
```

Selamat testing! 🎉

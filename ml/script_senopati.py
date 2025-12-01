#sebelum memulai mari berdoa dan pip install requests beautifulsoup4 python-dotenv
#step 2 adalah membuat file .env di folder yang sama dengan try.py
#lalu masukkan API_KEY=your-secret-api-key-from-google (ini sikrit jadi jangan dishare)

import requests
import json
import os
from bs4 import BeautifulSoup
from dotenv import load_dotenv

# Load API key
# load_dotenv()
# API_KEY = os.getenv("API_KEY")

# if not API_KEY:
#     print("Error: API key tidak ditemukan.")
#     print("Pastikan Anda sudah membuat file .env dan mengaturnya dengan benar.")
#     exit()

# Endpoint Senopati AI
API_URL = "https://senopati.its.ac.id/senopati-lokal-dev/generate"

def clean_html_text(html_text):
    """Fungsi untuk menghapus tag HTML dari teks."""
    soup = BeautifulSoup(html_text, 'html.parser')
    return soup.get_text(separator=' ', strip=True)

def analyze_news_from_html(file_path):
    """
    Fungsi untuk membaca file HTML, membersihkan teksnya,
    dan mengirimkannya ke API untuk dianalisis.
    """
    try:
        with open(file_path, 'r', encoding='utf-8') as file:
            html_content = file.read()
        plain_text = clean_html_text(html_content)
        
        if not plain_text:
            print("Error: Teks tidak ditemukan di dalam file HTML.")
            return None


        payload = {
            "model": "qwen2.5:14b",
            "prompt": f"Analisis sentimen, entitas, dan tema utama dari berita berikut: '{plain_text}'. "
                      "Hasilkan output HANYA dalam format JSON. Jangan ada teks atau penjelasan lain di luar objek JSON. "
                      "JSON harus memiliki kunci-kunci berikut: 'sentiment', 'score' (-1.0 hingga +1.0), 'details' (alasan mendalam), 'entitas', dan 'tema'. "
                      "Untuk 'entitas' dan 'tema', gunakan array objek di mana setiap objek memiliki kunci: 'nama' (string), 'magnitudo' (float), dan 'skor_sentimen' (float)."
                      " Contoh Skema JSON: { \"sentiment\": \"string\", \"score\": 0.0, \"details\": \"string\", \"entitas\": [ { \"nama\": \"string\", \"magnitudo\": 0.0, \"skor_sentimen\": 0.0 } ], \"tema\": [ { \"nama\": \"string\", \"magnitudo\": 0.0, \"skor_sentimen\": 0.0 } ] } ",
            "stream": False
        }
        
        response = requests.post(API_URL, json=payload)
        response.raise_for_status()
        
        # Parse the JSON response from Senopati AI
        response_data = response.json()
        
        # The actual analysis is in the 'response' field which is a JSON string.
        # We need to parse it again.
        try:
            analysis_json_str = response_data.get("response", "{}")
            # Membersihkan string JSON dari markdown code block
            if analysis_json_str.startswith("```json"):
                analysis_json_str = analysis_json_str[7:]
            if analysis_json_str.endswith("```"):
                analysis_json_str = analysis_json_str[:-3]
            
            analysis_json = json.loads(analysis_json_str.strip())
            return analysis_json
        except json.JSONDecodeError as e:
            print(f"Error: Gagal mem-parsing JSON dari respons Senopati AI: {e}")
            print("Respons mentah:", response_data.get("response"))
            return None
        
    except FileNotFoundError:
        print(f"Error: File '{file_path}' tidak ditemukan.")
        return None
    except requests.exceptions.RequestException as e:
        print(f"Error saat mengirim permintaan: {e}")
        return None

# Proses File
nama_file_html = "berita.html"
print(f"Memproses file: {nama_file_html}")

hasil_analisis = analyze_news_from_html(nama_file_html)

if hasil_analisis:
    print("\n--- Hasil Analisis ---")
    print(json.dumps(hasil_analisis, indent=4))

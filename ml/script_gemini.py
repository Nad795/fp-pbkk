#sebelum memulai mari berdoa dan pip install requests beautifulsoup4 python-dotenv
#step 2 adalah membuat file .env di folder yang sama dengan try.py
#lalu masukkan API_KEY=your-secret-api-key-from-google (ini sikrit jadi jangan dishare)

import requests
import json
import os
from bs4 import BeautifulSoup
from dotenv import load_dotenv

# Load API key
load_dotenv()
API_KEY = os.getenv("API_KEY")

if not API_KEY:
    print("Error: API key tidak ditemukan.")
    print("Pastikan Anda sudah membuat file .env dan mengaturnya dengan benar.")
    exit()

# Endpoint Gemini
API_URL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent"

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
            "contents": [
                {
                    "parts": [
                        {
                            "text": f"Analisis sentimen, entitas, dan tema utama dari berita berikut: '{plain_text}'. "
                                    "Hasilkan output HANYA dalam format JSON. Jangan ada teks atau penjelasan lain di luar objek JSON. "
                                    "JSON harus memiliki kunci-kunci berikut: 'sentiment', 'score' (-1.0 hingga +1.0), 'details' (alasan mendalam), 'entitas', dan 'tema'. "
                                    "Untuk 'entitas' dan 'tema', gunakan array objek di mana setiap objek memiliki kunci: 'nama' (string), 'magnitudo' (float), dan 'skor_sentimen' (float)."
                                    " Contoh Skema JSON: { \"sentiment\": \"string\", \"score\": 0.0, \"details\": \"string\", \"entitas\": [ { \"nama\": \"string\", \"magnitudo\": 0.0, \"skor_sentimen\": 0.0 } ], \"tema\": [ { \"nama\": \"string\", \"magnitudo\": 0.0, \"skor_sentimen\": 0.0 } ] } "
                        }
                    ]
                }
            ]
        }
        
        response = requests.post(f"{API_URL}?key={API_KEY}", json=payload)
        response.raise_for_status()
        
        return response.json()
        
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

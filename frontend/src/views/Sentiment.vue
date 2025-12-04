<template>
  <!-- Background Mesh -->
  <div class="absolute -bottom-10 -left-20 w-[400px] h-[400px] mesh-left"></div>
  <div class="absolute -bottom-20 right-0 w-[500px] h-[500px] mesh-right"></div>

  <div class="relative overflow-hidden bg-transparent">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-[1fr_2fr]">
      <!-- === KIRI: Skor Sentimen === -->
      <div
        class="flex flex-col items-center p-6 text-center bg-transparent border-4 border-white shadow rounded-2xl"
      >
        <div class="flex flex-col items-center md:items-start w-full px-2">
          <h2
            class="flex items-center gap-2 mb-2 text-3xl font-bold text-primarypurple"
          >
            Skor Sentimen
            <span
              class="flex items-center justify-center bg-transparent rounded-full w-7 h-7"
              id="tooltip-skor-sentimen"
            >
              <Icon
                icon="mdi:help-circle-outline"
                class="text-xl text-primarypurple"
              />
            </span>
          </h2>
          <Tooltip
            target="tooltip-skor-sentimen"
            text="Analisis proporsi sentimen teks berdasarkan
            kategori positif, negatif, dan netral."
            placement="top"
            noninteractive
          />
          <p class="mb-4 text-lg font-semibold text-primarypurple">Persentase</p>
        </div>

        <!-- Circle Diagram -->
        <div class="relative flex items-center justify-center w-48 h-48 mb-6">
          <svg
            class="absolute top-0 left-0 w-full h-full transform -rotate-90"
            viewBox="0 0 264 264"
          >
            <!-- Lingkaran background -->
            <circle
              cx="132"
              cy="132"
              r="108"
              stroke="#E5E7EB"
              stroke-width="24"
              fill="none"
            />

            <!-- Arc Positif -->
            <circle
              cx="132"
              cy="132"
              r="108"
              stroke="#55C895"
              stroke-width="24"
              stroke-linecap="round"
              fill="none"
              :stroke-dasharray="circumference"
              :stroke-dashoffset="positiveOffset"
              class="transition-circle"
            />

            <!-- Arc Netral -->
            <circle
              cx="132"
              cy="132"
              r="108"
              stroke="#FFE14F"
              stroke-width="24"
              stroke-linecap="round"
              fill="none"
              :stroke-dasharray="circumference"
              :stroke-dashoffset="neutralOffset"
              class="transition-circle"
              :transform="`rotate(${neutralRotation} 132 132)`"
            />

            <!-- Arc Negatif -->
            <circle
              cx="132"
              cy="132"
              r="108"
              stroke="#F75D51"
              stroke-width="24"
              stroke-linecap="round"
              fill="none"
              :stroke-dasharray="circumference"
              :stroke-dashoffset="negativeOffset"
              class="transition-circle"
              :transform="`rotate(${negativeRotation} 132 132)`"
            />
          </svg>

          <!-- Ekspresi wajah -->
          <svg
            v-if="sentimentData.overallSentiment"
            class="w-40 h-40 transition-all duration-700"
            viewBox="0 0 264 264"
          >
            <!-- Wajah -->
            <circle
              cx="132"
              cy="132"
              r="69.6"
              :fill="faceColor"
              class="transition-all duration-700"
            />
            <!-- Mata -->
            <circle cx="103.7" cy="118.5" r="13.05" fill="#F5F5F7" />
            <circle cx="158.1" cy="118.5" r="13.05" fill="#F5F5F7" />

            <!-- Mulut -->
            <path
              v-if="sentimentData.overallSentiment === 'positif'"
              d="M106.9 154.4C109.1 159.1 117.1 168.6 131.5 168.6C145.8 168.6 153.8 159.1 156 154.4"
              stroke="#F5F5F7"
              stroke-width="7.2"
              stroke-linecap="round"
              fill="none"
            />
            <path
              v-else-if="sentimentData.overallSentiment === 'netral'"
              d="M110 165 H153"
              stroke="#F5F5F7"
              stroke-width="7.2"
              stroke-linecap="round"
            />
            <path
              v-else
              d="M106 172C120 155,144 155,158 172"
              stroke="#F5F5F7"
              stroke-width="7.2"
              stroke-linecap="round"
              fill="none"
            />
          </svg>
        </div>

        <div class="flex justify-between w-full px-8 text-md">
          <div class="text-green-500"><b>Positif</b><br />{{ sentimentData.positive.toFixed(1) }}%</div>
          <div class="text-red-500"><b>Negatif</b><br />{{ sentimentData.negative.toFixed(1) }}%</div>
          <div class="text-yellow-500"><b>Netral</b><br />{{ sentimentData.neutral.toFixed(1) }}%</div>
        </div>

        <div class="flex flex-col mt-10 items-center md:items-start w-full px-2">
          <p class="flex items-center mb-4 text-lg font-semibold text-primarypurple">Rating Keseluruhan</p>
          <p class="items-center mb-4 text-sm text-center">
            Teks ini secara keseluruhan memiliki sentimen yang
          <span :class="['font-semibold', overallSentimentClass]">{{ sentimentData.overallSentiment }} {{ sentimentData.overallScore }}</span>.
          </p>
        </div>
      </div>

      <!-- === KANAN: Detail Sentimen === -->
      <div class="flex flex-col gap-6">
        <!-- 3 Box: Positif Negatif Netral -->
        <div class="grid grid-cols-3 gap-4">
          <!---<Positif>--->
           <div class="sentiment-card positive-card">
            <div class="items-center justify-start">
              <h3
                class="flex items-center gap-2 text-2xl font-semibold text-green-500"
              >
                Positif
                <span
                  class="flex items-center justify-center bg-transparent rounded-full w-7 h-7"
                  id="tooltip-positif"
                >
                  <Icon
                    icon="mdi:help-circle-outline"
                    class="text-xl text-green-500"
                  />
                </span>
              </h3>
              <Tooltip class="text-center"
                target="tooltip-positif"
                text="Tingkat emosi atau opini bernada positif.
                Nilai mendekati 1.0 berarti teks sangat positif."
                placement="top"
                noninteractive
              />
              <p class="text-md font-semibold">{{ (sentimentData.positive / 100).toFixed(2) }}<span class=" text-sm text-grey-500"> dari 1.0</span></p>
            </div>
          </div>
          <!---<Negatif>--->
          <div class="sentiment-card negative-card">
            <div class="items-center justify-start">
              <h3
                class="flex items-center gap-2 text-2xl font-semibold text-red-500"
              >
                Negatif
                <span
                  class="flex items-center justify-center bg-transparent rounded-full w-7 h-7"
                  id="tooltip-negatif"
                >
                  <Icon
                    icon="mdi:help-circle-outline"
                    class="text-xl text-red-500"
                  />
                </span>
              </h3>
              <Tooltip class="text-center"
                target="tooltip-negatif"
                text="Tingkat emosi atau opini bernada negatif.
                Nilai mendekati 1.0 berarti teks sangat negatif."
                placement="top"
                noninteractive
              />
              <p class="text-md font-semibold">{{ (sentimentData.negative / 100).toFixed(2) }}<span class=" text-sm text-grey-500"> dari 1.0</span></p>
            </div>
          </div>
          <!---<Netral>--->
           <div class="sentiment-card neutral-card">
            <div class="items-center justify-start">
              <h3
                class="flex items-center gap-2 text-2xl font-semibold text-yellow-600"
              >
                Netral
                <span
                  class="flex items-center justify-center bg-transparent rounded-full w-7 h-7"
                  id="tooltip-netral"
                >
                  <Icon
                    icon="mdi:help-circle-outline"
                    class="text-xl text-yellow-600"
                  />
                </span>
              </h3>
              <Tooltip class="text-center"
                target="tooltip-netral"
                text="Bagian teks yang tidak menonjolkan emosi, 
                bersifat informatif atau seimbang. Nilai 
                mendekati 1.0 berarti teks cenderung netral."
                placement="top"
                noninteractive
              />
              <p class="text-md font-semibold">{{ (sentimentData.neutral / 100).toFixed(2) }}<span class=" text-sm text-grey-500"> dari 1.0</span></p>
            </div>
          </div>
        </div>

        <!-- Scrollable Table Section -->
        <div
          class="flex flex-col p-6 overflow-hidden bg-transparent border-4 border-white shadow rounded-2xl"
        >
          <div class="pr-2 overflow-y-auto overflow-x-auto" style="max-height: 350px">
            <!-- Table Entitas -->
            <div class="border-b pb-4 mb-6 last:border-none last:pb-0">
              <div class="flex justify-between items-center py-3 cursor-pointer select-none" @click="toggleExpand('entitas')">
                <h2 class="mb-2 font-semibold text-md text-primarypurple">
                  Entitas Terdeteksi
                </h2>
                <svg
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  fill="none"
                  class="transition-transform duration-300"
                  :class="{ 'rotate-180': expanded.entitas }"
                >
                  <path
                    d="M6 9L12 15L18 9"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </div>
              <transition name="fade-slide">
                <table v-show="expanded.entitas" class="w-full text-sm text-left border-collapse table-fixed min-w-[640px]">
                  <thead class="text-gray-600">
                    <tr class="border-b border-gray-200">
                      <th class="p-2 text-center w-1/3">Entitas</th>
                      <th class="p-2 text-center w-1/3">Magnitudo</th>
                      <th class="p-2 text-center w-1/3">Skor Sentimen</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(e, i) in entitasData"
                      :key="'entitas-' + i"
                      class="border-b border-gray-100"
                      :style="{
                        backgroundColor: i % 2 === 0 ? '#C9DEFB' : 'transparent',
                      }"
                    >
                      <td class="p-2 w-1/3">{{ e.nama }}</td>
                      <td class="p-2 text-center w-1/3">{{ e.magnitudo }}</td>
                      <td
                        class="p-2 font-semibold text-center w-1/3"
                        :style="{ backgroundColor: getSentimentColor(e.sentimen) }"
                      >
                        {{ e.sentimen > 0 ? "+" : "" }}{{ e.sentimen.toFixed(2) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </transition>
            </div>

            <!-- Table Tema -->
            <div class="border-b pb-4 mb-6 last:border-none last:pb-0">
              <div class="flex justify-between items-center py-3 cursor-pointer select-none" @click="toggleExpand('tema')">
                <h2 class="mb-2 font-semibold text-md text-primarypurple">
                  Tema Terdeteksi
                </h2>
                <svg
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  fill="none"
                  class="transition-transform duration-300"
                  :class="{ 'rotate-180': expanded.tema }"
                >
                  <path
                    d="M6 9L12 15L18 9"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </div>
              <transition name="fade-slide">
                <table v-show="expanded.tema" class="w-full text-sm text-left border-collapse table-fixed min-w-[640px]">
                  <thead class="text-gray-600">
                    <tr class="border-b border-gray-200">
                      <th class="p-2 text-center w-1/3">Tema</th>
                      <th class="p-2 text-center w-1/3">Magnitudo</th>
                      <th class="p-2 text-center w-1/3">Skor Sentimen</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(t, i) in temaData"
                      :key="'tema-' + i"
                      class="border-b border-gray-100"
                      :style="{
                        backgroundColor: i % 2 === 0 ? '#C9DEFB' : 'transparent',
                      }"
                    >
                      <td class="p-2 w-1/3">{{ t.nama }}</td>
                      <td class="p-2 text-center w-1/3">{{ t.magnitudo }}</td>
                      <td
                        class="p-2 font-semibold text-center w-1/3"
                        :style="{ backgroundColor: getSentimentColor(t.sentimen) }"
                      >
                        {{ t.sentimen > 0 ? "+" : "" }}{{ t.sentimen.toFixed(2) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </transition>
            </div>

            <!-- Table Keywords -->
            <div class="border-b pb-4 mb-6 last:border-none last:pb-0">
              <div class="flex justify-between items-center py-3 cursor-pointer select-none" @click="toggleExpand('keywords')">
                <h2 class="mb-2 font-semibold text-md text-primarypurple">
                  Keywords Terdeteksi
                </h2>
                <svg
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  fill="none"
                  class="transition-transform duration-300"
                  :class="{ 'rotate-180': expanded.keywords }"
                >
                  <path
                    d="M6 9L12 15L18 9"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </div>
              <transition name="fade-slide">
                <table v-show="expanded.keywords" class="w-full text-sm text-left border-collapse table-fixed min-w-[640px]">
                  <thead class="text-gray-600">
                    <tr class="border-b border-gray-200">
                      <th class="p-2 text-center w-1/3">Keyword</th>
                      <th class="p-2 text-center w-1/3">Magnitudo</th>
                      <th class="p-2 text-center w-1/3">Skor Sentimen</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(k, i) in keywordsData"
                      :key="'keywords-' + i"
                      class="border-b border-gray-100"
                      :style="{
                        backgroundColor: i % 2 === 0 ? '#C9DEFB' : 'transparent',
                      }"
                    >
                      <td class="p-2 w-1/3">{{ k.nama }}</td>
                      <td class="p-2 text-center w-1/3">{{ k.magnitudo }}</td>
                      <td
                        class="p-2 font-semibold text-center w-1/3"
                        :style="{ backgroundColor: getSentimentColor(k.sentimen) }"
                      >
                        {{ k.sentimen > 0 ? "+" : "" }}{{ k.sentimen.toFixed(2) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </transition>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from "vue";
import Tooltip from '@/components/Tooltip.vue'

const sentimentData = ref({
    positive: 0.0,
    negative: 0.0,
    neutral: 100.0,
    overallScore: 0.0,
    overallSentiment: 'netral'
});

const entitasData = ref([]);
const temaData = ref([]);
const keywordsData = ref([]);

// === Load data dari backend ===
const loadAnalysisData = () => {
  const saved = localStorage.getItem('analysisResult')
  if (!saved) {
    console.log('Tidak ada data analisis sentiment. Menggunakan data default.')
    return
  }

  try {
    const parsed = JSON.parse(saved)
    console.log('Data sentiment ditemukan:', parsed)

    if (parsed.sentiment && parsed.sentiment_score !== undefined) {
      
      // --- 1. AMBIL sentiment_scores LANGSUNG DARI API ---
      const sentimentLabel = parsed.sentiment.toLowerCase()
      const sentimentScore = parseFloat(parsed.sentiment_score) || 0
      
      // Gunakan sentiment_scores dari API jika tersedia
      let pos = 50, neg = 25, neu = 25;
      
      if (parsed.sentiment_scores && typeof parsed.sentiment_scores === 'object') {
        // Convert dari 0.0-1.0 scale ke percentage
        pos = (parsed.sentiment_scores.positive || 0) * 100
        neg = (parsed.sentiment_scores.negative || 0) * 100
        neu = (parsed.sentiment_scores.neutral || 0) * 100
        
        // Normalize jika total tidak 100
        const total = pos + neg + neu
        if (total > 0 && total !== 100) {
          pos = (pos / total) * 100
          neg = (neg / total) * 100
          neu = (neu / total) * 100
        }
      } else {
        // Fallback: gunakan simple logic jika sentiment_scores tidak ada
        if (sentimentScore >= 0.2) {
          pos = (sentimentScore * 50) + 50
          neg = 100 - pos
          neu = 0
        } else if (sentimentScore <= -0.2) {
          neg = (Math.abs(sentimentScore) * 50) + 50
          pos = 100 - neg
          neu = 0
        } else {
          neu = 60
          pos = 25
          neg = 15
        }
      }
      
      // Update data Sentimen Utama
      sentimentData.value = {
        positive: pos,
        negative: neg,
        neutral: neu,
        overallScore: sentimentScore,
        overallSentiment: sentimentLabel.includes('positif') ? 'positif' : (sentimentLabel.includes('negatif') ? 'negatif' : 'netral')
      }

      // --- 2. AMBIL DATA TABEL LANGSUNG DARI API (ENTITAS & TEMA) ---
      entitasData.value = (parsed.entitas_terdeteksi || []).map(e => ({
        nama: e.nama || '',
        magnitudo: parseFloat(e.magnitudo) || 0,
        sentimen: parseFloat(e.skor_sentimen) || 0
      }))
      
      temaData.value = (parsed.tema_terdeteksi || []).map(t => ({
        nama: t.nama || '',
        magnitudo: parseFloat(t.magnitudo) || 0,
        sentimen: parseFloat(t.skor_sentimen) || 0
      }))

      keywordsData.value = (parsed.keywords_terdeteksi || []).map(k => ({
        nama: k.nama || '',
        magnitudo: parseFloat(k.magnitudo) || 0,
        sentimen: parseFloat(k.skor_sentimen) || 0
      }))

      console.log('Data berhasil dimuat - Entitas:', entitasData.value.length, 'Tema:', temaData.value.length, 'Keywords:', keywordsData.value.length)
    }
  } catch (error) {
    console.error('Error parsing sentiment result:', error)
  }
}

const radius = 108;
const circumference = 2 * Math.PI * radius;

// Reactive offsets (animasi)
const positiveOffset = ref(circumference);
const neutralOffset = ref(circumference);
const negativeOffset = ref(circumference);
const neutralRotation = ref(0);
const negativeRotation = ref(0);

// Warna wajah dinamis
const faceColor = ref("#55C895");

// Fungsi animasi lingkaran
function animateCircle() {
  const pos = sentimentData.value.positive;
  const neu = sentimentData.value.neutral;
  const neg = sentimentData.value.negative;

  // Konversi ke rotasi antar-arc
  const posAngle = (pos / 100) * 360;
  const neuAngle = (neu / 100) * 360;

  // Stroke offset (semakin besar, semakin sedikit arc)
  positiveOffset.value = circumference - (pos / 100) * circumference;
  neutralOffset.value = circumference - (neu / 100) * circumference;
  negativeOffset.value = circumference - (neg / 100) * circumference;

  neutralRotation.value = posAngle;
  negativeRotation.value = posAngle + neuAngle;

  // Warna wajah
  faceColor.value =
    sentimentData.value.overallSentiment === "positif"
      ? "#55C895"
      : sentimentData.value.overallSentiment === "netral"
      ? "#E8CD48"
      : "#E1554A";
}

// Jalankan saat pertama kali render
onMounted(() => {
  loadAnalysisData()
  setTimeout(animateCircle, 300);
});

// Jika data berubah manual
watch(sentimentData, animateCircle, { deep: true });

const getSentimentColor = (value) => {
  if (value > 0.5) return '#8DDAB8';
  if (value < -0.5) return '#FA928A';
  return '#FFEB89';
};

const expanded = ref({
  entitas: true,
  tema: true,
  keywords: true,
})

// Fungsi toggle
const toggleExpand = (section) => {
  expanded.value[section] = !expanded.value[section]
}

// Computed class for overall sentiment text color
const overallSentimentClass = computed(() => {
  const s = (sentimentData.value.overallSentiment || '').toLowerCase();
  if (s.includes('positif') || s === 'positive') return 'text-green-500';
  if (s.includes('negatif') || s === 'negative') return 'text-red-500';
  return 'text-yellow-600';
})
</script>

<style scoped>
.text-primarypurple {
  color: #7c3aed;
}

.text-green-500 {
  color: var(--green-500);
}

.text-red-500 {
  color: var(--red-500);
}

.text-yellow-600 {
  color: var(--yellow-600);
}

.text-grey-500 {
  color: var(--grey-500);
}

.bg-green-500 {
  background: var(--green-500);
}

.bg-red-500 {
  background: var(--red-500);
}

.bg-yellow-500 {
  background: var(--yellow-500);
}

.mesh-left {
  background: radial-gradient(
    circle at 0% 0%,
    rgba(80, 148, 241, 0.5) 0%,
    transparent 70%
  );
  filter: blur(90px);
  border-radius: 1000px;
}

.mesh-right {
  background: radial-gradient(
    circle at 70% 80%,
    rgba(80, 148, 241, 0.5) 0%,
    transparent 70%
  );
  filter: blur(100px);
  border-radius: 1000px;
}

.sentiment-card {
  flex: 1;
  display: flex;
  align-items: center;
  padding-left: 32px;
  border-radius: 0 16px 16px 0;
  border: 3px solid white;
  border-left-width: 12px;
  background: rgba(245, 245, 247, 0.3);
  min-height: 117px;
}

.positive-card {
  border-left-color: var(--green-500);
}

.negative-card {
  border-left-color: var(--red-500);
}

.neutral-card {
  border-left-color: var(--yellow-500);
}

/* Animasi transisi */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.3s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}

.transition-circle {
  transition: stroke-dashoffset 1.2s ease, transform 1.2s ease;
}
</style>

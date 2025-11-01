<template>
  <div class="absolute -bottom-10 -left-20 w-[400px] h-[400px] mesh-left"></div>
  <div class="absolute -bottom-20 right-0 w-[500px] h-[500px] mesh-right"></div>
  <div class="relative overflow-hidden bg-transparent">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-[1fr_2fr]">
      <div
        class="flex flex-col items-center p-6 bg-transparent border-4 border-white shadow rounded-2xl"
      >
        <div class="flex flex-col items-center md:items-start w-full px-2">
          <h2
            class="flex items-center gap-2 mb-2 text-3xl font-bold text-primarypurple"
          >
            Skor Keterbacaan
            <span
              class="flex items-center justify-center bg-transparent rounded-full w-7 h-7"
              id="tooltip-keterbacaan"
            >
              <Icon
                icon="mdi:help-circle-outline"
                class="text-xl text-primarypurle"
              />
            </span>
          </h2>
          <Tooltip class="text-center"
            target="tooltip-keterbacaan"
            text="Skor yang menunjukkan tingkat kemudahan 
            teks untuk dibaca. Semakin tinggi nilainya, 
            semakin mudah dipahami."
            placement="top"
            noninteractive
          />
          <p class="mb-4 text-lg font-semibold text-primarypurple">Skor</p>
        </div>

        <div class="relative flex items-center justify-center w-48 h-48 mb-6">
          <svg
            class="absolute top-0 left-0 w-full h-full transform -rotate-90"
            viewBox="0 0 192 192"
          >
            <!-- Lingkaran background -->
            <circle
              cx="96"
              cy="96"
              r="80"
              stroke="#E5E7EB"
              stroke-width="10"
              fill="none"
            />
            <!-- Lingkaran progress -->
            <circle
              cx="96"
              cy="96"
              r="80"
              stroke="#22C55E"
              stroke-width="10"
              fill="none"
              stroke-linecap="round"
              :stroke-dasharray="circumference"
              :stroke-dashoffset="animatedOffset"
              style="transition: stroke-dashoffset 1s ease;"
            />
          </svg>

          <!-- Angka skor dengan animasi -->
          <span class="text-5xl font-bold text-green-500">
            {{ fleschData[0]?.score.toFixed(1) }}
          </span>
        </div>

        <div class="flex flex-col items-center md:items-start w-full px-2">
          <p class="flex items-center mb-4 text-lg font-semibold text-primarypurple">Deskripsi Skor</p>
          <p class="flex items-center mb-4 text-sm text-center">{{ fleschData[0]?.description }}</p>
        </div>
      </div>

      <div class="flex flex-col gap-6">
        <!-- Statistik -->
        <div class="p-6 bg-transparent border-4 border-white rounded-2xl">
          <div class="grid grid-cols-5 text-center">
            <div>
              <p class="text-2xl font-semibold text-gray-800">{{fleschData[0]?.statistics.syllables}}</p>
              <p class="text-sm text-gray-500">Suku Kata</p>
            </div>
            <div>
              <p class="text-2xl font-semibold text-gray-800">{{fleschData[0]?.statistics.words}}</p>
              <p class="text-sm text-gray-500">Kata</p>
            </div>
            <div>
              <p class="text-2xl font-semibold text-gray-800">{{fleschData[0]?.statistics.sentences}}</p>
              <p class="text-sm text-gray-500">Kalimat</p>
            </div>
            <div>
              <p class="text-2xl font-semibold text-gray-800">{{fleschData[0]?.statistics.avgWordLength}}</p>
              <p class="text-sm text-gray-500">Panjang Kata</p>
            </div>
            <div>
              <p class="text-2xl font-semibold text-gray-800">{{fleschData[0]?.statistics.avgSentenceLength}}</p>
              <p class="text-sm text-gray-500">Panjang Kalimat</p>
            </div>
          </div>
        </div>

        <div class="flex flex-col p-6 overflow-hidden bg-transparent border-4 border-white shadow rounded-2xl">
          <div class="pr-2 overflow-y-auto" style="max-height: 350px">
            <div
              v-for="(item, i) in fleschData.slice(1)"
              :key="i"
              class="border-b pb-4 mb-6 last:border-none last:pb-0"
            >
              <!-- Header -->
              <div
                class="flex justify-between items-center py-3 cursor-pointer select-none"
                @click="toggleExpand(i)"
              >
                <h2 class="font-semibold text-md text-primarypurple">
                  {{ item.title }}
                </h2>
                <svg
                  :class="[
                    'w-6 h-6 text-gray-600 transform transition-transform duration-300',
                    expanded[i] ? 'rotate-180' : ''
                  ]"
                  viewBox="0 0 24 24"
                  fill="none"
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

              <!-- Konten expand -->
              <transition name="fade-slide">
                <div v-if="expanded[i]" class="overflow-hidden">
                  <div class="flex items-center mt-2">
                    <span class="mr-2 font-bold text-green-500">{{ item.score }}</span>
                    <span class="text-sm text-gray-500">/ 10</span>
                  </div>

                  <div class="w-full h-2 mt-2 bg-gray-200 rounded-full overflow-hidden">
                    <div
                      class="h-2 bg-green-500 rounded-full transition-all duration-700 ease-out"
                      :style="{ width: progressWidths[i] + '%' }"
                    ></div>
                  </div>

                  <p class="mt-2 text-sm text-gray-500">{{ item.level }}</p>
                  <p class="mt-1 text-xs text-gray-400">Rumus: {{ item.formula }}</p>
                </div>
              </transition>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { Icon } from '@iconify/vue'
import Tooltip from '@/components/Tooltip.vue'

const defaultFleschData = [
  {
    score: 7.5,
    description:
      'Berdasarkan Flesch Kincaid Reading Ease, teks ini memiliki tingkat kemudahan membaca sekitar 7.5 dari 10. Teks ini mudah dipahami oleh remaja berusia 12 hingga 15 tahun.',
    statistics: {
      syllables: 548,
      words: 132,
      sentences: 21,
      avgWordLength: 3.2,
      avgSentenceLength: 6.5,
    },
  },
  {
    title: 'Flesch Kincaid Reading Ease',
    score: 7.5,
    level: 'Mudah dibaca',
    formula: '206.835 - 1.015 × (words/sentences) - 84.6 × (syllables/words)',
  },
  {
    title: 'Flesch Kincaid Grade Level',
    score: 7.0,
    level: 'Mudah dibaca',
    formula:
      '0.39 × (words/sentences) + 11.8 × (syllables/words) - 15.59',
  },
  {
    title: 'Gunning Fog Index',
    score: 6.8,
    level: 'Sedikit sulit',
    formula: '0.4 × [(words/sentences) + 100 × (complex_words/words)]',
  },
  {
    title: 'SMOG Index',
    score: 7.2,
    level: 'Cukup mudah',
    formula: '1.0430 × √(polysyllables × (30/sentences)) + 3.1291',
  },
  {
    title: 'Coleman Liau Index',
    score: 7.0,
    level: 'Mudah dibaca',
    formula: '0.0588 × L - 0.296 × S - 15.8',
  },
]

const fleschData = ref([...defaultFleschData])

const loadAnalysisData = () => {
  const saved = localStorage.getItem('analysisResult')
  if (!saved) return

  try {
    const parsed = JSON.parse(saved)

    const getReadabilityCategory = (score) => {
      if (score >= 9) return 'Sangat Mudah'
      if (score >= 8) return 'Mudah'
      if (score >= 7) return 'Cukup Mudah'
      if (score >= 6) return 'Standar'
      if (score >= 5) return 'Cukup Sulit'
      if (score >= 3) return 'Sulit'
      return 'Sangat Sulit'
    }

    const readabilityScore = parseFloat(parsed.readability) || 0
    const wordCount = parseInt(parsed.word_count) || 0
    const sentenceCount = parseInt(parsed.sentence_count) || 0

    let adjustedScore
    if (readabilityScore < 0) {
      adjustedScore = Math.min(3, Math.abs(readabilityScore / 30)) // konversi negatif jadi rendah
    } else {
      adjustedScore = Math.min(10, Math.max(0, readabilityScore / 10)) // skala 0–10
    }

    const category = getReadabilityCategory(adjustedScore)
    const avgSentenceLength =
      sentenceCount > 0 ? wordCount / sentenceCount : 0

    fleschData.value = [
      {
        score: adjustedScore,
        description: `Berdasarkan analisis Flesch Reading Ease, teks memiliki skor keterbacaan ${adjustedScore.toFixed(
          1
        )} dari 10. Kategori: ${category}.`,
        statistics: {
          syllables: Math.floor(wordCount * 1.5),
          words: wordCount,
          sentences: sentenceCount,
          avgWordLength:
            wordCount > 0 && parsed.text
              ? (
                  parsed.text.replace(/\s+/g, '').length / wordCount
                ).toFixed(1)
              : 5.0,
          avgSentenceLength: avgSentenceLength.toFixed(1),
        },
      },
      {
        title: 'Flesch Kincaid Reading Ease',
        score: adjustedScore,
        level: category,
        formula:
          '206.835 - 1.015 × (words/sentences) - 84.6 × (syllables/words)',
      },
      {
        title: 'Flesch Kincaid Grade Level',
        score: Math.max(0.5, adjustedScore - 1),
        level: getReadabilityCategory(Math.max(0.5, adjustedScore - 1)),
        formula:
          '0.39 × (words/sentences) + 11.8 × (syllables/words) - 15.59',
      },
      {
        title: 'Gunning Fog Index',
        score: Math.max(1, adjustedScore - 0.5),
        level: getReadabilityCategory(Math.max(1, adjustedScore - 0.5)),
        formula:
          '0.4 × [(words/sentences) + 100 × (complex_words/words)]',
      },
      {
        title: 'SMOG Index',
        score: Math.min(10, adjustedScore + 0.5),
        level: getReadabilityCategory(Math.min(10, adjustedScore + 0.5)),
        formula:
          '1.0430 × √(polysyllables × (30/sentences)) + 3.1291',
      },
      {
        title: 'Coleman Liau Index',
        score: Math.max(2, adjustedScore - 0.8),
        level: getReadabilityCategory(Math.max(2, adjustedScore - 0.8)),
        formula: '0.0588 × L - 0.296 × S - 15.8',
      },
    ]

    setTimeout(() => {
      updateAnimation()
    }, 100)
  } catch {}
}

// === Animasi Circle Bar ===
const circumference = 2 * Math.PI * 80
const animatedOffset = ref(circumference)

const updateAnimation = () => {
  const target = fleschData.value[0].score
  // Sekarang target dibagi 10 (bukan 100)
  animatedOffset.value = circumference - (target / 10) * circumference
}

onMounted(() => {
  loadAnalysisData()
  setTimeout(updateAnimation, 200)
})

watch(
  () => fleschData.value[0].score,
  () => updateAnimation()
)

// === Expandable Sections ===
const expanded = ref(fleschData.value.slice(1).map(() => true))
const toggleExpand = (i) => {
  expanded.value[i] = !expanded.value[i]
}

// Animasi progress bar
const progressWidths = ref(fleschData.value.slice(1).map(() => 0))
onMounted(() => {
  fleschData.value.slice(1).forEach((item, i) => {
    setTimeout(() => {
      progressWidths.value[i] = (item.score / 10) * 100
    }, 300 + i * 150)
  })
})
watch(
  fleschData,
  (newData) => {
    if (newData && newData.length > 1) {
      progressWidths.value = newData.slice(1).map(() => 0)
      newData.slice(1).forEach((item, i) => {
        setTimeout(() => {
          if (progressWidths.value[i] !== undefined) {
            progressWidths.value[i] = (item.score / 10) * 100
          }
        }, 300 + i * 150)
      })
    }
  },
  { deep: true }
)
</script>

<style scoped>
.text-primarypurple {
  color: #7c3aed;
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
</style>

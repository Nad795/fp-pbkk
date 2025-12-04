<template>
  <section
    data-aos="fade-up"
    class="relative flex flex-col items-center px-6 py-16 overflow-hidden bg-primarywhite md:px-20"
  >
    <h2
      class="mb-20 text-3xl font-bold text-center md:text-4xl text-primarypurple"
      data-aos="fade-up"
    >
      Bagaimana cara menggunakan <br />
      <span class="text-primarypurple">ReadaSense?</span>
    </h2>

    <div class="z-10 grid w-full max-w-6xl grid-cols-1 gap-8 md:grid-cols-3">
      <div
        v-for="(card, index) in cards"
        :key="index"
        class="flex flex-col items-center p-6 text-center shadow-lg rounded-2xl"
        :style="{
          background:
            'linear-gradient(90deg,rgba(189, 215, 255, 1) 0%, rgba(245, 245, 247, 1) 100%)',
        }"
        data-aos="zoom-in-up"
        data-aos-delay="index * 200"
      >
        <div
          class="flex absolute -top-6 items-center justify-center w-16 h-16 rounded-full bg-[#5094F1] text-white font-bold mb-4"
          style="font-size: 36px; border: 2px solid var(--neutral-white); box-shadow: 0px 4px 4px rgba(0, 0, 0, 0.1);"
        >
          {{ index + 1 }}
        </div>
        <div
          class="flex flex-col items-center justify-start w-full max-w-sm p-6 text-left bg-transparent rounded-2xl"
        >
          <img
            :src="card.image"
            :alt="card.title"
            class="w-[200px] md:w-[250px] mb-4"
          />

          <h3 class="mb-2 text-xl font-semibold text-left text-black">
            {{ card.title }}
          </h3>

          <p class="text-base leading-relaxed text-left text-gray-700 text-justify">
            {{ card.description }}
          </p>
        </div>
      </div>
    </div>

    <div class="z-10 mt-12 p-6 space-y-8 rounded-2xl shadow-lg border-2 border-solid border-primarypurple" data-aos="fade-up" data-aos-delay="200">
      <div class="flex items-center justify-center">
        <img
          src="@/assets/image/warning.png"
          alt="Warning"
          class="w-[120px] h-auto mr-4"
        />

        <p class="text-left font-medium text-primarypurple text-justify">
          Perhitungan tingkat keterbacaan pada website ini menggunakan metode yang dikembangkan untuk bahasa Inggris sehingga tidak sepenuhnya sesuai untuk teks berbahasa Indonesia.
        </p>
      </div>
    </div>

    <div class="z-10 mt-32 mb-4 text-center" data-aos="fade-up" data-aos-delay="300">
      <p
        class="max-w-4xl mx-auto text-lg font-bold text-black md:text-xl"
        style="font-size: 36px; line-height: 40px;"
      >
        Dapatkan insight yang lebih
        <span class="underline-accent">cepat</span>,
        <span class="underline-accent">akurat</span> dan
        <span class="underline-accent">mendalam</span> dari setiap artikel. Uji
        <span class="text-primarypurple">ReadaSense</span> sekarang.
      </p>

      <button class="mt-6 bg-primarypurple hover:bg-[#5532d6] text-white font-semibold px-6 py-3 rounded-xl transition-all duration-300 inline-block text-center" @click="uploadDocument">
        Coba Sekarang!
      </button>
      <input
        type="file"
        ref="fileInput"
        style="display:none"
        accept=".pdf,.docx,.txt"
        @change="handleFileChange"
      />
    </div>
    <div
      class="absolute -bottom-10 -left-20 w-[600px] h-[600px] mesh-left"
    ></div>
    <div
      class="absolute -bottom-20 right-0 w-[500px] h-[500px] mesh-right"
    ></div>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const cards = [
  {
    image: new URL("@/assets/image/card-1.png", import.meta.url).href,
    title: "1. Upload Teks Berita",
    description:
      "Drop, paste dokumen (.docx, .pdf, .txt) atau URL berita yang ingin dianalisis ke ReadaSense. Hanya 1 dokumen yang dapat diupload dalam satu waktu.",
  },
  {
    image: new URL("@/assets/image/card-2.png", import.meta.url).href,
    title: "2. AI Menganalisis Teks",
    description:
      "AI ReadaSense membaca teks secara menyeluruh, mendeteksi nada emosional (positif, netral, atau negatif), menghitung tingkat keterbacaan, serta menyiapkan statistik kata yang mendetail.",
  },
  {
    image: new URL("@/assets/image/card-3.png", import.meta.url).href,
    title: "3. Lihat Hasil Analisis",
    description:
      "Nikmati hasil analisis: label sentimen, skor keterbacaan, statistik kata, serta visualisasi data dalam dashboard yang interaktif.",
  },
];

const emit = defineEmits<{
  (e: 'file-uploaded', file: File): void
}>()

const fileInput = ref<HTMLInputElement | null>(null)

const uploadDocument = () => {
  fileInput.value?.click()
}

const handleFileChange = (e: Event) => {
  const files = (e.target as HTMLInputElement).files
  if (files && files[0]) {
    emit('file-uploaded', files[0])
  }
}
</script>

<style scoped>
.mesh-left {
  background: radial-gradient(
    circle at 30% 0%,
    rgba(80, 148, 241, 0.5) 0%,
    transparent 70%
  );
  filter: blur(90px);
  border-radius: 1000px;
}

.mesh-right {
  background: radial-gradient(
    circle at 70% 80%,
    rgba(101, 64, 239, 0.4) 0%,
    transparent 70%
  );
  filter: blur(100px);
  border-radius: 1000 px;
}

.underline-accent {
  position: relative;
  display: inline-block;
}

.underline-accent::after {
  content: "";
  position: absolute;
  left: 0;
  bottom: 2px;
  width: 100%;
  height: 6px;
  background-color: #5094f1;
  border-radius: 6px;
  opacity: 0.8;
  z-index: -1;
}
</style>

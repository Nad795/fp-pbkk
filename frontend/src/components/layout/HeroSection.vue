<script setup lang="ts">
import { ref } from 'vue'

const emit = defineEmits(["file-uploaded"]);

const fileInput = ref<HTMLInputElement | null>(null)

const uploadDocument = () => {
  fileInput.value?.click()
}

const handleFileChange = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0];
    if (file) emit("file-uploaded", file);
}
</script>

<template>
  <section
    data-aos="fade-up"
    class="bg-primarywhite flex flex-col-reverse md:flex-row items-center justify-between px-6 md:px-16 py-12 relative overflow-hidden"
  >
    <div class="flex flex-col md:flex-1 text-center items-center md:text-left md:items-start z-10 md:ml-20">
      <h1 class="hero-title">Kenali nada dan makna di balik berita.</h1>
      <p class="hero-subtitle">ReadaSense membantu memahami sentimen serta tingkat keterbacaan dari teks berbahasa Inggris secara instan.</p>
      <div class="flex flex-col items-center">
        <button class="upload-btn" @click="uploadDocument">
          <svg class="upload-icon" viewBox="0 0 24 24" fill="none">
            <path d="M6 20L18 20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 16V4M12 4L15.5 7.5M12 4L8.5 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Upload Document
        </button>
        <span class="text-primarypurple text-sm">File yang diterima: .docx, .pdf, .txt</span>
      </div>
      <input
        type="file"
        ref="fileInput"
        style="display:none"
        accept=".pdf,.docx,.txt"
        @change="handleFileChange"
      />
    </div>

    <div class="flex-1 flex justify-center md:justify-end z-10 mb-6 md:mb-0">
      <img
        src="@/assets/image/main-image.png"
        alt="Main Image"
        class="w-[280px] md:w-[750px] drop-shadow-lg"
      />
    </div>

    <div class="absolute inset-0 -z-0 hero-mesh-bg"></div>
  </section>
</template>

<style scoped>
.hero-mesh-bg {
  background:
    radial-gradient(
      circle at 10% 45%,
      rgba(101, 64, 239, 0.5) 0%,
      transparent 40%
    );
  filter: blur(90px);
  border-radius: 1000px;
  width: 600px;
  height: 600px;
  margin: auto;
}

.hero-title {
  font-size: 48px;
  font-weight: 700;
  line-height: 1.2;
  color: var(--primary-purple);
  margin: 0 0 16px;
}

.hero-subtitle {
  font-size: 18px;
  font-weight: 600;
  line-height: 1.56;
  color: var(--neutral-black);
  margin: 0 0 24px;
}

.upload-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 16px 24px;
  background: var(--primary-purple);
  color: var(--neutral-white);
  border: none;
  border-radius: var(--border-radius);
  font-family: inherit;
  font-size: 18px;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 0.2s;
}

.upload-btn:hover {
  background: var(--dark-purple);
}

.upload-icon {
  width: 24px;
  height: 24px;
}

@media (max-width: 768px){
  .hero-title {
    font-size: 40px;
    font-weight: 700;
    line-height: 1.2;
    color: var(--primary-purple);
    margin: 0 0 16px;
    padding: 0 8px 0 8px;
  }

  .hero-subtitle {
    font-size: 16px;
    font-weight: 600;
    line-height: 1.56;
    color: var(--neutral-black);
    margin: 0 0 24px;
    padding: 0 8px 0 8px;
  }

  .upload-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px 24px;
    background: var(--primary-purple);
    color: var(--neutral-white);
    border: none;
    border-radius: var(--border-radius);
    font-family: inherit;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: background-color 0.2s;
  }

  .upload-icon {
    width: 20px;
    height: 20px;
  }
}
</style>

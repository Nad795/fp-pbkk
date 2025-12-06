<template>
  <div class="min-h-screen bg-primarywhite">
    <HeroSection 
      @file-uploaded="handleFileUpload"
      @text-uploaded="handleTextUpload"
    />
    <FiturSection />
    <CardSection 
      @file-uploaded="handleFileUpload"
      @text-uploaded="handleTextUpload"
    />
    <FileUpload 
      @file-uploaded="handleFileUpload" 
      @url-uploaded="handleUrlUpload"
      @text-uploaded="handleTextUpload"
    />

    <!-- Overlay loading -->
    <div
      v-if="loading"
      class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black bg-opacity-50 text-white backdrop-blur-sm"
    >
      <div class="animate-spin rounded-full h-16 w-16 border-4 border-white border-t-transparent mb-4"></div>
      <p class="text-lg font-medium">Sedang menganalisis dokumen...</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { defineAsyncComponent, ref, onMounted } from "vue"
import { useRouter } from 'vue-router'
import { ApiService } from '@/services/api'

const HeroSection = defineAsyncComponent(
  () => import("@/components/layout/HeroSection.vue")
);
const FiturSection = defineAsyncComponent(
  () => import("@/components/layout/FiturSection.vue")
);
const CardSection = defineAsyncComponent(
  () => import("@/components/layout/CardSection.vue")
);
const FileUpload = defineAsyncComponent(
  () => import("@/components/FileUpload.vue")
);

const router = useRouter()
const loading = ref(false)

onMounted(() => {
  // Jangan clear localStorage agar bisa debug
  console.log('Home component mounted')
  console.log('Current localStorage:', localStorage.getItem('analysisResult'))
})

const handleFileUpload = async (file: File) => {
  if (!file) return

  // Validate extension/MIME before doing anything
  const fileName = file.name || '';
  const ext = fileName.substring(fileName.lastIndexOf('.')).toLowerCase();
  const allowed = ['.pdf', '.docx', '.txt'];
  if (!allowed.includes(ext)) {
    alert('Format file tidak didukung. Gunakan .pdf, .docx, atau .txt');
    return;
  }

  console.log("File diterima:", file);
  
  try {
    loading.value = true
    
    // Check backend health first
    const isBackendHealthy = await ApiService.healthCheck()
    if (!isBackendHealthy) {
      throw new Error('Backend tidak dapat dijangkau. Pastikan server berjalan di localhost:8000')
    }

    // Analisis file menggunakan service
    const result = await ApiService.analyzeFile(file)
    console.log('Hasil analisis:', result)

    if (!result.success) {
      throw new Error('Analisis gagal')
    }

    // Simpan hasil ke localStorage
    localStorage.setItem('analysisResult', JSON.stringify(result))

    // Redirect ke hasil analisis
    router.push('/analisis/readability')
  } catch (error) {
    console.error('Error:', error)
    let errorMessage = error.message
    
    // Handle specific error cases for file upload
    if (error.message.includes('400')) {
      errorMessage = 'Format file tidak didukung atau file bermasalah. Gunakan file .txt, .pdf, atau .docx yang valid'
    } else if (error.message.includes('413')) {
      errorMessage = 'File terlalu besar. Maksimal 10MB'
    } else if (error.message.includes('network') || error.message.includes('fetch')) {
      errorMessage = 'Koneksi bermasalah. Pastikan backend berjalan di localhost:8000'
    }
    
    alert(`Terjadi kesalahan: ${errorMessage}`)
  } finally {
    loading.value = false
  }
}

const handleUrlUpload = async (url: string) => {
  if (!url) return
  console.log("URL diterima:", url);
  
  try {
    loading.value = true
    
    // Check backend health first
    const isBackendHealthy = await ApiService.healthCheck()
    if (!isBackendHealthy) {
      throw new Error('Backend tidak dapat dijangkau. Pastikan server berjalan di localhost:8000')
    }

    // Analisis URL sebagai teks
    const result = await ApiService.analyzeText(`${url}`)
    console.log('Hasil analisis URL:', result)

    if (!result.success) {
      throw new Error('Analisis URL gagal')
    }

    localStorage.setItem('analysisResult', JSON.stringify(result))
    router.push('/analisis/readability')
  } catch (error) {
    console.error('Error:', error)
    alert(`Terjadi kesalahan: ${error.message}`)
  } finally {
    loading.value = false
  }
};

const handleTextUpload = async (text: string) => {
  if (!text || text.length < 10) {
    alert('Teks terlalu pendek untuk dianalisis')
    return
  }
  
  console.log("Teks diterima:", text.substring(0, 100) + '...');
  
  try {
    loading.value = true
    
    // Check backend health first
    const isBackendHealthy = await ApiService.healthCheck()
    if (!isBackendHealthy) {
      throw new Error('Backend tidak dapat dijangkau. Pastikan server berjalan di localhost:8000')
    }

    // Analisis teks langsung
    const result = await ApiService.analyzeText(text)
    console.log('Hasil analisis teks:', result)

    if (!result.success) {
      throw new Error('Analisis teks gagal')
    }

    localStorage.setItem('analysisResult', JSON.stringify(result))
    router.push('/analisis/readability')
  } catch (error) {
    console.error('Error:', error)
    alert(`Terjadi kesalahan: ${error.message}`)
  } finally {
    loading.value = false
  }
};
</script>

<style scoped></style>

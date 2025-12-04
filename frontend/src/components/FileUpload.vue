<template>
    <transition name="fade">
        <!-- Overlay fullscreen -->
        <div
        v-if="isDragging"
        class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/40 backdrop-blur-md text-white"
        @dragenter.prevent
        @dragover.prevent
        @dragleave="onDragLeave"
        @drop.prevent="onDrop"
        >
        <!-- Teks tengah -->
        <div
            class="flex flex-col items-center justify-center w-full h-full border-4 border-dashed border-white/70 rounded-none"
        >
            <p class="text-3xl font-bold mb-3">Lepaskan file di mana saja</p>
        </div>

        <!-- Input file tersembunyi -->
        <input ref="fileInput" type="file" accept=".pdf,.docx,.txt" hidden @change="onFileSelect" />
        </div>
    </transition>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from "vue";

const emit = defineEmits(["file-uploaded", "url-uploaded", "text-uploaded"]);

const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
let dragCounter = 0;

// Allowed file types
const allowedExtensions = ['.pdf', '.docx', '.txt'];
const allowedMIMEs = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain'
];

const getFileExtension = (name: string) => {
    const idx = name.lastIndexOf('.');
    return idx >= 0 ? name.slice(idx).toLowerCase() : '';
}

const isAllowedFile = (file?: File | null) => {
    if (!file) return false;
    const ext = getFileExtension(file.name || '');
    if (allowedExtensions.includes(ext)) return true;
    if (file.type && allowedMIMEs.includes(file.type)) return true;
    return false;
}

// ===== DRAG & DROP (overlay) =====
const handleGlobalDragEnter = (e: DragEvent) => {
    e.preventDefault();
    dragCounter++;
    isDragging.value = true;
};

const handleGlobalDragLeave = (e: DragEvent) => {
    e.preventDefault();
    dragCounter--;
    if (dragCounter <= 0) {
        isDragging.value = false;
        dragCounter = 0;
    }
};

const onDragLeave = () => {
    dragCounter = 0;
    isDragging.value = false;
};

const onDrop = (e: DragEvent) => {
    isDragging.value = false;
    dragCounter = 0;
    const file = e.dataTransfer?.files?.[0];
    if (file) {
        if (!isAllowedFile(file)) {
            alert('Format file tidak didukung. Gunakan .pdf, .docx, atau .txt');
            return;
        }
        emit("file-uploaded", file);
    }
};

const onFileSelect = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        if (!isAllowedFile(file)) {
            alert('Format file tidak didukung. Gunakan .pdf, .docx, atau .txt');
            return;
        }
        emit("file-uploaded", file);
    }
};

// ===== PASTE FILE / URL / TEXT =====
const onPaste = (e: ClipboardEvent) => {
  // Prioritaskan file dulu
    const pastedFile = e.clipboardData?.files?.[0];
    if (pastedFile) {
        if (!isAllowedFile(pastedFile)) {
            alert('Format file yang ditempel tidak didukung. Gunakan .pdf, .docx, atau .txt');
            return;
        }
        emit("file-uploaded", pastedFile);
        return;
    }

  // Kalau tidak ada file, coba ambil teks
    const text = e.clipboardData?.getData("text");
    if (text) {
        if (text.startsWith("http")) {
            emit("url-uploaded", text.trim());
        } else if (text.length > 10) {
            // Jika teks cukup panjang, anggap sebagai teks untuk dianalisis
            emit("text-uploaded", text.trim());
        }
    }
};

// ===== Mount & Unmount =====
onMounted(() => {
    window.addEventListener("dragenter", handleGlobalDragEnter);
    window.addEventListener("dragleave", handleGlobalDragLeave);
    window.addEventListener("paste", onPaste);
});

onUnmounted(() => {
    window.removeEventListener("dragenter", handleGlobalDragEnter);
    window.removeEventListener("dragleave", handleGlobalDragLeave);
    window.removeEventListener("paste", onPaste);
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
.bg-primary-purple {
    background-color: #8b5cf6;
}
</style>

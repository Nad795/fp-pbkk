<template>
    <div
        class="fixed bottom-6 left-6 z-50 flex items-center justify-center group"
    >
        <!-- Tombol utama -->
        <button
        @click="goToSurvey"
        class="relative flex items-center gap-2 px-5 py-3 text-white rounded-full shadow-lg transition-all duration-300 bg-primarypurple hover:bg-purple-700 focus:outline-none focus:ring-4 focus:ring-purple-300"
        id="survey-button"
        >
        <Icon icon="mdi:clipboard-text-outline" class="text-xl" />
        <span class="font-semibold hidden sm:inline">Isi Survei</span>
        </button>
    </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";

const goToSurvey = () => {
    window.open("https://forms.gle/VQP7MSiLKfpBd14Z6", "_blank");
};
</script>

<style scoped>
.bg-primary-purple {
    background-color: var(--primary-purple);
}
</style>

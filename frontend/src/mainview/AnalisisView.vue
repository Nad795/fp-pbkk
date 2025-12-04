<template>
  <div class="min-h-screen bg-primarywhite">
    <Navbar />

    <div
      class="flex flex-col items-center px-12 mx-auto mt-5 text-left md:justify-between md:flex-row md:items-stretch"
    >
      <h1 class="mb-6 text-3xl font-bold text-primarypurple">Hasil Analisis</h1>

      <div class="flex mb-10 space-x-4">
        <router-link
          to="/analisis/readability"
          class="flex items-center gap-2 px-5 py-2 font-medium transition-all duration-300 rounded-lg"
          :class="{
            'bg-primarypurple text-white shadow-md': route.name === 'Readable',
            'text-primarypurple hover:bg-purple-50': route.name !== 'Readable',
          }"
        >
          <Icon
            icon="mdi:glasses"
            class="text-lg"
            :class="
              route.name === 'Readable' ? 'text-white' : 'text-primarypurple'
            "
          />
          Readability
        </router-link>

        <router-link
          to="/analisis/sentiment"
          class="flex items-center gap-2 px-5 py-2 font-medium transition-all duration-300 rounded-lg"
          :class="{
            'bg-primarypurple text-white shadow-md': route.name === 'Sentiment',
            'text-primarypurple hover:bg-purple-50': route.name !== 'Sentiment',
          }"
        >
          <Icon
            icon="mdi:emoticon-neutral-outline"
            class="text-lg"
            :class="
              route.name === 'Sentiment' ? 'text-white' : 'text-primarypurple'
            "
          />
          Sentiment
        </router-link>
      </div>
    </div>

    <div class="px-6 pb-20 mx-auto">
      <router-view />
    </div>

    <Footer />
  </div>
</template>

<script setup>
import Navbar from "@/components/layout/Navbar.vue";
import Footer from "@/components/layout/Footer.vue";
import { useRoute } from "vue-router";

const route = useRoute();
</script>

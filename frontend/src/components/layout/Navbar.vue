<template>
  <header class="sticky top-0 z-20 bg-primarywhite">
    <div
      class="relative flex items-center justify-between p-8 space-x-0 md:justify-start md:space-x-24 md:items-start lg:px-12"
    >
      <div>
        <img :src="logo" alt="logo" class="w-[194px] h-[36px]" @click="goHome"/>
      </div>

      <!-- mobile button -->
      <div class="z-30 md:hidden">
        <button
          class="block focus:outline-none"
          @click="isMenuOpen = !isMenuOpen"
        >
          <span v-if="isMenuOpen" class="text-5xl text-black">
            <Icon icon="material-symbols:close" />
          </span>
          <span v-else class="text-5xl text-black">
            <Icon icon="material-symbols:menu" />
          </span>
        </button>
      </div>

      <!-- navbar link -->
      <nav
        :class="[
          `fixed inset-0 z-20 flex flex-col mt-0 md:mt-2 items-center justify-center bg-transparent md:relative md:flex md:justify-between md:flex-row
                ${isMenuOpen ? 'block' : 'hidden'}`,
        ]"
      >
        <ul
          class="flex flex-col items-center space-y-5 md:flex-row md:space-x-16 md:space-y-0"
        >
          <li v-for="item in menu" :key="item.name">
            <a
              :href="item.href"
              class="block font-semibold transition ease-linear md:text-m text-primarypurple"
              @click="scrollToSection(item.href)"
            >
              {{ item.name }}
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </header>
</template>
<script setup>
import logo from "@/assets/image/logo.png";
import { Icon } from "@iconify/vue";
import { ref } from "vue";

const isMenuOpen = ref(false);
const menu = ref([
  { name: "Home", href: "/" },
]);

const scrollToSection = (href) => {
  isMenuOpen.value = false;
  const section = document.querySelector(href);
  if (section) {
    section.scrollIntoView({ behavior: "smooth" });
  }
};

const goHome = () => {
    window.location.href = '/'
}
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap');

:root{
  --app-font: 'Plus Jakarta Sans', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
}

html, body {
  font-family: var(--app-font);
}
</style>
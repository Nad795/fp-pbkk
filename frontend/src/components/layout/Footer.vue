<script setup lang="ts">
import { ref } from "vue";
import logo from "@/assets/image/logoputih.png";

const goHome = () => {
    window.location.href = '/'
}

const address = ref([
  {
    icon: "fa-location-dot",
    text: "Kampus Institut Teknologi Sepuluh Nopember, Keputih, Sukolilo, Surabaya, East Java 60117",
  },
  { icon: "fa-phone", text: "031-5994251-54, 5947274, 5945472" },
  { icon: "fa-envelope", text: "humas@its.ac.id" },
]);
</script>

<template>
  <footer
    class="relative z-10 bg-[#2A1B64] pt-10 pb-10 lg:pt-[60px] lg:pb-20 px-10 md:px-20"
  >
    <div class="container mx-auto">
      <div class="flex flex-wrap -mx-4 gap-2">
        <div class="w-full px-4 sm:w-2/3 lg:w-3/12">
          <div class="w-full mb-10">
            <a
              class="mb-6 inline-block max-w-[160px]"
              @click="goHome"
            >
              <img :src="logo" alt="logo" class="w-[194px]" />
            </a>
            <p class="mb-4 text-white text-normal">
              Copyright © 2025 ReadaSense. All Rights Reserved.
            </p>

            <h4 class="mb-4 text-lg font-normal text-white">Social Media</h4>
            <div class="flex items-center mb-6">
              <a
                href="https://www.facebook.com/InstitutTeknologiSepuluhNopember/"
                class="flex items-center justify-center w-8 h-8 mr-3 text-white border rounded-full hover:border-primary hover:bg-primary hover:text-white"
              >
                <i class="text-sm fab fa-facebook-f"></i>
              </a>

              <a
                href="https://x.com/its_campus"
                class="flex items-center justify-center w-8 h-8 mr-3 text-white border rounded-full hover:border-primary hover:bg-primary hover:text-white"
              >
                <i class="text-sm fab fa-twitter"></i>
              </a>

              <a
                href="https://www.youtube.com/@itseurekatv"
                class="flex items-center justify-center w-8 h-8 mr-3 text-white border rounded-full hover:border-primary hover:bg-primary hover:text-white"
              >
                <i class="text-sm fab fa-youtube"></i>
              </a>

              <a
                href="https://www.linkedin.com/school/its-campus"
                class="flex items-center justify-center w-8 h-8 mr-3 text-white border rounded-full hover:border-primary hover:bg-primary hover:text-white"
              >
                <i class="text-sm fab fa-linkedin-in"></i>
              </a>
            </div>
          </div>
        </div>

        <div class="w-full px-4 sm:w-1/2 lg:w-4/12">
          <div class="w-full mb-10">
            <h4 class="text-[28px] font-bold text-white mb-9">Alamat</h4>
            <ul class="space-y-4">
              <li
                v-for="(item, i) in address"
                :key="i"
                class="flex items-start space-x-3"
              >
                <i
                  :class="['fa-solid', item.icon]"
                  class="text-[#00FCDB] text-lg mt-1"
                ></i>
                <span class="text-base leading-relaxed text-white">
                  {{ item.text }}
                </span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div>
      <span class="absolute left-0 bottom-0 z-[-1]">
        <svg
          width="217"
          height="229"
          viewBox="0 0 217 229"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            d="M-64 140.5C-64 62.904 -1.096 1.90666e-05 76.5 1.22829e-05C154.096 5.49924e-06 217 62.904 217 140.5C217 218.096 154.096 281 76.5 281C-1.09598 281 -64 218.096 -64 140.5Z"
            fill="url(#paint0_linear_1179_5)"
          />
          <defs>
            <linearGradient
              id="paint0_linear_1179_5"
              x1="76.5"
              y1="281"
              x2="76.5"
              y2="1.22829e-05"
              gradientUnits="userSpaceOnUse"
            >
              <stop stop-color="#3056D3" stop-opacity="0.08" />
              <stop offset="1" stop-color="#C4C4C4" stop-opacity="0" />
            </linearGradient>
          </defs>
        </svg>
      </span>
    </div>
  </footer>
</template>

<style>
@import url("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css");
</style>

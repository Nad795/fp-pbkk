import { createRouter, createWebHistory } from "vue-router";
import Home from "@/views/Home.vue";
import Readability from "@/views/Readability.vue";
import MainView from "@/mainview/MainView.vue";
import Sentiment from "../views/Sentiment.vue";
import AnalisisView from "../mainview/AnalisisView.vue";

const routes = [
  {
    path: "/",
    component: MainView,
    children: [{ path: "", name: "Home", component: Home }],
  },
  {
    path: "/analisis",
    component: AnalisisView,
    children: [
      { path: "readability", name: "Readable", component: Readability },
      { path: "sentiment", name: "Sentiment", component: Sentiment },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,

  scrollBehavior(to, from, savedPosition) {
    // jika user menekan tombol "Back" atau "Forward"
    if (savedPosition) {
      return savedPosition
    } else {
      // posisi scroll top (0,0)
      return { top: 0 }
    }
  }
});

export default router;

import { initRouter, navigateTo } from "./router.js";
import { getCurrentUser, logout } from "./auth.js";

export const appState = {
  user: null,
  conditions: [],
  collections: []
};

document.addEventListener("DOMContentLoaded", async () => {
  try {
    appState.user = await getCurrentUser();
  } catch {
    appState.user = null;
  }

  setupNavigation();
  updateAuthUI();
  initRouter();

  navigateTo("home");
});

function setupNavigation() {
  document.querySelectorAll("[data-route]").forEach((element) => {
    element.addEventListener("click", () => {
      const route = element.dataset.route;

      if (!route) {
        return;
      }

      navigateTo(route);
    });
  });

  const logoutBtn = document.getElementById("logoutBtn");

  logoutBtn?.addEventListener("click", async () => {
    await logout();
    appState.user = null;
    updateAuthUI();
    navigateTo("home");
  });
}

export function updateAuthUI() {
  document.querySelectorAll("[data-auth-only]").forEach((el) => {
    el.hidden = !appState.user;
  });

  document.querySelectorAll("[data-guest-only]").forEach((el) => {
    el.hidden = !!appState.user;
  });
}
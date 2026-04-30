import { renderHome } from "./search.js";
import { renderLogin } from "./auth.js";
import { renderCollections } from "./collections.js";
import { renderRecognition } from "./recognition.js";

const app = () => document.getElementById("app");

const routes = {
  home: renderHome,
  login: renderLogin,
  collections: renderCollections,
  recognition: renderRecognition
};

export function initRouter() {
  window.addEventListener("popstate", () => {
    const route = history.state?.route || "home";
    renderRoute(route);
  });
}

export function navigateTo(route) {
  if (!routes[route]) {
    route = "home";
  }

  history.pushState({ route }, "", `#${route}`);
  renderRoute(route);
}

function renderRoute(route) {
  const renderer = routes[route] || routes.home;
  const appElement = app();

  if (!appElement) {
    return;
  }

  appElement.innerHTML = "";
  renderer(appElement);

  updateActiveNav(route);
}

function updateActiveNav(route) {
  document.querySelectorAll("[data-route]").forEach((el) => {
    el.classList.toggle("active", el.dataset.route === route);
  });
}
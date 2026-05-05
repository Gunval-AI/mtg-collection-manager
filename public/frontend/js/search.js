import { apiFetch } from "./api.js";
import { appState, openPrintDetailModal } from "./app.js";

function openModal(overlay) {
  document.querySelector(".modal-overlay")?.remove();
  document.body.appendChild(overlay);
  document.body.classList.add("modal-open");
}

function closeModal(overlay) {
  overlay.remove();
  document.body.classList.remove("modal-open");
}

export function renderHome(container) {
  container.innerHTML = `
    <section class="hero">
      <h1>MTG Collection Manager</h1>
      <p>Busca cartas e impresiones de Magic.</p>

      <form id="searchForm" class="search-box">
        <input name="name" type="text" placeholder="Buscar carta..." required />
        <button type="submit">Buscar</button>
      </form>
    </section>

    <section id="searchResults" class="card-grid"></section>
  `;

  document.getElementById("searchForm").addEventListener("submit", async (event) => {
    event.preventDefault();

    const form = event.target;
    const submitButton = form.querySelector("button[type='submit']");
    const name = new FormData(form).get("name");
    const resultsContainer = document.getElementById("searchResults");

    resultsContainer.innerHTML = `<div class="loading">Buscando cartas...</div>`;
    submitButton.disabled = true;
    submitButton.textContent = "Buscando...";

    try {
      const response = await apiFetch(`/prints/search?name=${encodeURIComponent(name)}`);
      renderPrintResults(resultsContainer, response.data);
    } catch (error) {
      resultsContainer.innerHTML = `<p class="error">${error.message}</p>`;
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = "Buscar";
    }
  });
}

function renderPrintResults(container, prints) {
  if (!prints.length) {
    container.innerHTML = `<p class="empty-state">No se encontraron resultados.</p>`;
    return;
  }

  container.innerHTML = prints.map((print, index) => `
    <article class="print-card" data-print-id="${print.id}">
      <img 
        class="clickable-print-image"
        data-action="open-print-detail"
        data-print-index="${index}"
        src="${print.imagenSmall || print.imagenNormal || './assets/card-placeholder.png'}" 
        alt="${print.nombreCarta}" 
      />

      <div>
        <h3>${print.nombreCarta}</h3>
        <p>${print.nombreEdicion}</p>
        <p class="rarity ${print.rareza.toLowerCase()}">${print.rareza}</p>
        <p>${print.codigoEdicion} · #${print.numeroColeccion}</p>

        ${appState.user ? `
          <button class="small-button" data-action="show-search-add-copy-form">
            Añadir copia
          </button>
        ` : ""}
      </div>
    </article>
  `).join("");

  container.querySelectorAll("[data-action='show-search-add-copy-form']").forEach((button) => {
    button.addEventListener("click", async (event) => {
      event.stopPropagation();

      const card = event.target.closest(".print-card");
      const printId = card.dataset.printId;

      await openSearchAddCopyModal(printId);
    });
  });

  container.querySelectorAll("[data-action='open-print-detail']").forEach((image) => {
    image.addEventListener("click", (event) => {
      event.stopPropagation();

      const printIndex = Number(event.target.dataset.printIndex);
      openPrintDetailModal(prints[printIndex]);
    });
  });
}

async function openSearchAddCopyModal(printId) {
  const overlay = document.createElement("div");
  overlay.className = "modal-overlay";

  overlay.innerHTML = `
    <div class="app-modal">
      <div class="modal-header">
        <h2>Añadir copia</h2>
        <button type="button" class="secondary-button small-button" data-action="close-modal">
          ✕
        </button>
      </div>

      <div id="searchAddCopyModalContent">
        <div class="loading">Cargando formulario...</div>
      </div>
    </div>
  `;

  openModal(overlay);

  const handleCloseModal = () => closeModal(overlay);

  overlay.querySelector("[data-action='close-modal']").addEventListener("click", handleCloseModal);

  overlay.addEventListener("click", (event) => {
    if (event.target === overlay) {
      handleCloseModal();
    }
  });

  const content = overlay.querySelector("#searchAddCopyModalContent");

  try {
    const [collectionsResponse, conditionsResponse] = await Promise.all([
      apiFetch("/collections"),
      apiFetch("/conditions")
    ]);

    const collections = collectionsResponse.data;
    const conditions = conditionsResponse.data;

    content.innerHTML = `
      <form id="searchAddCopyForm" class="form">
        <label>
          Colección
          <select name="collectionId" required>
            ${collections.map((collection) => `
              <option value="${collection.id}">
                ${collection.nombre}
              </option>
            `).join("")}
          </select>
        </label>

        <label>
          Condición
          <select name="condicionId" required>
            ${conditions.map((condition) => `
              <option value="${condition.id}">
                ${condition.descripcion}
              </option>
            `).join("")}
          </select>
        </label>

        <label>
          Idioma
          <select name="idioma" required>
            <option value="EN">Inglés</option>
            <option value="ES">Español</option>
          </select>
        </label>

        <label class="checkbox-label modal-checkbox">
          <input type="checkbox" name="esFoil" />
          Foil
        </label>

        <div class="modal-actions">
          <button type="submit">Guardar copia</button>
          <button type="button" class="secondary-button" data-action="cancel-modal">
            Cancelar
          </button>
        </div>

        <p class="message"></p>
      </form>
    `;

    const form = content.querySelector("#searchAddCopyForm");
    const message = form.querySelector(".message");

    form.querySelector("[data-action='cancel-modal']").addEventListener("click", handleCloseModal);

    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      const formData = new FormData(form);
      const submitButton = form.querySelector("button[type='submit']");

      message.textContent = "";
      message.className = "message";
      submitButton.disabled = true;
      submitButton.textContent = "Guardando...";

      try {
        await apiFetch("/copies", {
          method: "POST",
          body: JSON.stringify({
            collectionId: Number(formData.get("collectionId")),
            impresionId: Number(printId),
            condicionId: Number(formData.get("condicionId")),
            idioma: formData.get("idioma"),
            esFoil: formData.get("esFoil") === "on"
          })
        });

        message.textContent = "Copia añadida correctamente.";
        message.className = "message success";

        setTimeout(() => {
          handleCloseModal();
        }, 700);
      } catch (error) {
        message.textContent = error.message;
        message.className = "message error";
      } finally {
        submitButton.disabled = false;
        submitButton.textContent = "Guardar copia";
      }
    });
  } catch (error) {
    content.innerHTML = `<p class="error">${error.message}</p>`;
  }
}
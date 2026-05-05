import { apiFetch } from "./api.js";
import { openPrintDetailModal } from "./app.js";

let currentCollection = null;

const COLLECTION_PAGE_SIZE = 20;

let collectionSummaryState = {
  prints: [],
  collectionId: null,
  currentPage: 1,
  searchTerm: "",
  rarityFilter: "",
  editionFilter: ""
};

function openModal(overlay) {
  document.querySelector(".modal-overlay")?.remove();
  document.body.appendChild(overlay);
  document.body.classList.add("modal-open");

  const handleEsc = (event) => {
    if (event.key === "Escape") {
      closeModal(overlay);
      document.removeEventListener("keydown", handleEsc);
    }
  };

  document.addEventListener("keydown", handleEsc);

  overlay._handleEsc = handleEsc;
}

function closeModal(overlay) {
  overlay.remove();
  document.body.classList.remove("modal-open");

  if (overlay._handleEsc) {
    document.removeEventListener("keydown", overlay._handleEsc);
  }
}

export async function renderCollections(container) {
  container.innerHTML = `
    <section class="panel">
      <div class="section-header">
        <div>
          <h1>Mis colecciones</h1>
          <p>Gestiona tus colecciones de cartas.</p>
        </div>

        <button id="createCollectionBtn">Nueva colección</button>
      </div>

      <div id="collectionsContent" class="collection-list">
        <div class="loading">Cargando colecciones...</div>
      </div>
    </section>
  `;

  await loadCollections();

  document.getElementById("createCollectionBtn").addEventListener("click", () => {
    openCreateCollectionModal();
  });
}

async function loadCollections() {
  const content = document.getElementById("collectionsContent");

  try {
    const response = await apiFetch("/collections");
    renderCollectionList(content, response.data);
  } catch (error) {
    content.innerHTML = `<p class="error">${error.message}</p>`;
  }
}

function renderCollectionList(container, collections) {
  if (!collections.length) {
    container.innerHTML = `<p class="empty-state">Todavía no tienes colecciones.</p>`;
    return;
  }

  container.innerHTML = collections.map((collection) => `
    <article class="collection-card" data-collection-id="${collection.id}">
      <div>
        <h3>
          ${collection.nombre}
          ${collection.esPrincipal ? `<span class="collection-badge">Principal</span>` : ""}
        </h3>

        <p>${collection.descripcion || "Sin descripción"}</p>
        <small>Creada el ${collection.fechaCreacion}</small>
      </div>

      <div class="collection-actions">
        <button class="secondary-button" data-action="open-collection">
          Ver colección
        </button>

        ${collection.esPrincipal ? "" : `
          <button class="danger-button" data-action="delete-collection">
            Eliminar
          </button>
        `}
      </div>
    </article>
  `).join("");

  container.querySelectorAll("[data-action='open-collection']").forEach((button) => {
    button.addEventListener("click", async (event) => {
      const card = event.target.closest(".collection-card");
      const collectionId = card.dataset.collectionId;
      const collectionName = card.querySelector("h3").childNodes[0].textContent.trim();

      currentCollection = {
        id: collectionId,
        nombre: collectionName
      };

      await renderCollectionDetail(collectionId, collectionName);
    });
  });

  container.querySelectorAll("[data-action='delete-collection']").forEach((button) => {
    button.addEventListener("click", (event) => {
      const card = event.target.closest(".collection-card");

      const collection = {
        id: Number(card.dataset.collectionId),
        nombre: card.querySelector("h3").childNodes[0].textContent.trim()
      };

      openDeleteCollectionModal(collection);
    });
  });
}

async function renderCollectionDetail(collectionId, collectionName) {
  const main = document.getElementById("app");

  currentCollection = {
    id: collectionId,
    nombre: collectionName
  };

  main.innerHTML = `
    <section class="panel wide-panel">
      <div class="section-header">
        <div>
          <button class="secondary-button small-button" id="backToCollectionsBtn">
            ← Volver
          </button>
          <h1>${collectionName}</h1>
          <p>Impresiones agrupadas dentro de esta colección.</p>
        </div>

        <button id="openCollectionSearchModalBtn">
          Añadir carta
        </button>
      </div>

      <div id="collectionSummary">
        <div class="loading">Cargando colección...</div>
      </div>
    </section>
  `;

  document.getElementById("backToCollectionsBtn").addEventListener("click", () => {
    renderCollections(main);
  });

  document.getElementById("openCollectionSearchModalBtn").addEventListener("click", () => {
    openCollectionSearchModal();
  });

  try {
    const response = await apiFetch(`/collections/${collectionId}/summary`);

    collectionSummaryState = {
      prints: response.data,
      collectionId,
      currentPage: 1,
      searchTerm: "",
      rarityFilter: "",
      editionFilter: ""
    };

    renderCollectionSummaryPage();
  } catch (error) {
    document.getElementById("collectionSummary").innerHTML = `<p class="error">${error.message}</p>`;
  }
}

function getFilteredPrints() {
  const searchTerm = collectionSummaryState.searchTerm.trim().toLowerCase();
  const rarityFilter = collectionSummaryState.rarityFilter;
  const editionFilter = collectionSummaryState.editionFilter;

  return collectionSummaryState.prints.filter((print) => {
    const searchableText = [
      print.nombreCarta,
      print.nombreEdicion,
      print.codigoEdicion,
      print.numeroColeccion,
      print.rareza
    ].join(" ").toLowerCase();

    const matchesSearch = !searchTerm || searchableText.includes(searchTerm);
    const matchesRarity = !rarityFilter || print.rareza === rarityFilter;
    const matchesEdition = !editionFilter || print.codigoEdicion === editionFilter;

    return matchesSearch && matchesRarity && matchesEdition;
  });
}

function getAvailableEditions() {
  const editions = new Map();

  collectionSummaryState.prints.forEach((print) => {
    if (print.codigoEdicion) {
      editions.set(print.codigoEdicion, print.nombreEdicion || print.codigoEdicion);
    }
  });

  return Array.from(editions.entries()).sort((a, b) => a[1].localeCompare(b[1]));
}

function getAvailableRarities() {
  return Array.from(
    new Set(collectionSummaryState.prints.map((print) => print.rareza).filter(Boolean))
  ).sort();
}

function renderCollectionSummaryPage() {
  const container = document.getElementById("collectionSummary");
  const { prints, collectionId, currentPage, searchTerm, rarityFilter, editionFilter } = collectionSummaryState;

  if (!prints.length) {
    container.innerHTML = `<p class="empty-state">Esta colección todavía no tiene cartas.</p>`;
    return;
  }

  const filteredPrints = getFilteredPrints();
  const totalPages = Math.max(1, Math.ceil(filteredPrints.length / COLLECTION_PAGE_SIZE));
  const safeCurrentPage = Math.min(currentPage, totalPages);

  collectionSummaryState.currentPage = safeCurrentPage;

  const startIndex = (safeCurrentPage - 1) * COLLECTION_PAGE_SIZE;
  const endIndex = startIndex + COLLECTION_PAGE_SIZE;
  const visiblePrints = filteredPrints.slice(startIndex, endIndex);
  const editions = getAvailableEditions();
  const rarities = getAvailableRarities();

  container.innerHTML = `
    <div class="collection-filters">
      <input 
        type="search" 
        placeholder="Buscar en esta colección..." 
        value="${searchTerm}"
        data-action="filter-search"
      />

      <select data-action="filter-rarity">
        <option value="">Todas las rarezas</option>
        ${rarities.map((rarity) => `
          <option value="${rarity}" ${rarityFilter === rarity ? "selected" : ""}>
            ${rarity}
          </option>
        `).join("")}
      </select>

      <select data-action="filter-edition">
        <option value="">Todas las ediciones</option>
        ${editions.map(([code, name]) => `
          <option value="${code}" ${editionFilter === code ? "selected" : ""}>
            ${name} (${code})
          </option>
        `).join("")}
      </select>
    </div>

    ${filteredPrints.length ? `
      <div class="collection-pagination">
        <span>
          Mostrando ${startIndex + 1}-${Math.min(endIndex, filteredPrints.length)} de ${filteredPrints.length} impresiones
        </span>

        <div class="pagination-actions">
          <button class="secondary-button small-button" data-action="previous-page" ${safeCurrentPage === 1 ? "disabled" : ""}>
            Anterior
          </button>

          <span>Página ${safeCurrentPage} de ${totalPages}</span>

          <button class="secondary-button small-button" data-action="next-page" ${safeCurrentPage === totalPages ? "disabled" : ""}>
            Siguiente
          </button>
        </div>
      </div>

      <div id="collectionSummaryGrid" class="summary-grid"></div>
    ` : `
      <p class="empty-state">No hay cartas que coincidan con los filtros.</p>
    `}
  `;

  container.querySelector("[data-action='filter-search']").addEventListener("input", (event) => {
    collectionSummaryState.searchTerm = event.target.value;
    collectionSummaryState.currentPage = 1;

    renderCollectionSummaryPage();

    const input = document.querySelector("[data-action='filter-search']");
    input?.focus();
    input.setSelectionRange(input.value.length, input.value.length);
  });

  container.querySelector("[data-action='filter-rarity']").addEventListener("change", (event) => {
    collectionSummaryState.rarityFilter = event.target.value;
    collectionSummaryState.currentPage = 1;
    renderCollectionSummaryPage();
  });

  container.querySelector("[data-action='filter-edition']").addEventListener("change", (event) => {
    collectionSummaryState.editionFilter = event.target.value;
    collectionSummaryState.currentPage = 1;
    renderCollectionSummaryPage();
  });

  if (filteredPrints.length) {
    renderCollectionSummary(document.getElementById("collectionSummaryGrid"), visiblePrints, collectionId);
  }

  container.querySelector("[data-action='previous-page']")?.addEventListener("click", () => {
    if (collectionSummaryState.currentPage > 1) {
      collectionSummaryState.currentPage -= 1;
      renderCollectionSummaryPage();
    }
  });

  container.querySelector("[data-action='next-page']")?.addEventListener("click", () => {
    if (collectionSummaryState.currentPage < totalPages) {
      collectionSummaryState.currentPage += 1;
      renderCollectionSummaryPage();
    }
  });
}

async function reloadCurrentCollectionSummary() {
  const currentPage = collectionSummaryState.currentPage;
  const searchTerm = collectionSummaryState.searchTerm;
  const rarityFilter = collectionSummaryState.rarityFilter;
  const editionFilter = collectionSummaryState.editionFilter;
  const scrollY = window.scrollY;

  const response = await apiFetch(`/collections/${currentCollection.id}/summary`);

  collectionSummaryState = {
    prints: response.data,
    collectionId: currentCollection.id,
    currentPage,
    searchTerm,
    rarityFilter,
    editionFilter
  };

  renderCollectionSummaryPage();

  window.scrollTo(0, scrollY);
}

function renderCollectionSummary(container, prints, collectionId) {
  container.innerHTML = prints.map((print, index) => `
    <article class="summary-card" data-print-id="${print.impresionId}">
      <img 
        class="clickable-print-image"
        data-action="open-print-detail"
        data-print-index="${index}"
        src="${print.imagenSmall || print.imagenNormal || './assets/card-placeholder.png'}" 
        alt="${print.nombreCarta}" 
      />

      <div class="summary-info">
        <h3>${print.nombreCarta}</h3>
        <p>${print.nombreEdicion}</p>
        <p>${print.codigoEdicion} · #${print.numeroColeccion} · ${print.rareza}</p>
        <strong>x${print.cantidadCopias}</strong>
      </div>

      <div class="summary-actions">
        <button class="secondary-button" data-action="toggle-copies">
          Ver copias
        </button>

        <button data-action="open-add-copy-modal">
          Añadir copia
        </button>
      </div>

      <div class="copies-panel" id="copies-${print.impresionId}" hidden></div>
    </article>
  `).join("");

  container.querySelectorAll("[data-action='toggle-copies']").forEach((button) => {
    button.addEventListener("click", async (event) => {
      const card = event.target.closest(".summary-card");
      const printId = card.dataset.printId;
      const panel = card.querySelector(".copies-panel");

      if (!panel.hidden) {
        panel.hidden = true;
        button.textContent = "Ver copias";
        return;
      }

      panel.hidden = false;
      button.textContent = "Ocultar copias";
      panel.innerHTML = `<div class="loading">Cargando copias...</div>`;

      await loadPrintCopies(collectionId, printId, panel);
    });
  });

  container.querySelectorAll("[data-action='open-add-copy-modal']").forEach((button) => {
    button.addEventListener("click", async (event) => {
      const card = event.target.closest(".summary-card");
      const printId = card.dataset.printId;

      await openAddCopyToCollectionModal(collectionId, printId);
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

async function loadPrintCopies(collectionId, printId, panel) {
  try {
    const response = await apiFetch(`/collections/${collectionId}/prints/${printId}/copies`);
    renderCopyGroups(panel, response.data, collectionId, printId);
  } catch (error) {
    panel.innerHTML = `<p class="error">${error.message}</p>`;
  }
}

function renderCopyGroups(container, copies, collectionId, printId) {
  if (!copies.length) {
    container.innerHTML = `<p class="empty-state">No hay copias para esta impresión.</p>`;
    return;
  }

  const groups = groupCopies(copies);

  container.innerHTML = groups.map((group, index) => `
    <div class="copy-group" data-group-index="${index}">
      <div>
        <strong>${group.condicion}</strong>
        <span>${group.idioma}</span>
        <span>${group.esFoil ? "Foil" : "No foil"}</span>
      </div>

      <div class="copy-group-actions">
        <strong>x${group.quantity}</strong>

        <button class="secondary-button small-button" data-action="add-same-copy">
          Añadir igual
        </button>
        <button class="secondary-button small-button" data-action="delete-one-copy">
          Eliminar una
        </button>
      </div>
    </div>
  `).join("");

  container.querySelectorAll("[data-action='add-same-copy']").forEach((button) => {
    button.addEventListener("click", async (event) => {
      const groupElement = event.target.closest(".copy-group");
      const group = groups[Number(groupElement.dataset.groupIndex)];

      button.disabled = true;
      button.textContent = "Añadiendo...";

      try {
        await apiFetch("/copies", {
          method: "POST",
          body: JSON.stringify({
            collectionId: Number(collectionId),
            impresionId: Number(printId),
            condicionId: Number(group.condicionId),
            idioma: group.idioma,
            esFoil: group.esFoil
          })
        });

        await reloadCurrentCollectionSummary();
      } catch (error) {
        alert(error.message);
      } finally {
        button.disabled = false;
        button.textContent = "Añadir igual";
      }
    });
  });

  container.querySelectorAll("[data-action='delete-one-copy']").forEach((button) => {
    button.addEventListener("click", async (event) => {
      const groupElement = event.target.closest(".copy-group");
      const group = groups[Number(groupElement.dataset.groupIndex)];
      const copyId = group.ids[group.ids.length - 1];

      if (!confirm("¿Eliminar una copia?")) {
        return;
      }

      button.disabled = true;
      button.textContent = "Eliminando...";

      try {
        await apiFetch(`/copies/${copyId}`, {
          method: "DELETE"
        });

        await reloadCurrentCollectionSummary();
      } catch (error) {
        alert(error.message);
      } finally {
        button.disabled = false;
        button.textContent = "Eliminar una";
      }
    });
  });
}

function groupCopies(copies) {
  const grouped = {};

  copies.forEach((copy) => {
    const key = `${copy.condicion}-${copy.idioma}-${copy.esFoil}`;

    if (!grouped[key]) {
      grouped[key] = {
        condicion: copy.condicion,
        condicionId: copy.condicionId,
        idioma: copy.idioma,
        esFoil: copy.esFoil,
        quantity: 0,
        ids: []
      };
    }

    grouped[key].quantity += 1;
    grouped[key].ids.push(copy.id);
  });

  return Object.values(grouped);
}

function openCreateCollectionModal() {
  const overlay = document.createElement("div");
  overlay.className = "modal-overlay";

  overlay.innerHTML = `
    <div class="app-modal">
      <div class="modal-header">
        <h2>Nueva colección</h2>
        <button type="button" class="secondary-button small-button" data-action="close-modal">✕</button>
      </div>

      <form id="createCollectionForm" class="form">
        <label>
          Nombre
          <input name="nombre" type="text" required />
        </label>

        <label>
          Descripción
          <input name="descripcion" type="text" />
        </label>

        <div class="modal-actions">
          <button type="submit">Crear colección</button>
          <button type="button" class="secondary-button" data-action="close-modal">Cancelar</button>
        </div>

        <p class="message"></p>
      </form>
    </div>
  `;

  openModal(overlay);

  const handleCloseModal = () => closeModal(overlay);

  overlay.querySelectorAll("[data-action='close-modal']").forEach((button) => {
    button.addEventListener("click", handleCloseModal);
  });

  overlay.addEventListener("click", (event) => {
    if (event.target === overlay) {
      handleCloseModal();
    }
  });

  const form = overlay.querySelector("#createCollectionForm");
  const message = form.querySelector(".message");

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const formData = new FormData(form);
    const submitButton = form.querySelector("button[type='submit']");

    message.textContent = "";
    message.className = "message";
    submitButton.disabled = true;
    submitButton.textContent = "Creando...";

    try {
      await apiFetch("/collections", {
        method: "POST",
        body: JSON.stringify({
          nombre: formData.get("nombre"),
          descripcion: formData.get("descripcion") || null
        })
      });

      message.textContent = "Colección creada correctamente.";
      message.className = "message success";

      await loadCollections();

      setTimeout(() => {
        handleCloseModal();
      }, 600);
    } catch (error) {
      message.textContent = error.message;
      message.className = "message error";
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = "Crear colección";
    }
  });
}

function openDeleteCollectionModal(collection) {
  const overlay = document.createElement("div");
  overlay.className = "modal-overlay";

  overlay.innerHTML = `
    <div class="app-modal">
      <div class="modal-header">
        <h2>Eliminar colección</h2>
        <button type="button" class="secondary-button small-button" data-action="close-modal">✕</button>
      </div>

      <p>
        Vas a eliminar la colección <strong>${collection.nombre}</strong>.
      </p>

      <p class="warning-text">
        Elige qué hacer con las copias que contiene.
      </p>

      <div class="delete-options">
        <button type="button" class="danger-button" data-strategy="delete_all">
          Eliminar colección y todas sus copias
        </button>

        <button type="button" data-strategy="move_to_principal">
          Mover copias a Principal y eliminar colección
        </button>

        <button type="button" class="secondary-button" data-action="close-modal">
          Cancelar
        </button>
      </div>

      <p class="message"></p>
    </div>
  `;

  openModal(overlay);

  const handleCloseModal = () => closeModal(overlay);
  const message = overlay.querySelector(".message");

  overlay.querySelectorAll("[data-action='close-modal']").forEach((button) => {
    button.addEventListener("click", handleCloseModal);
  });

  overlay.addEventListener("click", (event) => {
    if (event.target === overlay) {
      handleCloseModal();
    }
  });

  overlay.querySelectorAll("[data-strategy]").forEach((button) => {
    button.addEventListener("click", async () => {
      const strategy = button.dataset.strategy;

      overlay.querySelectorAll("button").forEach((modalButton) => {
        modalButton.disabled = true;
      });

      button.textContent = "Eliminando...";

      try {
        await apiFetch(`/collections/${collection.id}`, {
          method: "DELETE",
          body: JSON.stringify({ strategy })
        });

        message.textContent = "Colección eliminada correctamente.";
        message.className = "message success";

        await loadCollections();

        setTimeout(() => {
          handleCloseModal();
        }, 700);
      } catch (error) {
        message.textContent = error.message;
        message.className = "message error";

        overlay.querySelectorAll("button").forEach((modalButton) => {
          modalButton.disabled = false;
        });

        button.textContent =
          strategy === "delete_all"
            ? "Eliminar colección y todas sus copias"
            : "Mover copias a Principal y eliminar colección";
      }
    });
  });
}

function openCollectionSearchModal() {
  const overlay = document.createElement("div");
  overlay.className = "modal-overlay";

  overlay.innerHTML = `
    <div class="app-modal collection-search-modal">
      <div class="modal-header">
        <h2>Añadir carta</h2>
        <button type="button" class="secondary-button small-button" data-action="close-modal">✕</button>
      </div>

      <form id="collectionSearchForm" class="search-box">
        <input name="name" type="text" placeholder="Buscar carta..." required />
        <button type="submit">Buscar</button>
      </form>

      <div id="collectionSearchResults" class="collection-search-results">
        <p class="empty-state">Busca una carta para añadirla a esta colección.</p>
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

  const form = overlay.querySelector("#collectionSearchForm");
  const resultsContainer = overlay.querySelector("#collectionSearchResults");

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const submitButton = form.querySelector("button[type='submit']");
    const name = new FormData(form).get("name");

    resultsContainer.innerHTML = `<div class="loading">Buscando cartas...</div>`;
    submitButton.disabled = true;
    submitButton.textContent = "Buscando...";

    try {
      const response = await apiFetch(`/prints/search?name=${encodeURIComponent(name)}`);
      renderCollectionSearchResults(resultsContainer, response.data, handleCloseModal);
    } catch (error) {
      resultsContainer.innerHTML = `<p class="error">${error.message}</p>`;
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = "Buscar";
    }
  });
}

function renderCollectionSearchResults(container, prints, handleCloseModal) {
  if (!prints.length) {
    container.innerHTML = `<p class="empty-state">No se encontraron resultados.</p>`;
    return;
  }

  container.innerHTML = prints.map((print, index) => `
    <article class="collection-search-result" data-print-id="${print.id}">
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
        <p>${print.codigoEdicion} · #${print.numeroColeccion} · ${print.rareza}</p>

        <button class="small-button" data-action="add-search-result-copy">
          Añadir copia
        </button>
      </div>
    </article>
  `).join("");

  container.querySelectorAll("[data-action='open-print-detail']").forEach((image) => {
    image.addEventListener("click", (event) => {
      event.stopPropagation();

      const printIndex = Number(event.target.dataset.printIndex);
      openPrintDetailModal(prints[printIndex]);
    });
  });

  container.querySelectorAll("[data-action='add-search-result-copy']").forEach((button) => {
    button.addEventListener("click", async (event) => {
      const card = event.target.closest(".collection-search-result");
      const printId = card.dataset.printId;

      await openAddCopyToCollectionModal(currentCollection.id, printId, handleCloseModal);
    });
  });
}

async function openAddCopyToCollectionModal(collectionId, printId, afterSuccess = null) {
  const overlay = document.createElement("div");
  overlay.className = "modal-overlay";

  overlay.innerHTML = `
    <div class="app-modal">
      <div class="modal-header">
        <h2>Añadir copia</h2>
        <button type="button" class="secondary-button small-button" data-action="close-modal">✕</button>
      </div>

      <div id="addCopyCollectionModalContent">
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

  const content = overlay.querySelector("#addCopyCollectionModalContent");

  try {
    const response = await apiFetch("/conditions");
    const conditions = response.data;

    content.innerHTML = `
      <form id="addCopyCollectionForm" class="form">
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

    const form = content.querySelector("#addCopyCollectionForm");
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
            collectionId: Number(collectionId),
            impresionId: Number(printId),
            condicionId: Number(formData.get("condicionId")),
            idioma: formData.get("idioma"),
            esFoil: formData.get("esFoil") === "on"
          })
        });

        message.textContent = "Copia añadida correctamente.";
        message.className = "message success";

        await reloadCurrentCollectionSummary();

        setTimeout(() => {
          handleCloseModal();

          if (afterSuccess) {
            afterSuccess();
          }
        }, 600);
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
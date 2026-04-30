import { apiFetch } from "./api.js";

let currentCollection = null;

function openModal(overlay) {
  document.querySelector(".modal-overlay")?.remove();
  document.body.appendChild(overlay);
  document.body.classList.add("modal-open");
}

function closeModal(overlay) {
  overlay.remove();
  document.body.classList.remove("modal-open");
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
      </div>

      <div id="collectionSummary" class="summary-grid">
        <div class="loading">Cargando colección...</div>
      </div>
    </section>
  `;

  document.getElementById("backToCollectionsBtn").addEventListener("click", () => {
    renderCollections(main);
  });

  try {
    const response = await apiFetch(`/collections/${collectionId}/summary`);
    renderCollectionSummary(document.getElementById("collectionSummary"), response.data, collectionId);
  } catch (error) {
    document.getElementById("collectionSummary").innerHTML = `<p class="error">${error.message}</p>`;
  }
}

function renderCollectionSummary(container, prints, collectionId) {
  if (!prints.length) {
    container.innerHTML = `<p class="empty-state">Esta colección todavía no tiene cartas.</p>`;
    return;
  }

  container.innerHTML = prints.map((print) => `
    <article class="summary-card" data-print-id="${print.impresionId}">
      <img 
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

        await renderCollectionDetail(currentCollection.id, currentCollection.nombre);
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

        await renderCollectionDetail(currentCollection.id, currentCollection.nombre);
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

async function openAddCopyToCollectionModal(collectionId, printId) {
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

        await renderCollectionDetail(currentCollection.id, currentCollection.nombre);

        setTimeout(() => {
          handleCloseModal();
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
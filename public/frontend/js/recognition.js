import { apiFetch, apiUpload } from "./api.js";

export async function renderRecognition(container) {
  container.innerHTML = `
    <section class="panel wide-panel">
      <div class="section-header">
        <div>
          <h1>Subir foto</h1>
          <p>Sube una imagen de una carta y el sistema intentará reconocerla.</p>
        </div>

        <button type="button" class="secondary-button" id="openRecognitionGuideBtn">
          Guía de uso
        </button>
      </div>

      <form id="recognitionForm" class="form">
        <label>
          Colección
          <select name="collectionId" required>
            <option value="">Cargando colecciones...</option>
          </select>
        </label>

        <label>
          Condición
          <select name="condicionId" required>
            <option value="">Cargando condiciones...</option>
          </select>
        </label>

        <label class="checkbox-label">
          <input type="checkbox" name="esFoil" />
          Foil
        </label>

        <label>
          Imagen
          <input name="image" type="file" accept="image/*" required />
        </label>

        <button type="submit">Analizar imagen</button>

        <p id="recognitionMessage" class="message"></p>
      </form>

      <section id="recognitionResult" class="recognition-result"></section>
    </section>
  `;

  await loadRecognitionFormOptions();

  document.getElementById("openRecognitionGuideBtn").addEventListener("click", () => {
    openRecognitionGuideModal();
  });

  document.getElementById("recognitionForm").addEventListener("submit", handleRecognitionSubmit);
}

async function loadRecognitionFormOptions() {
  const collectionSelect = document.querySelector("#recognitionForm select[name='collectionId']");
  const conditionSelect = document.querySelector("#recognitionForm select[name='condicionId']");
  const message = document.getElementById("recognitionMessage");

  try {
    const [collectionsResponse, conditionsResponse] = await Promise.all([
      apiFetch("/collections"),
      apiFetch("/conditions")
    ]);

    collectionSelect.innerHTML = collectionsResponse.data.map((collection) => `
      <option value="${collection.id}">
        ${collection.nombre}
      </option>
    `).join("");

    conditionSelect.innerHTML = conditionsResponse.data.map((condition) => `
      <option value="${condition.id}">
        ${condition.descripcion}
      </option>
    `).join("");
  } catch (error) {
    message.textContent = error.message;
    message.className = "message error";
  }
}

async function handleRecognitionSubmit(event) {
  event.preventDefault();

  const form = event.target;
  const formData = new FormData(form);
  const image = formData.get("image");
  const message = document.getElementById("recognitionMessage");
  const resultContainer = document.getElementById("recognitionResult");
  const submitButton = form.querySelector("button[type='submit']");

  message.textContent = "";
  message.className = "message";
  resultContainer.innerHTML = "";

  if (!image || !image.size) {
    message.textContent = "Selecciona una imagen antes de analizar.";
    message.className = "message error";
    return;
  }

  submitButton.disabled = true;
  submitButton.textContent = "Analizando...";

  try {
    const uploadData = new FormData();

    uploadData.append("image", image);
    uploadData.append("collectionId", formData.get("collectionId"));
    uploadData.append("conditionId", formData.get("condicionId"));
    uploadData.append("esFoil", formData.get("esFoil") === "on" ? "1" : "0");

    const response = await apiUpload("/image-recognition/analyze", uploadData);

    renderRecognitionResult(resultContainer, response.data, {
      collectionId: Number(formData.get("collectionId")),
      condicionId: Number(formData.get("condicionId")),
      esFoil: formData.get("esFoil") === "on"
    });
  } catch (error) {
    message.textContent = error.message;
    message.className = "message error";
  } finally {
    submitButton.disabled = false;
    submitButton.textContent = "Analizar imagen";
  }
}

function renderRecognitionResult(container, data, copyOptions) {
  const status = data.resolution?.status;

  if (status === "matched") {
    renderMatchedResult(container, data, copyOptions);
    return;
  }

  if (status === "needs_confirmation") {
    renderCandidateResults(container, data, copyOptions);
    return;
  }

  if (status === "ambiguous_card") {
    container.innerHTML = `
      <p class="message error">
        La imagen es demasiado ambigua y puede corresponder a varias cartas. Prueba con una imagen más clara.
      </p>
    `;
    return;
  }

  if (status === "not_found") {
    container.innerHTML = `
      <p class="message error">
        No se ha podido reconocer la carta. Prueba con otra imagen o añádela manualmente desde el buscador.
      </p>
    `;
    return;
  }

  container.innerHTML = `
    <p class="message error">
      No se pudo interpretar la respuesta del reconocimiento.
    </p>
  `;
}

function renderMatchedResult(container, data, copyOptions) {
  const print = data.recognizedPrint.print;
  const card = data.recognizedPrint.card;
  const language = data.recognizedPrint.copyDefaults.language || "EN";

  container.innerHTML = `
    <div class="recognition-card">
      <h2>Carta reconocida</h2>

      <div class="recognition-card-content">
        <img 
          src="${print.imageNormal || print.imageSmall || './assets/card-placeholder.png'}"
          alt="${card.nameEs || card.nameEn}" 
        />

        <div>
          <h3>${card.nameEs || card.nameEn}</h3>
          <p>${print.setName}</p>
          <p>${print.setCode} · #${print.collectorNumber} · ${print.rarity}</p>
          <p>Idioma detectado: ${language}</p>

          <button id="confirmRecognition">
            Añadir a colección
          </button>
        </div>
      </div>

      <p class="message"></p>
    </div>
  `;

  const button = container.querySelector("#confirmRecognition");
  const message = container.querySelector(".message");

  button.addEventListener("click", async () => {
    button.disabled = true;
    button.textContent = "Guardando...";

    try {
      await apiFetch("/copies", {
        method: "POST",
        body: JSON.stringify({
          collectionId: copyOptions.collectionId,
          impresionId: print.id,
          condicionId: copyOptions.condicionId,
          idioma: language,
          esFoil: copyOptions.esFoil
        })
      });

      message.textContent = "Copia añadida correctamente.";
      message.className = "message success";
      button.textContent = "Añadida";
    } catch (error) {
      message.textContent = error.message;
      message.className = "message error";
      button.disabled = false;
      button.textContent = "Añadir a colección";
    }
  });
}

function renderCandidateResults(container, data, copyOptions) {
  const candidates = data.candidatePrints || [];

  if (!candidates.length) {
    container.innerHTML = `
      <p class="message error">
        No se recibieron impresiones candidatas.
      </p>
    `;
    return;
  }

  container.innerHTML = `
    <div class="recognition-card">
      <h2>Selecciona la impresión correcta</h2>
      <p>Se ha reconocido la carta, pero hay varias impresiones posibles.</p>

      <div class="candidate-grid">
        ${candidates.map((item, index) => `
          <div class="candidate-card" data-index="${index}">
            <img 
              src="${item.print.imageNormal || item.print.imageSmall || './assets/card-placeholder.png'}" 
              alt="${item.card.nameEs || item.card.nameEn}"
            />

            <div>
              <strong>${item.card.nameEs || item.card.nameEn}</strong>
              <p>${item.print.setName}</p>
              <p>${item.print.setCode} · #${item.print.collectorNumber}</p>
            </div>
          </div>
        `).join("")}
      </div>

      <p class="message"></p>
    </div>
  `;

  const message = container.querySelector(".message");
  const cards = container.querySelectorAll(".candidate-card");
  let selectionMade = false;

  cards.forEach((cardElement) => {
    cardElement.addEventListener("click", async () => {
      if (selectionMade) {
        return;
      }

      selectionMade = true;

      cards.forEach((card) => {
        card.classList.add("disabled-card");
      });

      cardElement.classList.remove("disabled-card");
      cardElement.classList.add("selected-card");

      const selected = candidates[Number(cardElement.dataset.index)];

      try {
        await apiFetch("/copies", {
          method: "POST",
          body: JSON.stringify({
            collectionId: copyOptions.collectionId,
            impresionId: selected.print.id,
            condicionId: copyOptions.condicionId,
            idioma: selected.copyDefaults.language || "EN",
            esFoil: copyOptions.esFoil
          })
        });

        message.textContent = "Copia añadida correctamente.";
        message.className = "message success";
      } catch (error) {
        message.textContent = error.message;
        message.className = "message error";

        selectionMade = false;

        cards.forEach((card) => {
          card.classList.remove("disabled-card", "selected-card");
        });
      }
    });
  });
}

function openModal(overlay) {
  document.querySelector(".modal-overlay")?.remove();
  document.body.appendChild(overlay);
  document.body.classList.add("modal-open");

  const handleEsc = (event) => {
    if (event.key === "Escape") {
      closeModal(overlay);
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

function openRecognitionGuideModal() {
  const overlay = document.createElement("div");
  overlay.className = "modal-overlay";

  overlay.innerHTML = `
    <div class="app-modal recognition-guide-modal">
      <div class="modal-header">
        <h2>Guía para subir imágenes</h2>
        <button type="button" class="secondary-button small-button" data-action="close-modal">✕</button>
      </div>

      <div class="recognition-guide-content">
        <section>
          <h3>Cómo sacar una buena foto</h3>

          <p>
            Para mejorar el reconocimiento, procura que la carta esté completa,
            centrada, bien iluminada y enfocada.
          </p>

          <p>
            Evita reflejos fuertes, fondos muy cargados y fotos donde aparezcan
            varias cartas a la vez.
          </p>

          <p>
            La colocación correcta de la carta es lo mas importante para un correcto reconocimiento.
          </p>
        </section>

        <section>
          <h3>Recomendación: card slider</h3>

          <p>
            Se recomienda usar un card slider para mantener la carta plana y centrada.
          </p>

          <img src="./assets/recognition-guide/1.jpeg" alt="Card slider" />
        </section>

        <section>
          <h3>Ejemplos de fotos válidas</h3>
          <p>Procura mantener mínimo margen vertical y que este bien centrada</p>

          <div class="recognition-guide-grid">
            <img src="./assets/recognition-guide/2.jpeg" alt="Foto válida 1" />
            <img src="./assets/recognition-guide/3.jpeg" alt="Foto válida 2" />
          </div>
        </section>

        <section>
          <h3>Ejemplos de fotos inválidas</h3>
          <p>Imágenes torcidas o con mucha/poca luz</p>

          <div class="recognition-guide-grid">
            <img src="./assets/recognition-guide/4.jpeg" alt="Foto inválida 1" />
            <img src="./assets/recognition-guide/5.jpeg" alt="Foto inválida 2" />
          </div>
        </section>
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
}
import { apiFetch } from "./api.js";
import { appState, updateAuthUI } from "./app.js";
import { navigateTo } from "./router.js";

let isRegisterMode = false;

export async function getCurrentUser() {
  const response = await apiFetch("/me");
  return response.data;
}

export async function logout() {
  await apiFetch("/logout", {
    method: "POST"
  });
}

export function renderLogin(container) {
  isRegisterMode = false;

  container.innerHTML = `
    <section class="panel auth-panel">
      <h1 id="authTitle">Iniciar sesión</h1>
      <p class="auth-subtitle">Accede para gestionar tus colecciones de Magic.</p>

      <form id="loginForm" class="form">
        <input 
          id="usernameInput"
          name="nombreUsuario" 
          type="text" 
          placeholder="Nombre de usuario" 
          hidden 
        />

        <input name="email" type="email" placeholder="Email" required />
        <input name="password" type="password" placeholder="Contraseña" required />

        <button type="submit">Entrar</button>
      </form>

      <p id="loginMessage" class="message"></p>

      <p id="toggleAuthMode" class="auth-toggle">
        ¿No tienes cuenta? <span>Regístrate</span>
      </p>
    </section>
  `;

  const form = document.getElementById("loginForm");
  const title = document.getElementById("authTitle");
  const usernameInput = document.getElementById("usernameInput");
  const toggleAuthMode = document.getElementById("toggleAuthMode");
  const submitButton = form.querySelector("button[type='submit']");
  const message = document.getElementById("loginMessage");

  toggleAuthMode.addEventListener("click", () => {
    isRegisterMode = !isRegisterMode;

    message.textContent = "";
    message.className = "message";

    if (isRegisterMode) {
      title.textContent = "Crear cuenta";
      usernameInput.hidden = false;
      usernameInput.required = true;
      submitButton.textContent = "Registrarse";
      toggleAuthMode.innerHTML = `¿Ya tienes cuenta? <span>Inicia sesión</span>`;
    } else {
      title.textContent = "Iniciar sesión";
      usernameInput.hidden = true;
      usernameInput.required = false;
      usernameInput.value = "";
      submitButton.textContent = "Entrar";
      toggleAuthMode.innerHTML = `¿No tienes cuenta? <span>Regístrate</span>`;
    }
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const formData = new FormData(form);

    message.textContent = "";
    message.className = "message";

    submitButton.disabled = true;
    submitButton.textContent = isRegisterMode ? "Registrando..." : "Entrando...";

    try {
      const endpoint = isRegisterMode ? "/register" : "/login";

      const payload = isRegisterMode
        ? {
            username: formData.get("nombreUsuario").trim(),
            email: formData.get("email").trim(),
            password: formData.get("password")
          }
        : {
            email: formData.get("email").trim(),
            password: formData.get("password")
          };

      const response = await apiFetch(endpoint, {
        method: "POST",
        body: JSON.stringify(payload)
      });

      if (isRegisterMode) {
        isRegisterMode = false;

        title.textContent = "Iniciar sesión";
        usernameInput.hidden = true;
        usernameInput.required = false;
        usernameInput.value = "";
        submitButton.textContent = "Entrar";
        toggleAuthMode.innerHTML = `¿No tienes cuenta? <span>Regístrate</span>`;

        message.textContent = "Cuenta creada correctamente. Ahora inicia sesión.";
        message.className = "message success";

        form.reset();
        return;
      }

      appState.user = response.data;
      updateAuthUI();
      navigateTo("home");

    } catch (error) {
      message.textContent = error.message;
      message.className = "message error";
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = isRegisterMode ? "Registrarse" : "Entrar";
    }
  });
}
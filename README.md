# MTG Collection Manager

Aplicación web para gestionar colecciones de cartas de Magic: The Gathering.

## Stack

* Backend: PHP + Slim 4
* Base de datos: MySQL
* Frontend: HTML, CSS, JavaScript
* Servicio de reconocimiento: Python (FastAPI)
* Contenedores: Docker + Docker Compose

## Requisitos

* Docker Desktop (incluye Docker y Docker Compose): https://www.docker.com/products/docker-desktop/

## Instalación

### Obtener el proyecto

Puedes clonar el repositorio:

```
git clone https://github.com/Gunval-AI/mtg-collection-manager.git
cd mtg-collection-manager
```

O descargarlo como ZIP desde GitHub y descomprimirlo.

---

### Crear archivo `.env`

Debes estar situado en la carpeta raiz del proyecto 

En Linux / Mac:

```
cp .env.example .env
```

En Windows (CMD o PowerShell):

```
copy .env.example .env
```

---

### Levantar el proyecto

* Primera ejecución del proyecto:

```
docker compose up --build
```

* Ejecuciones posteriores:

```
docker compose up
```

* Si se descarga una nueva versión del proyecto:

```
docker compose down
docker compose up --build
```

* Si además se quiere eliminar la base de datos y volver al estado incial:

```
docker compose down -v
docker compose up --build
```

## Acceso

* Local: http://localhost:8080/frontend/
* Railway: https://mtg-collection-manager-production.up.railway.app/frontend/

## Base de datos

Se inicializa automáticamente al levantar Docker:

* `01-schema.sql` → estructura
* `02_seed.sql` → datos base

## Funcionalidades principales

* Autenticación de usuarios (registro, login, logout)
* Gestión de colecciones
* Gestión de copias de cartas
* Búsqueda de cartas e impresiones
* Reconocimiento de cartas mediante imagen


## Notas

* Configuración mediante `.env`
* El servicio de reconocimiento funciona como microservicio independiente
* La base de datos se crea automáticamente al iniciar Docker

## Ejecución desde cero

1. Instalar Docker Desktop
2. Clonar el repositorio o descargarlo como ZIP
3. Crear `.env`
4. Ejecutar `docker compose up --build`
5. Acceder a http://localhost:8080/frontend/

## Ejemplos de uso

* Ejemplos de busquedas: "Aang", "Donatello", "Fire", "Fuego", "Tunnel", "Ratas"...

* En /test-images hay imagenes de prueba listas para ser utilizadas en el servicio de reconocimiento.
